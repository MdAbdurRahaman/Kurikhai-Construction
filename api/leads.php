<?php
/**
 * Public Lead Ingestion API - Tabeeb Contractor
 * Endpoint: POST /api/leads
 * 
 * Secure, same-origin, rate-limited, anti-spam, transactional MySQL-backed
 * CRM ingestion handler with idempotent lead creation and durable notification outbox.
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

// 6. Strict Scalar Type Verification on ALL received fields FIRST
// Any nested array or object in any field receives a controlled 422 before typed functions
foreach ($data as $key => $val) {
    if (!is_scalar($val) && $val !== null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Field '{$key}' must be a scalar value."]);
        exit;
    }
}

// 7. Anti-Spam Honeypot & Cryptographic Timing Check
$spamCheck = validate_honeypot_and_timing($data, 2);
if (!$spamCheck['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $spamCheck['reason']]);
    exit;
}

// 8. CSRF Token Verification (Strict validation - never bypassed)
$csrfToken = (string)($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page and try again.']);
    exit;
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

// Service Validation: Check against published services and canonical categories
$serviceInput = trim((string)($data['service'] ?? ''));
if (empty($serviceInput)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please select a service from the options provided.']);
    exit;
}

$publishedServices = get_all_services(true);
$validService = null;
$serviceSlug = null;

foreach ($publishedServices as $ps) {
    if (strcasecmp($ps['slug'] ?? '', $serviceInput) === 0 || 
        strcasecmp($ps['name'] ?? '', $serviceInput) === 0 || 
        strcasecmp((string)($ps['id'] ?? ''), $serviceInput) === 0) {
        $validService = $ps['name'];
        $serviceSlug = $ps['slug'];
        break;
    }
}

$knownCategories = [
    'renovation-contractor-singapore' => 'Renovation Contractor Singapore',
    'general-renovation'              => 'General Renovation',
    'hdb-renovation'                  => 'HDB Renovation Works',
    'commercial-reinstatement'        => 'Commercial Reinstatement',
    'reinstatement'                   => 'Commercial Reinstatement',
    'hacking-demolition'              => 'Hacking & Demolition Works',
    'tiling-works'                    => 'Tiling Works & Screeding',
    'vinyl-flooring'                  => 'Vinyl Flooring Works',
    'waterproofing'                   => 'Waterproofing Works',
    'plumbing'                        => 'Plumbing Works',
    'electrical'                      => 'Electrical Works',
    'painting-works'                  => 'Painting Works',
    'plastering'                      => 'Plastering & Skim Coating',
    'ceiling-partition'               => 'Ceiling & Partition Works'
];

if (!$validService) {
    foreach ($knownCategories as $catSlug => $catTitle) {
        if (strcasecmp($catSlug, $serviceInput) === 0 || strcasecmp($catTitle, $serviceInput) === 0) {
            $validService = $catTitle;
            $serviceSlug = $catSlug;
            break;
        }
    }
}

// Reject unknown service values rather than defaulting quietly
if (!$validService || !$serviceSlug) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please select a valid service from the options provided.']);
    exit;
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
$allowedBudgets = [
    'Under $5,000', 'Below $10,000', '$5,000 - $20,000', '$10,000 - $30,000',
    '$20,000 - $50,000', '$30,000 - $50,000', '$50,000 - $100,000', 'Above $50,000',
    'Above $100,000', 'Unspecified'
];
$budgetInput = trim((string)($data['budget'] ?? 'Unspecified'));
$budget = in_array($budgetInput, $allowedBudgets, true) ? $budgetInput : 'Unspecified';

// Preferred contact method
$allowedContacts = ['whatsapp', 'phone', 'email', 'any'];
$prefInput = strtolower(trim((string)($data['preferred_contact'] ?? 'whatsapp')));
$preferredContact = in_array($prefInput, $allowedContacts, true) ? $prefInput : 'whatsapp';

// Consent status (Explicit agreement check or submission notice consent)
$consentMethod = trim((string)($data['consent_mechanism'] ?? ''));
$hasExplicitConsent = in_array(strtolower((string)($data['consent'] ?? $data['consent_agree'] ?? $data['consent_status'] ?? '')), ['1', 'true', 'on', 'yes'], true);
$hasNoticeConsent = ($consentMethod === 'submission_notice_consent');

if (!$hasExplicitConsent && !$hasNoticeConsent) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please confirm your consent to process your quotation request.']);
    exit;
}
$consentStatus = 1;
$now = date('Y-m-d H:i:s');

// Attribution & Source
$source = substr(trim((string)($data['source'] ?? 'website_quote_form')), 0, 80);
$landingPage = substr(trim((string)($data['landing_page'] ?? $_SERVER['HTTP_REFERER'] ?? '/')), 0, 255);
$referrer = substr(trim((string)($data['referrer'] ?? '')), 0, 255);
$ipHash = get_client_ip_hash();
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

// Capture UTM attribution
$utmParams = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utmKey) {
    if (!empty($data[$utmKey])) {
        $utmParams[$utmKey] = substr(trim((string)$data[$utmKey]), 0, 100);
    }
}
$utmJson = !empty($utmParams) ? json_encode($utmParams) : null;

// 10. Database Connection Check (Strict Fail-Closed)
if (!DB::isConnected()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Our inquiry system is temporarily undergoing scheduled maintenance. Please contact our team directly via phone at +65 8648 4883 or WhatsApp.',
        'contact_options' => [
            'phone'    => '+65 8648 4883',
            'whatsapp' => 'https://wa.me/6586484883',
            'email'    => 'info@tabeebgroup.com'
        ]
    ]);
    exit;
}

require_once __DIR__ . '/../includes/lead_request.php';

// A key identifies one intended submission. Different keys always identify different enquiries.
$providedIdempotencyKey = trim((string)($data['idempotency_key'] ?? ''));
if ($providedIdempotencyKey !== '' && !preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $providedIdempotencyKey)) {
    http_response_code(422); echo json_encode(['success'=>false, 'message'=>'Invalid submission identifier.']); exit;
}
$idempotencyKey = $providedIdempotencyKey ?: 'srv_' . bin2hex(random_bytes(16));
$payloadHash = lead_request_fingerprint([
    'name'=>$name, 'phone'=>$formattedPhone, 'email'=>$email, 'service_requested'=>$validService,
    'property_type'=>$propertyType, 'estimated_budget'=>$budget, 'project_description'=>$message,
    'preferred_contact_method'=>$preferredContact, 'consent_status'=>$consentStatus, 'source'=>$source
]);
try {
    $existingByKey = DB::query("SELECT * FROM leads WHERE idempotency_key = ? LIMIT 1", [$idempotencyKey])->fetch();
    if ($existingByKey) lead_replay_response($existingByKey, $payloadHash, $serviceSlug);
} catch (Throwable $t) {
    error_log('[Lead replay lookup failed] ' . get_class($t));
    http_response_code(503); echo json_encode(['success'=>false,'message'=>'The enquiry system is temporarily unavailable. Please try again.']); exit;
}

// 12. Generate Unique Human-Readable Reference
$leadYear = date('Y');
$leadRandom = strtoupper(bin2hex(random_bytes(3))); // 6 alphanumeric characters
$leadNumber = "TBC-{$leadYear}-{$leadRandom}";

// 13. Transactional Write Execution (Lead + Activity + Scoped Notification Outbox)
$insertedId = null;

try {
    $insertedId = DB::transaction(function($pdo) use (
        $leadNumber, $name, $formattedPhone, $email, $preferredContact, $validService, $serviceSlug,
        $propertyType, $budget, $message, $source, $landingPage, $referrer, $utmJson,
        $consentStatus, $now, $ipHash, $userAgent, $idempotencyKey, $payloadHash
    ) {
        // A. Insert Primary Lead with Idempotency Key
        $stmt = $pdo->prepare(
            "INSERT INTO `leads` 
             (`lead_number`, `name`, `phone`, `email`, `preferred_contact_method`, `service_requested`, 
              `property_type`, `estimated_budget`, `project_description`, `status`, `priority`, 
              `source`, `landing_page`, `referrer`, `utm_params`, `consent_status`, `consent_timestamp`, `submission_timestamp`, 
              `ip_hash`, `idempotency_key`, `payload_hash`, `user_agent`, `created_at`) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'medium', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $leadNumber, $name, $formattedPhone, $email, $preferredContact, $validService,
            $propertyType, $budget, $message, $source, $landingPage, $referrer, $utmJson,
            $consentStatus, $now, $now, $ipHash, $idempotencyKey, $payloadHash, $userAgent, $now
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

        // C. Queue into durable notification_outbox for active recipients matching routing rules
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

            $queuedEmails = [];

            foreach ($recipients as $rcpt) {
                $rcptEmail = strtolower(trim((string)$rcpt['email']));
                if (empty($rcptEmail) || isset($queuedEmails[$rcptEmail])) {
                    continue;
                }

                // Check service filter against both human title and normalized slug
                if (!empty($rcpt['service_filter'])) {
                    $sf = trim($rcpt['service_filter']);
                    $matchesService = (stripos($validService, $sf) !== false) || (stripos($serviceSlug, $sf) !== false);
                    if (!$matchesService) {
                        continue;
                    }
                }

                // Check source filter
                if (!empty($rcpt['source_filter'])) {
                    $srcF = trim($rcpt['source_filter']);
                    if (stripos($source, $srcF) === false) {
                        continue;
                    }
                }

                $queuedEmails[$rcptEmail] = true;
                $outboxIdempotencyKey = hash('sha256', "lead_{$leadId}_rcpt_{$rcpt['id']}_" . date('Ymd_H'));
                $rcptType = in_array($rcpt['recipient_type'] ?? '', ['to', 'cc', 'bcc']) ? $rcpt['recipient_type'] : 'to';
                
                $outboxStmt->execute([
                    $leadId,
                    $rcpt['email'],
                    $rcpt['name'],
                    $rcptType,
                    $emailSubject,
                    $emailHtml,
                    $emailPlain,
                    $outboxIdempotencyKey
                ]);
            }
        }

        return $leadId;
    });

} catch (Throwable $t) {
    error_log('[Lead Ingestion Transaction Failure] ' . $t->getMessage());

    if ($t instanceof PDOException && (int)($t->errorInfo[1] ?? 0) === 1062) {
        try {
            $existing = DB::query("SELECT * FROM leads WHERE idempotency_key = ? LIMIT 1", [$idempotencyKey])->fetch();
            if ($existing) lead_replay_response($existing, $payloadHash, $serviceSlug);
        } catch (Throwable $lookupError) { error_log('[Lead race lookup failed] ' . get_class($lookupError)); }
    }

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

// 14. Return Verified Success Response (Zero PII, normalized service identifier)
http_response_code(201);
echo json_encode([
    'success'       => true,
    'lead_id'       => $leadNumber,
    'service_id'    => $serviceSlug,
    'service'       => $validService,
    'is_duplicate'  => false,
    'message'       => 'Thank you! Your inquiry has been received. Our site supervisor will contact you shortly.',
    'contact_pref'  => $preferredContact
]);
exit;
