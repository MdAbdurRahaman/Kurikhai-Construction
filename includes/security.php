<?php
/**
 * Application Security Suite - Tabeeb Contractor
 * 
 * Provides CSRF protection, HTML allowlist sanitization, phone normalization,
 * rate limiting, anti-spam honeypot, CSV formula escaping, and audit logging.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';

/**
 * 1. CSRF PROTECTION
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
 * 2. PRIVACY-CONSCIOUS IP HASHING
 */
function get_client_ip_hash(): string {
    $rawIp = $_SERVER['HTTP_CF_CONNECTING_IP']
          ?? $_SERVER['HTTP_X_FORWARDED_FOR']
          ?? $_SERVER['REMOTE_ADDR']
          ?? '127.0.0.1';
    
    // In multi-proxy setups, take the first IP
    if (strpos($rawIp, ',') !== false) {
        $parts = explode(',', $rawIp);
        $rawIp = trim($parts[0]);
    }

    // Hash with monthly rotating salt to comply with Singapore PDPA minimization
    $salt = date('Y-m') . '_tabeeb_sec_salt';
    return hash('sha256', $rawIp . $salt);
}

/**
 * 3. RATE LIMITING (Sliding window via Database with Session Fallback)
 */
function check_rate_limit(string $actionType, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $ipHash = get_client_ip_hash();
    $rateKey = $ipHash . ':' . $actionType;
    $now = date('Y-m-d H:i:s');

    if (DB::isConnected()) {
        // Purge expired limits
        db_exec("DELETE FROM `rate_limits` WHERE `reset_at` < ?", [$now]);

        $record = db_one(
            "SELECT `id`, `attempts`, `reset_at` FROM `rate_limits` WHERE `rate_key` = ? AND `action_type` = ? LIMIT 1",
            [$rateKey, $actionType]
        );

        if ($record) {
            if ($record['attempts'] >= $maxAttempts) {
                $retryAfter = max(1, strtotime($record['reset_at']) - time());
                return [
                    'allowed' => false,
                    'retry_after' => $retryAfter,
                    'message' => "Too many attempts. Please wait {$retryAfter} seconds before trying again."
                ];
            }
            // Increment
            db_exec("UPDATE `rate_limits` SET `attempts` = `attempts` + 1 WHERE `id` = ?", [$record['id']]);
            return ['allowed' => true, 'remaining' => $maxAttempts - ($record['attempts'] + 1)];
        } else {
            $resetAt = date('Y-m-d H:i:s', time() + $windowSeconds);
            db_exec(
                "INSERT INTO `rate_limits` (`rate_key`, `action_type`, `attempts`, `reset_at`) VALUES (?, ?, 1, ?)",
                [$rateKey, $actionType, $resetAt]
            );
            return ['allowed' => true, 'remaining' => $maxAttempts - 1];
        }
    }

    // Fallback to PHP session rate limiting if database is temporarily unavailable
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
            'allowed' => false,
            'retry_after' => $retryAfter,
            'message' => "Too many attempts. Please wait {$retryAfter} seconds before trying again."
        ];
    }

    return ['allowed' => true, 'remaining' => $maxAttempts - $history['attempts']];
}

/**
 * 4. SINGAPORE TELEPHONE NUMBER NORMALIZATION
 */
function normalize_singapore_phone(string $phone): array {
    $raw = trim($phone);
    // Remove all characters except digits and plus
    $clean = preg_replace('/[^\d+]/', '', $raw);

    // If starts with +65, strip +65 for inspection
    if (str_starts_with($clean, '+65')) {
        $digits = substr($clean, 3);
    } elseif (str_starts_with($clean, '65') && strlen($clean) === 10) {
        $digits = substr($clean, 2);
    } else {
        $digits = ltrim($clean, '+');
    }

    // Singapore standard phone numbers are 8 digits starting with 6, 8, or 9
    // (6 = landline/office, 8 or 9 = mobile / WhatsApp)
    $isValid = (bool)preg_match('/^[689]\d{7}$/', $digits);
    $formatted = $isValid ? '+65 ' . substr($digits, 0, 4) . ' ' . substr($digits, 4, 4) : $raw;

    return [
        'valid' => $isValid,
        'formatted' => $formatted,
        'digits' => $digits,
        'e164' => $isValid ? '+65' . $digits : $raw
    ];
}

