<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Strict enforcement: Reject GET requests with HTTP 405
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=UTF-8');
    echo "405 Method Not Allowed: Logout requires a secure POST request.\n";
    exit;
}

// Strict enforcement: Reject invalid or missing CSRF tokens with HTTP 403
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "403 Forbidden: Invalid or expired security token for logout.\n";
    exit;
}

// Perform safe logout
logout_user('User initiated sign out');
header('Location: login.php?msg=' . urlencode('You have been signed out safely.'));
exit;
