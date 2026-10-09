<?php
/**
 * Public Lead Ingestion API - Tabeeb Contractor
 * Endpoint: POST /api/leads
 * 
 * Replaces insecure Google Apps Script endpoint with a robust, same-origin,
 * rate-limited, anti-spam, MySQL-backed CRM ingestion handler.
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only POST is accepted.']);
    exit;
}

// 1. Rate Limiting Check (Max 5 submissions per 10 minutes per IP)
$rateLimit = check_rate_limit('public_lead_submission', 5, 600);
if (!$rateLimit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => $rateLimit['message'],
        'retry_after' => $rateLimit['retry_after']
    ]);
    exit;
}

// 2. Parse Incoming Payload (Supports both JSON and Standard FormData)
$rawBody = file_get_contents('php://input');
$data = [];

if (!empty($_POST)) {
    $data = $_POST;
} elseif (!empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

// 3. Anti-Spam Honeypot & Timing Delay Validation
$spamCheck = validate_honeypot_and_timing($data, 2);
if (!$spamCheck['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $spamCheck['reason']]);
    exit;
}

// 4. Server-Side Data Sanitization & Field Validation
$name = trim($data['name'] ?? '');
if (strlen($name) < 2 || strlen($name) > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid full name (2 to 100 characters).']);
    exit;
}

$rawPhone = trim($data['phone'] ?? '');
$phoneNorm = normalize_singapore_phone($rawPhone);
if (empty($rawPhone) || strlen($phoneNorm['digits']) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid phone number (minimum 8 digits).']);
    exit;
}
$formattedPhone = $phoneNorm['formatted'];

$email = trim($data['email'] ?? '');
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

$service = trim($data['service'] ?? 'General Renovation');
$service = substr($service, 0, 120);

$message = trim($data['message'] ?? $data['requirements'] ?? '');
if (empty($message)) {
    $message = 'General inquiry for ' . $service;
}
$message = substr($message, 0, 3000);

$budget = trim($data['budget'] ?? 'Unspecified');
$propertyType = trim($data['property_type'] ?? 'Unspecified');
$preferredContact = in_array($data['preferred_contact'] ?? '', ['phone', 'whatsapp', 'email']) ? $data['preferred_contact'] : 'whatsapp';
$source = trim($data['source'] ?? 'website_direct');
$landingPage = substr(trim($data['landing_page'] ?? $_SERVER['HTTP_REFERER'] ?? '/'), 0, 255);
$referrer = substr(trim($data['referrer'] ?? ''), 0, 255);

// 5. Generate Unique Human-Readable Lead Reference
$leadYear = date('Y');
$leadRandom = strtoupper(bin2hex(random_bytes(3))); // 6 alphanumeric chars
$leadNumber = "TBC-{$leadYear}-{$leadRandom}";

$ipHash = get_client_ip_hash();
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
$now = date('Y-m-d H:i:s');

// 6. Deduplication Check (Same phone and service within 10 minutes)
if (DB::isConnected()) {
    $duplicate = db_one(
        "SELECT `id`, `lead_number` FROM `leads` 
         WHERE `phone` = ? AND `service_requested` = ? AND `created_at` >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1",
        [$formattedPhone, $service]
    );

    if ($duplicate) {
        // Return existing lead reference without inserting duplicate spam
        echo json_encode([
            'success' => true,
            'lead_id' => $duplicate['lead_number'],
            'message' => 'Thank you! We already received your inquiry and our coordinator is reviewing it right now.',
            'is_duplicate' => true
        ]);
        exit;
    }
}

// 7. Store Lead in MySQL Database
$leadRecord = [
    'lead_number'         => $leadNumber,
    'name'                => $name,
    'phone'               => $formattedPhone,
    'email'               => $email ?: null,
    'preferred_contact_method' => $preferredContact,
    'service_requested'   => $service,
    'property_type'       => $propertyType,
    'project_location'    => null,
    'estimated_budget'    => $budget,
    'desired_commencement'=> null,
    'project_description' => $message,
    'status'              => 'new',
    'priority'            => 'medium',
    'quality'             => 'unrated',
    'source'              => $source,
    'landing_page'        => $landingPage,
    'referrer'            => $referrer,
    'consent_status'      => 1,
    'consent_timestamp'   => $now,
    'submission_timestamp'=> $now,
    'ip_hash'             => $ipHash,
    'user_agent'          => $userAgent
];

$savedSuccessfully = false;
$insertedId = null;

if (DB::isConnected()) {
    try {
        db_exec(
            "INSERT INTO `leads` 
             (`lead_number`, `name`, `phone`, `email`, `preferred_contact_method`, `service_requested`, 
              `property_type`, `estimated_budget`, `project_description`, `status`, `priority`, 
              `source`, `landing_page`, `referrer`, `consent_status`, `consent_timestamp`, `submission_timestamp`, 
              `ip_hash`, `user_agent`, `created_at`) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $leadRecord['lead_number'],
                $leadRecord['name'],
                $leadRecord['phone'],
                $leadRecord['email'],
                $leadRecord['preferred_contact_method'],
                $leadRecord['service_requested'],
                $leadRecord['property_type'],
                $leadRecord['estimated_budget'],
                $leadRecord['project_description'],
                $leadRecord['status'],
                $leadRecord['priority'],
                $leadRecord['source'],
                $leadRecord['landing_page'],
                $leadRecord['referrer'],
                $leadRecord['consent_status'],
                $leadRecord['consent_timestamp'],
                $leadRecord['submission_timestamp'],
                $leadRecord['ip_hash'],
                $leadRecord['user_agent'],
                $now
            ]
        );
        $insertedId = db()->lastInsertId();
        $leadRecord['id'] = $insertedId;

        // Log CRM creation activity
        db_exec(
            "INSERT INTO `lead_activities` (`lead_id`, `activity_type`, `description`, `created_at`) 
             VALUES (?, 'lead_received', 'Lead submitted via public website quote form', NOW())",
            [$insertedId]
        );

        $savedSuccessfully = true;
    } catch (PDOException $e) {
        error_log('[Lead Insertion Failure] ' . $e->getMessage());
        $savedSuccessfully = false;
    }
}

// Fallback: If database is temporarily unavailable, write safely to protected local queue file
if (!$savedSuccessfully) {
    $queueFile = DATA_DIR . '/leads_queue.json';
    $queue = read_json_file($queueFile, []);
    $leadRecord['id'] = 'q_' . time() . '_' . rand(100, 999);
    $queue[] = $leadRecord;
    write_json_file($queueFile, $queue);
    $savedSuccessfully = true;
}

// 8. Dispatch Notifications (Independent of DB insertion success)
if ($savedSuccessfully) {
    try {
        Mailer::dispatchLeadNotifications($leadRecord);
    } catch (Throwable $t) {
        error_log('[Lead Notification Dispatch Error] ' . $t->getMessage());
        // Do not fail the user's inquiry response because email dispatch failed
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'lead_id' => $leadNumber,
        'message' => 'Thank you! Your inquiry has been received. Our site supervisor will contact you shortly.'
    ]);
    exit;
}

// If all storage mechanisms failed
http_response_code(500);
echo json_encode([
    'success' => false,
    'message' => 'We could not process your inquiry at this moment. Please call or WhatsApp us directly at +65 8648 4883.'
]);
exit;
