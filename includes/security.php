<?php
/**
 * Application Security Suite - Tabeeb Contractor
 * 
 * Provides:
 * 1. CSRF Protection & Same-Origin Verification
 * 2. Privacy-Conscious HMAC Client-IP Hashing (Trusted Proxy Aware)
 * 3. Concurrency-Safe Sliding Window Rate Limiting (Atomic UPSERT)
 * 4. Singapore Phone Number Normalization & Validation
 * 5. Anti-Spam Honeypot & Signed Form Timing Detection
 * 6. Parser-Based Allowlist HTML Sanitizer (HTMLPurifier)
 * 7. CSV Formula Injection Defense
 * 8. Redacted Audit Logging (Zero Leaked Hashes or Secrets)
 * 9. Re-encoding Secure Image Uploads (GD Pixel & Metadata Sanitization)
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';

/**
 * 1. CSRF PROTECTION & SAME-ORIGIN VERIFICATION
 */
function get_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify that the incoming request originates from the approved application host
 */
function verify_same_origin(): bool {
    $allowedHost = parse_url(APP_URL, PHP_URL_HOST);
    
    // Check Origin header first if present
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $originHost = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
        return $originHost !== null && (strcasecmp($originHost, $allowedHost) === 0 || $originHost === 'localhost' || $originHost === '127.0.0.1');
    }

    // Check Referer header
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        return $refererHost !== null && (strcasecmp($refererHost, $allowedHost) === 0 || $refererHost === 'localhost' || $refererHost === '127.0.0.1');
    }

    // If neither header is sent by modern browser on same-origin POST, check Sec-Fetch-Site
    if (!empty($_SERVER['HTTP_SEC_FETCH_SITE'])) {
        return in_array($_SERVER['HTTP_SEC_FETCH_SITE'], ['same-origin', 'same-site', 'none'], true);
    }

    return true; // Allow non-browser CLI or test calls
}

/**
 * 2. PRIVACY-CONSCIOUS HMAC CLIENT-IP HASHING (TRUSTED PROXIES ONLY)
 */
function get_client_ip_raw(): string {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $trustedProxies = array_map('trim', explode(',', TRUSTED_PROXIES));

    // Only inspect forwarded headers if the immediate connection is from a trusted reverse proxy
    if (in_array($remoteAddr, $trustedProxies, true) || $remoteAddr === '127.0.0.1' || $remoteAddr === '::1') {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
    }

    return $remoteAddr;
}

function get_client_ip_hash(): string {
    $ip = get_client_ip_raw();
    return hash_hmac('sha256', $ip, HMAC_SECRET);
}

/**
 * 3. CONCURRENCY-SAFE RATE LIMITING (Atomic SQL UPSERT)
 */
function check_rate_limit(string $actionType, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $ipHash = get_client_ip_hash();
    $rateKey = $ipHash . ':' . $actionType;
    $now = date('Y-m-d H:i:s');
    $resetAt = date('Y-m-d H:i:s', time() + $windowSeconds);

    if (DB::isConnected()) {
        try {
            // Atomic UPSERT avoids race conditions under concurrent requests
            db_exec(
                "INSERT INTO `rate_limits` (`rate_key`, `action_type`, `attempts`, `reset_at`) 
                 VALUES (?, ?, 1, ?) 
                 ON DUPLICATE KEY UPDATE 
                    `attempts` = IF(`reset_at` < NOW(), 1, `attempts` + 1),
                    `reset_at` = IF(`reset_at` < NOW(), VALUES(`reset_at`), `reset_at`)",
                [$rateKey, $actionType, $resetAt]
            );

            $record = db_one(
                "SELECT `attempts`, `reset_at` FROM `rate_limits` WHERE `rate_key` = ? AND `action_type` = ? LIMIT 1",
                [$rateKey, $actionType]
            );

            if ($record && (int)$record['attempts'] > $maxAttempts) {
                $retryAfter = max(1, strtotime($record['reset_at']) - time());
                return [
                    'allowed'     => false,
                    'retry_after' => $retryAfter,
                    'remaining'   => 0,
                    'message'     => "Too many attempts. Please wait {$retryAfter} seconds before trying again."
                ];
            }

            $currentAttempts = $record ? (int)$record['attempts'] : 1;
            return [
                'allowed'   => true,
                'remaining' => max(0, $maxAttempts - $currentAttempts)
            ];
        } catch (Throwable $t) {
            error_log('[Rate Limit DB Error] ' . $t->getMessage());
        }
    }

    // Fallback to PHP session rate limiting if DB is offline
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionKey = 'rate_' . $actionType;
    $history = $_SESSION[$sessionKey] ?? ['attempts' => 0, 'reset_at' => time() + $windowSeconds];

    if (time() > $history['reset_at']) {
        $history = ['attempts' => 1, 'reset_at' => time() + $windowSeconds];
    } else {
        $history['attempts']++;
    }
    $_SESSION[$sessionKey] = $history;

    if ($history['attempts'] > $maxAttempts) {
        $retryAfter = max(1, $history['reset_at'] - time());
        return [
            'allowed'     => false,
            'retry_after' => $retryAfter,
            'remaining'   => 0,
            'message'     => "Too many attempts. Please wait {$retryAfter} seconds before trying again."
        ];
    }

    return ['allowed' => true, 'remaining' => $maxAttempts - $history['attempts']];
}

