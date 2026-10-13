<?php
/**
 * Application Configuration - Tabeeb Contractor
 *
 * Supports environment variables, protected .env loading, and local defaults.
 * Timezone: Asia/Singapore.
 */

// Timezone requirement: Asia/Singapore
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Singapore');

// Load only this application's explicitly provisioned configuration. Never inherit a parent .env.
(function() {
    $path = getenv('APP_ENV_FILE') ?: dirname(__DIR__) . '/.env';
    if (getenv('APP_ENV_FILE') && (!is_file($path) || !is_readable($path))) {
        throw new RuntimeException('The configured environment file is not readable.');
    }
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) continue;
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) $value = substr($value, 1, -1);
        if (getenv($key) === false && !isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            putenv($key . '=' . $value); $_ENV[$key] = $value;
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
define('APP_ENV', (string)app_env('APP_ENV', 'production'));
if (!in_array(APP_ENV, ['production', 'staging', 'development', 'testing'], true)) throw new RuntimeException('Invalid application environment.');
define('APP_DEBUG', app_env('APP_DEBUG') === 'true' || APP_ENV === 'development');

// Base URL / Canonical Origin (Singapore Market)
define('APP_URL', rtrim((string)app_env('APP_URL', 'https://tabeebgroup.com'), '/'));
define('APP_NAME', 'Tabeeb Contractor');

// Provision 32 random bytes (64 hex characters). No shared or runtime-generated secrets.
$configuredHmac = (string)app_env('HMAC_SECRET', '');
if (strlen($configuredHmac) < 32 || preg_match('/(replace|change.?me|your.*secret|placeholder)/i', $configuredHmac)) {
    throw new RuntimeException('Provision a strong HMAC_SECRET before starting the application.');
}
define('HMAC_SECRET', $configuredHmac);
define('MAIL_SIMULATE', APP_ENV !== 'production');

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
define('DATA_DIR', (string)app_env('DATA_DIR', ROOT_DIR . '/data'));
define('BOOTSTRAP_DIR', ROOT_DIR . '/config/bootstrap');
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

// Validate environment boundaries before allowing database or provider access.
$url = parse_url(APP_URL);
if (!$url || empty($url['host']) || !empty($url['user']) || !empty($url['pass']) || !empty($url['query']) || !empty($url['fragment']) || !empty($url['path'])) throw new RuntimeException('APP_URL must be an origin without a path.');
if (in_array(APP_ENV, ['production', 'staging'], true) && ($url['scheme'] ?? '') !== 'https') throw new RuntimeException('HTTPS is required.');
if (APP_ENV === 'production' && APP_URL !== 'https://tabeebgroup.com') throw new RuntimeException('Unexpected production origin.');
if (APP_ENV === 'staging') {
    $productionDb = (string)app_env('PRODUCTION_DB_ID', '');
    if (in_array(strtolower($url['host']), ['tabeebgroup.com', 'www.tabeebgroup.com'], true) || $productionDb === '' || $productionDb === DB_HOST . ':' . DB_PORT . '/' . DB_NAME || DB_NAME === substr($productionDb, strrpos($productionDb, '/') + 1) || DB_NAME === 'tabeebgroup_portal') throw new RuntimeException('Staging must use an isolated origin and database.');
}
if (APP_ENV === 'testing' && !str_starts_with(DB_NAME, 'tabeeb_test_')) throw new RuntimeException('Tests require a disposable tabeeb_test_ database.');
if (PHP_SAPI !== 'cli') {
    if (in_array(APP_ENV, ['production','staging'],true)) {
        $host = strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
        $configuredHost = strtolower(parse_url(APP_URL,PHP_URL_HOST));
        if ($host !== $configuredHost) { http_response_code(503); exit('Application host does not match its configured environment.'); }
    }
    header('Cache-Control: private, no-store');
    header('X-Tabeeb-Release: ' . basename(ROOT_DIR));
    if (APP_ENV !== 'production') header('X-Robots-Tag: noindex, nofollow, noarchive');
    if (APP_ENV === 'staging') {
        if (!isset($_SERVER['PHP_AUTH_USER']) && preg_match('/^Basic (.+)$/i',(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??''),$authMatch)) {
            $decodedAuth=base64_decode($authMatch[1],true);
            if($decodedAuth!==false && str_contains($decodedAuth,':')) { [$_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']]=explode(':',$decodedAuth,2); }
        }
        $user = (string)app_env('STAGING_AUTH_USER', ''); $hash = (string)app_env('STAGING_AUTH_HASH', '');
        if ($user === '' || $hash === '') { http_response_code(503); exit('Staging access must be configured.'); }
        if (!hash_equals($user, (string)($_SERVER['PHP_AUTH_USER'] ?? '')) || !password_verify((string)($_SERVER['PHP_AUTH_PW'] ?? ''), $hash)) {
            header('WWW-Authenticate: Basic realm="Private staging"'); http_response_code(401); exit('Authentication required.');
        }
    }
}
function secure_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_only_cookies', '1'); ini_set('session.use_strict_mode', '1');
    session_name('tabeeb_' . APP_ENV);
    session_set_cookie_params(['lifetime'=>SESSION_LIFETIME, 'path'=>'/', 'secure'=>str_starts_with(APP_URL, 'https://'), 'httponly'=>true, 'samesite'=>'Lax']);
    session_start();
}