/**
 * 5. ANTI-SPAM HONEYPOT & TIME DELAY DETECTION
 */
function validate_honeypot_and_timing(array $postData, int $minSeconds = 2): array {
    // Honeypot field must be empty
    $honeypot = $postData['website_url_hp'] ?? $postData['middle_name_hp'] ?? '';
    if (!empty(trim((string)$honeypot))) {
        return ['valid' => false, 'reason' => 'Bot activity detected (honeypot triggered).'];
    }

    // Minimum form completion time
    $timestamp = (int)($postData['form_loaded_ts'] ?? 0);
    if ($timestamp > 0) {
        $elapsed = time() - $timestamp;
        if ($elapsed < $minSeconds) {
            return ['valid' => false, 'reason' => 'Form submitted too rapidly. Please review your details.'];
        }
    }

    return ['valid' => true];
}

/**
 * 6. HTML ALLOWLIST SANITIZER (Prevents Stored XSS in Blog & CMS)
 */
function sanitize_html_content(string $dirtyHtml): string {
    if (empty(trim($dirtyHtml))) {
        return '';
    }

    // Strip dangerous tags completely including their contents
    $stripped = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $dirtyHtml);
    $stripped = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $stripped);
    $stripped = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $stripped);
    $stripped = preg_replace('/<object\b[^>]*>(.*?)<\/object>/is', '', $stripped);
    $stripped = preg_replace('/<embed\b[^>]*>(.*?)<\/embed>/is', '', $stripped);
    $stripped = preg_replace('/<applet\b[^>]*>(.*?)<\/applet>/is', '', $stripped);
    $stripped = preg_replace('/<form\b[^>]*>(.*?)<\/form>/is', '', $stripped);

    // List of allowed semantic HTML tags
    $allowedTags = '<h2><h3><h4><h5><h6><p><br><hr><strong><b><em><i><u><s>' .
                   '<ul><ol><li><blockquote><code><pre><div><span>' .
                   '<table><thead><tbody>tr<th><td><a><img>';

    $clean = strip_tags($stripped, $allowedTags);

    // Strip inline javascript: event handlers (onload, onerror, onclick, etc.)
    $clean = preg_replace('/\s*on[a-zA-Z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]*)/i', '', $clean);

    // Strip javascript: pseudo-protocols from href and src
    $clean = preg_replace('/(href|src)\s*=\s*["\']\s*javascript:[^"\']*["\']/i', '', $clean);

    // Ensure external links have safe attributes
    $clean = preg_replace('/<a\s+([^>]*href=["\']https?:\/\/[^"\']+["\'][^>]*)>/i', '<a $1 target="_blank" rel="noopener noreferrer">', $clean);

    return $clean;
}

/**
 * 7. CSV FORMULA INJECTION PREVENTION
 */
function escape_csv_cell($value): string {
    if ($value === null) {
        return '';
    }
    $str = (string)$value;
    $firstChar = substr($str, 0, 1);
    // If cell begins with dangerous formula prefixes, prepend a single quote
    if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $str;
    }
    return $str;
}

/**
 * 8. SECURE AUDIT LOGGING
 */
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

    $beforeJson = $beforeState !== null ? json_encode($beforeState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $afterJson = $afterState !== null ? json_encode($afterState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

    db_exec(
        "INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `before_state`, `after_state`, `ip_hash`, `user_agent`, `created_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [$userId, $action, $entityType, $entityId, $beforeJson, $afterJson, $ipHash, $userAgent]
    );
}

/**
 * 9. SECURE FILE UPLOAD HANDLER
 */
function secure_upload_image(array $fileInput, string $targetDir, int $maxBytes = 5242880): array {
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

    // Verify image decoding (prevents image-embedded script polyglots)
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        return ['success' => false, 'error' => 'Uploaded file is not a valid decipherable image.'];
    }

    // Ensure target directory exists and prevent direct script execution
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

    if (!move_uploaded_file($tmpPath, $destination)) {
        return ['success' => false, 'error' => 'Failed to save uploaded image. Check directory permissions.'];
    }

    @chmod($destination, 0644);

    return [
        'success' => true,
        'filename' => $safeName,
        'filepath' => $destination,
        'mime' => $mime,
        'width' => $imageInfo[0],
        'height' => $imageInfo[1]
    ];
}