/**
 * 4. SINGAPORE & INTERNATIONAL PHONE NUMBER NORMALIZATION & VALIDATION
 */
function normalize_singapore_phone(string $phone): array {
    $raw = trim($phone);
    $clean = preg_replace('/[^\d+]/', '', $raw);

    // Singapore specific normalization (+65 or 65 prefix)
    if (str_starts_with($clean, '+65')) {
        $digits = substr($clean, 3);
    } elseif (str_starts_with($clean, '65') && strlen($clean) === 10) {
        $digits = substr($clean, 2);
    } else {
        $digits = ltrim($clean, '+');
    }

    // Singapore standard phones: 8 digits starting with 3, 6, 8, or 9
    // (6 = landline/office, 8 or 9 = mobile/WhatsApp, 3 = IP telephony)
    $isSgValid = (bool)preg_match('/^[3689]\d{7}$/', $digits);
    if ($isSgValid) {
        return [
            'valid'     => true,
            'is_sg'     => true,
            'formatted' => '+65 ' . substr($digits, 0, 4) . ' ' . substr($digits, 4, 4),
            'digits'    => $digits,
            'e164'      => '+65' . $digits
        ];
    }

    // Explicit international validation (starts with + or 00, 7 to 15 digits according to E.164)
    $cleanDigits = ltrim($clean, '+');
    $isIntlValid = (str_starts_with($clean, '+') || str_starts_with($raw, '+')) && preg_match('/^\d{7,15}$/', $cleanDigits);
    if ($isIntlValid) {
        return [
            'valid'     => true,
            'is_sg'     => false,
            'formatted' => '+' . $cleanDigits,
            'digits'    => $cleanDigits,
            'e164'      => '+' . $cleanDigits
        ];
    }

    return [
        'valid'     => false,
        'is_sg'     => false,
        'formatted' => $raw,
        'digits'    => $cleanDigits,
        'e164'      => $raw
    ];
}

/**
 * 5. ANTI-SPAM HONEYPOT & SIGNED FORM TIMING DETECTION
 */
function generate_form_token(): string {
    $time = time();
    $hash = hash_hmac('sha256', (string)$time, HMAC_SECRET);
    return base64_encode("{$time}:{$hash}");
}

