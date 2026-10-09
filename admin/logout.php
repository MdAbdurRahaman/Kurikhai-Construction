<?php
require_once dirname(__DIR__) . '/includes/data.php';

// If POST request, verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        // Still logout safely, but log warning
        error_log('[Security Warning] Logout called with invalid CSRF token.');
    }
}

logout_user('User initiated sign out');
header('Location: login.php?msg=' . urlencode('You have been signed out safely.'));
exit;
