<?php
/**
 * Application Configuration - Tabeeb Contractor
 * 
 * Supports environment variables or default local credentials.
 * Production credentials should be provided via server environment or protected .env
 */

// Timezone requirement: Asia/Singapore
date_default_timezone_set('Asia/Singapore');

// Application Environment
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // 'development' or 'production'
define('APP_DEBUG', getenv('APP_DEBUG') === 'true' || APP_ENV === 'development');

// Base URL / Canonical Origin (Singapore Market)
define('APP_URL', rtrim(getenv('APP_URL') ?: 'https://tabeebgroup.com', '/'));
define('APP_NAME', 'Tabeeb Contractor');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'tabeebgroup_portal');
define('DB_USER', getenv('DB_USER') ?: 'tabeebgroup_dbuser');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Mail / SMTP Configuration (Protected credentials)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'mail.tabeebgroup.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl'); // 'ssl', 'tls', or 'none'
define('SMTP_USER', getenv('SMTP_USER') ?: 'info@tabeebgroup.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'info@tabeebgroup.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Tabeeb Contractor Leads');

// Security & Session Settings
define('SESSION_LIFETIME', 86400 * 3); // 3 days maximum session
define('SESSION_IDLE_TIMEOUT', 3600 * 2); // 2 hours idle timeout
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes lockout

// Directory Paths
define('ROOT_DIR', dirname(__DIR__));
define('DATA_DIR', ROOT_DIR . '/data');
define('UPLOADS_DIR', ROOT_DIR . '/images/blog');

// Hide PHP error display in production
if (!APP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