function validate_honeypot_and_timing(array $postData, int $minSeconds = 2): array {
    // 1. Honeypot check
    $honeypot = $postData['website_url_hp'] ?? $postData['middle_name_hp'] ?? '';
    if (!empty(trim((string)$honeypot))) {
        return ['valid' => false, 'reason' => 'Bot activity detected (honeypot triggered).'];
    }

    // 2. Cryptographically signed form load timestamp
    $token = $postData['form_loaded_ts'] ?? '';
    if (!empty($token)) {
        $decoded = base64_decode((string)$token, true);
        if ($decoded && str_contains($decoded, ':')) {
            [$ts, $hash] = explode(':', $decoded, 2);
            $ts = (int)$ts;
            $expectedHash = hash_hmac('sha256', (string)$ts, HMAC_SECRET);
            if (hash_equals($expectedHash, $hash)) {
                $elapsed = time() - $ts;
                if ($elapsed < $minSeconds) {
                    return ['valid' => false, 'reason' => 'Form submitted too rapidly. Please take your time to review details.'];
                }
                // Expire form after 12 hours
                if ($elapsed > 43200) {
                    return ['valid' => false, 'reason' => 'Form submission expired. Please refresh the page.'];
                }
            }
        }
    }

    return ['valid' => true];
}

/**
 * 6. PARSER-BASED ALLOWLIST HTML SANITIZER (HTMLPurifier)
 */
function sanitize_html_content(string $dirtyHtml): string {
    if (empty(trim($dirtyHtml))) {
        return '';
    }

    if (class_exists('HTMLPurifier')) {
        static $purifier = null;
        if ($purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $config->set('HTML.Allowed', 'h2,h3,h4,h5,h6,p,br,hr,strong,b,em,i,u,s,ul,ol,li,blockquote,code,pre,div[class],span[class],table,thead,tbody,tr,th,td,a[href|title|target|rel],img[src|alt|title|width|height]');
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            $config->set('HTML.TargetBlank', true);
            $config->set('HTML.Nofollow', false);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('Core.Encoding', 'UTF-8');
            $config->set('Cache.DefinitionImpl', null);
            $purifier = new HTMLPurifier($config);
        }
        return $purifier->purify($dirtyHtml);
    }

    // Safe fallback DOMDocument parser if purifier library missing
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $dirtyHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $allowedTags = ['h2','h3','h4','h5','h6','p','br','hr','strong','b','em','i','u','s','ul','ol','li','blockquote','code','pre','div','span','table','thead','tbody','tr','th','td','a','img'];
    $nodesToRemove = [];

    $allElements = $doc->getElementsByTagName('*');
    foreach ($allElements as $element) {
        $tag = strtolower($element->nodeName);
        if (!in_array($tag, $allowedTags, true)) {
            $nodesToRemove[] = $element;
            continue;
        }
        // Remove dangerous event handler attributes
        if ($element->hasAttributes()) {
            $attrsToRemove = [];
            foreach ($element->attributes as $attr) {
                $attrName = strtolower($attr->nodeName);
                if (str_starts_with($attrName, 'on') || in_array($attrName, ['style', 'form', 'action'], true)) {
                    $attrsToRemove[] = $attr->nodeName;
                }
                if (in_array($attrName, ['href', 'src'], true)) {
                    $val = strtolower(trim($attr->nodeValue));
                    if (str_starts_with($val, 'javascript:') || str_starts_with($val, 'data:') || str_starts_with($val, 'vbscript:')) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                }
            }
            foreach ($attrsToRemove as $rem) {
                $element->removeAttribute($rem);
            }
        }
    }

    foreach ($nodesToRemove as $node) {
        $node->parentNode->removeChild($node);
    }

    return $doc->saveHTML();
}

/**
 * 7. CSV FORMULA INJECTION DEFENSE
 */
function escape_csv_cell($value): string {
    if ($value === null) {
        return '';
    }
    $str = (string)$value;
    $firstChar = substr($str, 0, 1);
    if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $str;
    }
    return $str;
}

/**
 * 8. REDACTED AUDIT LOGGING (Zero Leaked Hashes or Secrets)
 */
function redact_sensitive_audit_data($data) {
    if (!is_array($data)) {
        return $data;
    }
    $redacted = [];
    $sensitiveKeys = [
        'password', 'password_hash', 'password_verify', 'token', 'secret',
        'two_factor_secret', 'two_factor_secret_encrypted', 'smtp_pass', 'db_pass'
    ];

    foreach ($data as $key => $val) {
        $lowerKey = strtolower((string)$key);
        if (in_array($lowerKey, $sensitiveKeys, true)) {
            $redacted[$key] = '[REDACTED]';
        } elseif (is_array($val)) {
            $redacted[$key] = redact_sensitive_audit_data($val);
        } else {
            $redacted[$key] = $val;
        }
    }
    return $redacted;
}

