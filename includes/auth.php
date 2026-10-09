<?php
/**
 * Authentication & Access Control System - Tabeeb Contractor
 * 
 * Provides session lifecycle management, rate-limited login,
 * password hashing (Argon2id/Bcrypt), role-based permissions,
 * and last-super-admin safeguards.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

/**
 * 1. SECURE SESSION INITIALIZATION
 */
function init_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    // Enforce idle timeout and absolute session lifetime
    if (!empty($_SESSION['tabeeb_admin_user'])) {
        $now = time();
        $createdAt = $_SESSION['session_created_at'] ?? $now;
        $lastActivity = $_SESSION['session_last_activity'] ?? $now;

        if (($now - $createdAt) > SESSION_LIFETIME || ($now - $lastActivity) > SESSION_IDLE_TIMEOUT) {
            logout_user('Session expired due to inactivity. Please sign in again.');
            header('Location: /admin/login.php?msg=' . urlencode('Session expired. Please sign in again.'));
            exit;
        }

        $_SESSION['session_last_activity'] = $now;
    }
}

// Auto-run session setup
init_secure_session();

/**
 * 2. AUTHENTICATION (Login / Logout)
 */
function login_user(string $username, string $password): array {
    $cleanUsername = strtolower(trim($username));

    // Rate limiting check
    $rateCheck = check_rate_limit('admin_login', MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME);
    if (!$rateCheck['allowed']) {
        audit_log('auth.rate_limited', 'user', null, null, ['username' => $cleanUsername]);
        return ['success' => false, 'message' => $rateCheck['message']];
    }

    $user = null;
    if (DB::isConnected()) {
        $user = db_one(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name 
             FROM `users` u 
             JOIN `roles` r ON u.role_id = r.id 
             WHERE (LOWER(u.username) = ? OR LOWER(u.email) = ?) 
               AND u.deleted_at IS NULL 
             LIMIT 1",
            [$cleanUsername, $cleanUsername]
        );
    } else {
        // Fallback to JSON user storage if DB is migrating
        $jsonUsers = @file_get_contents(DATA_DIR . '/users.json');
        if ($jsonUsers) {
            $all = json_decode($jsonUsers, true) ?: [];
            foreach ($all as $u) {
                if (strtolower($u['username']) === $cleanUsername || strtolower($u['email']) === $cleanUsername) {
                    $user = $u;
                    $user['password_hash'] = $u['password'];
                    $user['role_slug'] = $u['role'];
                    $user['role_name'] = ucfirst(str_replace('_', ' ', $u['role']));
                    break;
                }
            }
        }
    }

    if (!$user) {
        audit_log('auth.login_failed', 'user', null, null, ['username' => $cleanUsername, 'reason' => 'user_not_found']);
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    // Check account status
    if (isset($user['status']) && $user['status'] !== 'active') {
        audit_log('auth.login_denied', 'user', (string)$user['id'], null, ['status' => $user['status']]);
        return ['success' => false, 'message' => 'Account is deactivated. Please contact an administrator.'];
    }

    // Check lockout timestamp
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        $waitMins = ceil((strtotime($user['locked_until']) - time()) / 60);
        return ['success' => false, 'message' => "Account is temporarily locked. Try again in {$waitMins} minute(s)."];
    }

    // Verify password hash
    $hash = $user['password_hash'] ?? $user['password'] ?? '';
    if (!password_verify($password, $hash)) {
        if (DB::isConnected()) {
            $newFailed = ((int)$user['failed_login_count']) + 1;
            $lockedUntil = null;
            if ($newFailed >= MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
            }
            db_exec("UPDATE `users` SET `failed_login_count` = ?, `locked_until` = ? WHERE `id` = ?", [$newFailed, $lockedUntil, $user['id']]);
        }
        audit_log('auth.login_failed', 'user', (string)$user['id'], null, ['username' => $cleanUsername, 'reason' => 'invalid_password']);
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    // Successful Login: Rotate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['tabeeb_admin_user'] = [
        'id'        => $user['id'],
        'name'      => $user['name'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'role_id'   => $user['role_id'] ?? 1,
        'role'      => $user['role_slug'] ?? 'super_admin',
        'role_name' => $user['role_name'] ?? 'Super Administrator',
        'force_password_change' => !empty($user['force_password_change'])
    ];
    $_SESSION['session_created_at'] = time();
    $_SESSION['session_last_activity'] = time();

    // Reset failed counter & record login timestamp
    if (DB::isConnected()) {
        db_exec(
            "UPDATE `users` SET `failed_login_count` = 0, `locked_until` = NULL, `last_login_at` = NOW() WHERE `id` = ?",
            [$user['id']]
        );

        // Rehash password if stronger algorithm is now available
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        if (password_needs_rehash($hash, $algo)) {
            $newHash = password_hash($password, $algo);
            db_exec("UPDATE `users` SET `password_hash` = ? WHERE `id` = ?", [$newHash, $user['id']]);
        }
    }

    audit_log('auth.login_success', 'user', (string)$user['id'], null, ['username' => $cleanUsername]);

    return ['success' => true, 'user' => $_SESSION['tabeeb_admin_user']];
}

function logout_user(?string $reason = null): void {
    if (!empty($_SESSION['tabeeb_admin_user'])) {
        audit_log('auth.logout', 'user', (string)$_SESSION['tabeeb_admin_user']['id'], null, ['reason' => $reason]);
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
}

/**
 * 3. CURRENT USER & ACCESS CHECKS
 */
function is_logged_in(): bool {
    return !empty($_SESSION['tabeeb_admin_user']);
}

function current_user(): ?array {
    return $_SESSION['tabeeb_admin_user'] ?? null;
}

function is_super_admin(): bool {
    $user = current_user();
    return $user && ($user['role'] === 'super_admin');
}

function has_permission(string $permSlug): bool {
    if (!is_logged_in()) {
        return false;
    }
    // Super admins have all permissions implicitly
    if (is_super_admin()) {
        return true;
    }

    $roleId = current_user()['role_id'] ?? 0;
    if (DB::isConnected() && $roleId > 0) {
        $match = db_val(
            "SELECT 1 FROM `role_permissions` rp 
             JOIN `permissions` p ON rp.permission_id = p.id 
             WHERE rp.role_id = ? AND p.slug = ? LIMIT 1",
            [$roleId, $permSlug]
        );
        return (bool)$match;
    }

    return false;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function require_super_admin(): void {
    require_login();
    if (!is_super_admin()) {
        header('Location: /admin/index.php?error=' . urlencode('Access Denied: Super Administrator privileges are required.'));
        exit;
    }
}

function require_permission(string $permSlug): void {
    require_login();
    if (!has_permission($permSlug)) {
        http_response_code(403);
        include dirname(__DIR__) . '/admin/header.php';
        echo '<div style="padding: 40px; text-align: center;"><h2>403 Forbidden</h2><p>You do not have permission (' . htmlspecialchars($permSlug) . ') to access this action.</p><a href="/admin/index.php" class="btn-adm btn-adm-primary">Return to Dashboard</a></div>';
        include dirname(__DIR__) . '/admin/footer.php';
        exit;
    }
}

/**
 * 4. LAST SUPER ADMIN SAFEGUARD
 */
function count_active_super_admins(): int {
    if (DB::isConnected()) {
        return (int)db_val(
            "SELECT COUNT(*) FROM `users` u 
             JOIN `roles` r ON u.role_id = r.id 
             WHERE r.slug = 'super_admin' AND u.status = 'active' AND u.deleted_at IS NULL"
        );
    }
    return 1;
}

function can_deactivate_or_delete_user(int $targetUserId): bool {
    if (DB::isConnected()) {
        $user = db_one(
            "SELECT u.id, u.status, r.slug AS role_slug FROM `users` u 
             JOIN `roles` r ON u.role_id = r.id 
             WHERE u.id = ? LIMIT 1",
            [$targetUserId]
        );
        if ($user && $user['role_slug'] === 'super_admin') {
            if (count_active_super_admins() <= 1) {
                return false;
            }
        }
    }
    return true;
}
