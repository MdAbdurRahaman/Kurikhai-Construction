<?php
/**
 * Application Configuration - Tabeeb Contractor
 * 
 * Supports environment variables, protected .env loading, and local defaults.
 * Timezone: Asia/Singapore.
 */

// Timezone requirement: Asia/Singapore
date_default_timezone_set('Asia/Singapore');

// Helper to load protected .env file if present
(function() {
    $envPaths = [
        dirname(__DIR__) . '/.env',
        dirname(__DIR__, 2) . '/.env'
    ];
    foreach ($envPaths as $envPath) {
        if (file_exists($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $val] = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val);
                    // Remove surrounding single or double quotes
                    if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                        (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                        $val = substr($val, 1, -1);
                    }
                    if (!isset($_SERVER[$key]) && !isset($_ENV[$key])) {
                        putenv("{$key}={$val}");
                        $_ENV[$key] = $val;
                        $_SERVER[$key] = $val;
                    }
                }
            }
            break;
        }
    }
})();

// Autoload Composer dependencies (PHPMailer, HTMLPurifier)
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Helper to read env with fallback
if (!function_exists('app_env')) {
    function app_env(string $key, $default = null) {
        $val = getenv($key);
        if ($val === false || $val === null || $val === '') {
            return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        return $val;
    }
}

// Application Environment
define('APP_ENV', app_env('APP_ENV', 'production'));
define('APP_DEBUG', app_env('APP_DEBUG') === 'true' || APP_ENV === 'development');

// Base URL / Canonical Origin (Singapore Market)
define('APP_URL', rtrim((string)app_env('APP_URL', 'https://tabeebgroup.com'), '/'));
define('APP_NAME', 'Tabeeb Contractor');

// Cryptographic / HMAC secret for rate limiting & IP tokenization
$defaultHmac = (function() {
    $secretFile = dirname(__DIR__) . '/data/.secret_key';
    if (file_exists($secretFile) && is_readable($secretFile)) {
        $secret = trim(file_get_contents($secretFile));
        if (!empty($secret)) return $secret;
    }
    if (is_dir(dirname(__DIR__) . '/data') && is_writable(dirname(__DIR__) . '/data')) {
        $gen = bin2hex(random_bytes(32));
        @file_put_contents($secretFile, $gen);
        return $gen;
    }
    return 'tabeeb_secure_hmac_key_2026_sg_prod_k92817x';
})();
define('HMAC_SECRET', (string)app_env('HMAC_SECRET', $defaultHmac));

// Trusted reverse proxy IP addresses (comma separated)
define('TRUSTED_PROXIES', (string)app_env('TRUSTED_PROXIES', '127.0.0.1,::1'));

// Database Configuration
define('DB_HOST', (string)app_env('DB_HOST', 'localhost'));
define('DB_PORT', (string)app_env('DB_PORT', '3306'));
define('DB_NAME', (string)app_env('DB_NAME', 'tabeebgroup_portal'));
define('DB_USER', (string)app_env('DB_USER', 'tabeebgroup_dbuser'));
define('DB_PASS', (string)app_env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// Mail / SMTP Configuration (Protected credentials)
define('SMTP_HOST', (string)app_env('SMTP_HOST', 'mail.tabeebgroup.com'));
define('SMTP_PORT', (int)app_env('SMTP_PORT', 465));
define('SMTP_SECURE', (string)app_env('SMTP_SECURE', 'ssl')); // 'ssl', 'tls', or 'none'
define('SMTP_USER', (string)app_env('SMTP_USER', 'info@tabeebgroup.com'));
define('SMTP_PASS', (string)app_env('SMTP_PASS', ''));
define('SMTP_FROM_EMAIL', (string)app_env('SMTP_FROM_EMAIL', 'info@tabeebgroup.com'));
define('SMTP_FROM_NAME', (string)app_env('SMTP_FROM_NAME', 'Tabeeb Contractor Leads'));

// Security & Session Settings
define('SESSION_LIFETIME', 86400 * 3); // 3 days maximum session
define('SESSION_IDLE_TIMEOUT', 3600 * 2); // 2 hours idle timeout
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes lockout

// Directory Paths
define('ROOT_DIR', dirname(__DIR__));
define('DATA_DIR', ROOT_DIR . '/data');
define('UPLOADS_DIR', ROOT_DIR . '/images/blog');

// Error reporting policy
if (!APP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
