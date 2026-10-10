<?php
/**
 * Public Lead Ingestion API - Tabeeb Contractor
 * Endpoint: POST /api/leads
 * 
 * Secure, same-origin, rate-limited, anti-spam, transactional MySQL-backed
 * CRM ingestion handler with durable notification outbox creation.
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/data.php';

// 1. Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only POST is accepted.']);
    exit;
}

// 2. Request Size Guard (Max 64KB payload)
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 65536) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Request payload exceeds maximum allowed size.']);
    exit;
}

// 3. Same-Origin & Host Verification
if (!verify_same_origin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Cross-origin request rejected.']);
    exit;
}

// 4. Rate Limiting Check (Max 5 submissions per 10 minutes per IP)
$rateLimit = check_rate_limit('public_lead_submission', 5, 600);
if (!$rateLimit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success'     => false,
        'message'     => $rateLimit['message'],
        'retry_after' => $rateLimit['retry_after']
    ]);
    exit;
}

// 5. Parse Incoming Payload (Supports JSON and Standard FormData)
$rawBody = file_get_contents('php://input');
$data = [];

if (!empty($_POST)) {
    $data = $_POST;
} elseif (!empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $data = $decoded;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Malformed JSON payload.']);
        exit;
    }
}

// 6. Anti-Spam Honeypot & Cryptographic Timing Check
$spamCheck = validate_honeypot_and_timing($data, 2);
if (!$spamCheck['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $spamCheck['reason']]);
    exit;
}

// 7. CSRF Token Verification
$csrfToken = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    // If anonymous form didn't supply token, verify same-origin Sec-Fetch-Site
    $secFetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($secFetchSite !== 'same-origin' && empty($_SERVER['HTTP_REFERER'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing security token. Please refresh the page.']);
        exit;
    }
}

// 8. Strict Scalar Type Verification (Arrays/Objects in scalar fields must receive controlled 422)
$scalarFields = ['name', 'phone', 'email', 'service', 'property_type', 'budget', 'message', 'requirements', 'preferred_contact', 'source', 'landing_page', 'referrer'];
foreach ($scalarFields as $field) {
    if (isset($data[$field]) && !is_scalar($data[$field])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Field '{$field}' must be a scalar string value."]);
        exit;
    }
}

// 9. Input Sanitization & Explicit Allowlist Validation
$name = trim((string)($data['name'] ?? ''));
if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid full name (2 to 100 characters).']);
    exit;
}

// Telephone validity (strictly enforces normalizer.valid)
$rawPhone = trim((string)($data['phone'] ?? ''));
$phoneNorm = normalize_singapore_phone($rawPhone);
if (!$phoneNorm['valid']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid Singapore telephone number (8 digits starting with 6, 8, or 9) or valid international number with country code.']);
    exit;
}
$formattedPhone = $phoneNorm['formatted'];

// Optional Email
$email = trim((string)($data['email'] ?? ''));
if (!empty($email)) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
        exit;
    }
} else {
    $email = null;
}

// Service Validation: Check against published services
$serviceInput = trim((string)($data['service'] ?? 'General Renovation'));
$publishedServices = get_all_services(true);
$validService = null;

foreach ($publishedServices as $ps) {
    if (strcasecmp($ps['slug'], $serviceInput) === 0 || strcasecmp($ps['name'], $serviceInput) === 0 || strcasecmp($ps['id'], $serviceInput) === 0) {
        $validService = $ps['name'];
        break;
    }
}
if (!$validService) {
    // If not matching specific service, check if general contracting enquiry
    $allowedGeneric = ['General Renovation', 'Commercial Reinstatement', 'Hacking & Demolition', 'Tiling & Flooring', 'Waterproofing'];
    foreach ($allowedGeneric as $ag) {
        if (strcasecmp($ag, $serviceInput) === 0) {
            $validService = $ag;
            break;
        }
    }
}
if (!$validService) {
    $validService = 'General Renovation';
}

// Message / Description
$message = trim((string)($data['message'] ?? $data['requirements'] ?? ''));
if (empty($message)) {
    $message = 'Inquiry for ' . $validService;
}
$message = mb_substr($message, 0, 3000, 'UTF-8');

// Property Type allowlist
$allowedProperties = ['HDB Flat', 'Condominium', 'Landed Property', 'Commercial / Office', 'Industrial', 'Unspecified'];
$propInput = trim((string)($data['property_type'] ?? 'Unspecified'));
$propertyType = in_array($propInput, $allowedProperties, true) ? $propInput : 'Unspecified';

// Budget allowlist
$allowedBudgets = ['Below $10,000', '$10,000 - $30,000', '$30,000 - $50,000', '$50,000 - $100,000', 'Above $100,000', 'Unspecified'];
$budgetInput = trim((string)($data['budget'] ?? 'Unspecified'));
$budget = in_array($budgetInput, $allowedBudgets, true) ? $budgetInput : 'Unspecified';

// Preferred contact method
$allowedContacts = ['whatsapp', 'phone', 'email', 'any'];
$prefInput = strtolower(trim((string)($data['preferred_contact'] ?? 'whatsapp')));
$preferredContact = in_array($prefInput, $allowedContacts, true) ? $prefInput : 'whatsapp';

// Consent status (Actual consent evidence, not hardcoded true)
$consentChoice = !empty($data['consent_agree']) || (isset($data['consent_status']) && (string)$data['consent_status'] === '1');
$consentStatus = $consentChoice ? 1 : 0;
$now = date('Y-m-d H:i:s');

// Attribution & Source
$source = substr(trim((string)($data['source'] ?? 'website_quote_form')), 0, 80);
$landingPage = substr(trim((string)($data['landing_page'] ?? $_SERVER['HTTP_REFERER'] ?? '/')), 0, 255);
$referrer = substr(trim((string)($data['referrer'] ?? '')), 0, 255);
$ipHash = get_client_ip_hash();
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

// 10. Database Connection Check (Strict Fail-Closed, No Pseudo Success)
if (!DB::isConnected()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Our inquiry system is temporarily undergoing database maintenance. Your details were not lost. Please contact our team directly via WhatsApp or phone.',
        'contact_options' => [
            'phone'    => '+65 8648 4883',
            'whatsapp' => 'https://wa.me/6586484883',
            'email'    => 'info@tabeebgroup.com'
        ]
    ]);
    exit;
}

// 11. Deduplication / Idempotency Check (Same phone & service within 10 minutes)
try {
    $duplicate = db_one(
        "SELECT `id`, `lead_number` FROM `leads` 
         WHERE `phone` = ? AND `service_requested` = ? AND `created_at` >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1",
        [$formattedPhone, $validService]
    );

    if ($duplicate) {
        // Return existing lead reference safely without creating duplicate records or email floods
        http_response_code(200);
        echo json_encode([
            'success'      => true,
            'lead_id'      => $duplicate['lead_number'],
            'message'      => 'Thank you! We have already received your inquiry and our coordinator is reviewing your details right now.',
            'is_duplicate' => true
        ]);
        exit;
    }
} catch (Throwable $t) {
    error_log('[Deduplication Check Error] ' . $t->getMessage());
}

// 12. Generate Unique Human-Readable Reference
$leadYear = date('Y');
$leadRandom = strtoupper(bin2hex(random_bytes(3))); // 6 alphanumeric characters
$leadNumber = "TBC-{$leadYear}-{$leadRandom}";

// 13. Transactional Write Execution (Lead + Activity + Notification Outbox)
$insertedId = null;

try {
    $insertedId = DB::transaction(function($pdo) use (
        $leadNumber, $name, $formattedPhone, $email, $preferredContact, $validService,
        $propertyType, $budget, $message, $source, $landingPage, $referrer,
        $consentStatus, $now, $ipHash, $userAgent
    ) {
        // A. Insert Primary Lead
        $stmt = $pdo->prepare(
            "INSERT INTO `leads` 
             (`lead_number`, `name`, `phone`, `email`, `preferred_contact_method`, `service_requested`, 
              `property_type`, `estimated_budget`, `project_description`, `status`, `priority`, 
              `source`, `landing_page`, `referrer`, `consent_status`, `consent_timestamp`, `submission_timestamp`, 
              `ip_hash`, `user_agent`, `created_at`) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'medium', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $leadNumber, $name, $formattedPhone, $email, $preferredContact, $validService,
            $propertyType, $budget, $message, $source, $landingPage, $referrer,
            $consentStatus, $now, $now, $ipHash, $userAgent, $now
        ]);

        $leadId = (int)$pdo->lastInsertId();
        if ($leadId <= 0) {
            throw new RuntimeException('Failed to obtain valid lastInsertId from leads table.');
        }

        // B. Insert Lead Activity
        $actStmt = $pdo->prepare(
            "INSERT INTO `lead_activities` (`lead_id`, `activity_type`, `description`, `created_at`) 
             VALUES (?, 'lead_received', 'Inquiry submitted via verified website form', NOW())"
        );
        $actStmt->execute([$leadId]);

        // C. Queue into durable notification_outbox for active recipients
        $recipientsStmt = $pdo->query("SELECT * FROM `notification_recipients` WHERE `is_active` = 1");
        $recipients = $recipientsStmt->fetchAll();

        if (!empty($recipients)) {
            $emailSubject = "New Inquiry #{$leadNumber} - {$validService}";
            $emailHtml = "<div style='font-family: Arial, sans-serif; max-width: 600px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>"
                       . "<h2 style='color: #0a1128; margin-top: 0;'>New Inquiry Received</h2>"
                       . "<p><strong>Reference:</strong> {$leadNumber}</p>"
                       . "<p><strong>Customer Name:</strong> " . htmlspecialchars($name) . "</p>"
                       . "<p><strong>Phone:</strong> <a href='tel:{$formattedPhone}'>{$formattedPhone}</a></p>"
                       . ($email ? "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>" : "")
                       . "<p><strong>Service Requested:</strong> " . htmlspecialchars($validService) . "</p>"
                       . "<p><strong>Property Type:</strong> " . htmlspecialchars($propertyType) . "</p>"
                       . "<p><strong>Estimated Budget:</strong> " . htmlspecialchars($budget) . "</p>"
                       . "<p><strong>Preferred Contact:</strong> " . ucfirst($preferredContact) . "</p>"
                       . "<div style='margin-top: 15px; padding: 12px; background: #f8fafc; border-radius: 6px;'>"
                       . "<strong>Project Description:</strong><br>" . nl2br(htmlspecialchars($message))
                       . "</div>"
                       . "</div>";
            $emailPlain = "New Inquiry #{$leadNumber}\nName: {$name}\nPhone: {$formattedPhone}\nService: {$validService}\nBudget: {$budget}\nDetails: {$message}";

            $outboxStmt = $pdo->prepare(
                "INSERT INTO `notification_outbox` 
                 (`lead_id`, `recipient_email`, `recipient_name`, `recipient_type`, `subject`, `body_html`, `body_plain`, `status`, `idempotency_key`, `next_retry_at`, `created_at`) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'queued', ?, NOW(), NOW())"
            );

            foreach ($recipients as $rcpt) {
                // Apply service filter if configured
                if (!empty($rcpt['service_filter']) && stripos($validService, $rcpt['service_filter']) === false) {
                    continue;
                }
                $idempotencyKey = hash('sha256', "lead_{$leadId}_rcpt_{$rcpt['id']}_" . date('Ymd_H'));
                $rcptType = in_array($rcpt['recipient_type'] ?? '', ['to', 'cc', 'bcc']) ? $rcpt['recipient_type'] : 'to';
                $outboxStmt->execute([
                    $leadId,
                    $rcpt['email'],
                    $rcpt['name'],
                    $rcptType,
                    $emailSubject,
                    $emailHtml,
                    $emailPlain,
                    $idempotencyKey
                ]);
            }
        }

        return $leadId;
    });

} catch (Throwable $t) {
    error_log('[Lead Ingestion Transaction Failure] ' . $t->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'We could not save your inquiry at this moment due to an internal processing error. Please contact us directly at +65 8648 4883.',
        'contact_options' => [
            'phone'    => '+65 8648 4883',
            'whatsapp' => 'https://wa.me/6586484883',
            'email'    => 'info@tabeebgroup.com'
        ]
    ]);
    exit;
}

// 14. Dispatch immediate asynchronous outbox processor in background if CLI available
if (function_exists('exec')) {
    $workerScript = dirname(__DIR__) . '/scripts/notification_worker.php';
    if (file_exists($workerScript)) {
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            @pclose(@popen("start /B php \"" . $workerScript . "\" > NUL 2>&1", "r"));
        } else {
            @exec("php \"" . $workerScript . "\" > /dev/null 2>&1 &");
        }
    }
}

// 15. Return Verified Success Response
http_response_code(201);
echo json_encode([
    'success'       => true,
    'lead_id'       => $leadNumber,
    'message'       => 'Thank you! Your inquiry has been received. Our site supervisor will contact you shortly.',
    'service'       => $validService,
    'contact_pref'  => $preferredContact
]);
exit;