function audit_log(
    string $action,
    string $entityType,
    ?string $entityId = null,
    ?array $beforeState = null,
    ?array $afterState = null
): void {
    if (!DB::isConnected()) {
        return;
    }

    $userId = $_SESSION['tabeeb_admin_user']['id'] ?? null;
    $ipHash = get_client_ip_hash();
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

    $cleanBefore = $beforeState !== null ? redact_sensitive_audit_data($beforeState) : null;
    $cleanAfter = $afterState !== null ? redact_sensitive_audit_data($afterState) : null;

    $beforeJson = $cleanBefore !== null ? json_encode($cleanBefore, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $afterJson = $cleanAfter !== null ? json_encode($cleanAfter, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

    try {
        db_exec(
            "INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `before_state`, `after_state`, `ip_hash`, `user_agent`, `created_at`) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$userId, $action, $entityType, $entityId, $beforeJson, $afterJson, $ipHash, $userAgent]
        );
    } catch (Throwable $t) {
        error_log('[Audit Log Error] ' . $t->getMessage());
    }
}

/**
 * 9. RE-ENCODING SECURE FILE UPLOAD HANDLER (GD Pixel & Metadata Sanitization)
 */
function secure_upload_image(array $fileInput, string $targetDir, int $maxBytes = 5242880, int $maxDimension = 3840): array {
    if ($fileInput['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $fileInput['error']];
    }

    if ($fileInput['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'File size exceeds maximum limit of ' . round($maxBytes / 1048576) . 'MB.'];
    }

    $tmpPath = $fileInput['tmp_name'];

    // Verify MIME type via finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedMimes[$mime])) {
        return ['success' => false, 'error' => 'Invalid file format. Only JPG, PNG, and WebP images are permitted.'];
    }

    // Inspect image dimensions via getimagesize
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        return ['success' => false, 'error' => 'Uploaded file is not a valid decipherable image.'];
    }

    $width = $imageInfo[0];
    $height = $imageInfo[1];

    if ($width > $maxDimension || $height > $maxDimension) {
        return ['success' => false, 'error' => "Image dimensions exceed the allowed limit ({$maxDimension}px)."];
    }

    // Genuine re-encoding via GD: strips all EXIF/embedded metadata and polyglots
    $sourceImage = null;
    switch ($mime) {
        case 'image/jpeg':
            $sourceImage = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $sourceImage = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $sourceImage = @imagecreatefromwebp($tmpPath);
            break;
    }

    if (!$sourceImage) {
        return ['success' => false, 'error' => 'Image decode failed during sanitization.'];
    }

    // Ensure target directory exists and prevent PHP execution
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    $htaccessFile = rtrim($targetDir, '/\\') . '/.htaccess';
    if (!file_exists($htaccessFile)) {
        @file_put_contents($htaccessFile, "# Disable PHP execution in uploads directory\n<FilesMatch \"\.(php|phtml|php3|php4|php5|php7|phps|phar)$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>\n");
    }

    $extension = $allowedMimes[$mime];
    $safeName = 'upload_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destination = rtrim($targetDir, '/\\') . '/' . $safeName;

    $saved = false;
    switch ($extension) {
        case 'jpg':
            $saved = imagejpeg($sourceImage, $destination, 88);
            break;
        case 'png':
            imagealphablending($sourceImage, false);
            imagesavealpha($sourceImage, true);
            $saved = imagepng($sourceImage, $destination, 8);
            break;
        case 'webp':
            imagepalettetotruecolor($sourceImage);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
            $saved = imagewebp($sourceImage, $destination, 85);
            break;
    }
    imagedestroy($sourceImage);

    if (!$saved) {
        return ['success' => false, 'error' => 'Failed to write re-encoded image.'];
    }

    @chmod($destination, 0644);

    return [
        'success'   => true,
        'filename'  => $safeName,
        'filepath'  => $destination,
        'mime'      => $mime,
        'width'     => $width,
        'height'    => $height
    ];
}
