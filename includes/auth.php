<?php
/**
 * Authentication & Access Control System - Tabeeb Contractor
 *
 * Provides:
 * 1. Secure Session Lifecycle & Idle/Absolute Timeout Management
 * 2. Fail-Closed Authentication (Strictly MySQL Backed, Zero JSON Identity Fallback)
 * 3. Active Account & Session Version Revalidation on Every Request
 * 4. Mandatory Enforced Password Rotation (force_password_change)
 * 5. Strict Role-Based Permissions (RBAC) across 6 granular roles
 * 6. Concurrency-Safe Final Super Admin Safeguard
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

/**
 * 1. SECURE SESSION INITIALIZATION & LIFECYCLE
 */
function init_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    secure_session_start();

    // Check session timeout & database account status
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

        // Revalidate account status against database (Fail-Closed)
        revalidate_active_session();
    }
}

/**
 * 2. ACTIVE SESSION REVALIDATION (Fail-Closed)
 */
function revalidate_active_session(): bool {
    if (empty($_SESSION['tabeeb_admin_user']['id'])) {
        return false;
    }

    $userId = (int)$_SESSION['tabeeb_admin_user']['id'];
    $sessionVer = (int)($_SESSION['tabeeb_admin_user']['session_version'] ?? 1);

    // If database is unavailable, fail closed
    if (!DB::isConnected()) {
        logout_user('Database connection unavailable for session validation.');
        if (php_sapi_name() !== 'cli') {
            header('Location: /admin/login.php?error=' . urlencode('Authentication database is temporarily unavailable. Please try again shortly.'));
            exit;
        }
        return false;
    }

    try {
        $user = db_one(
            "SELECT u.id, u.status, u.role_id, u.force_password_change, u.session_version, u.deleted_at,
                    r.slug AS role_slug, r.name AS role_name
             FROM `users` u
             JOIN `roles` r ON u.role_id = r.id
             WHERE u.id = ? LIMIT 1",
            [$userId]
        );

        if (!$user || $user['status'] !== 'active' || !empty($user['deleted_at'])) {
            logout_user('Account deactivated, suspended, or deleted.');
            if (php_sapi_name() !== 'cli') {
                header('Location: /admin/login.php?error=' . urlencode('Your account has been deactivated or removed.'));
                exit;
            }
            return false;
        }

        // Session version mismatch (triggered by password change or privilege revocation)
        $dbVer = (int)($user['session_version'] ?? 1);
        if ($dbVer !== $sessionVer) {
            logout_user('Session revoked due to password or privilege change.');
            if (php_sapi_name() !== 'cli') {
                header('Location: /admin/login.php?msg=' . urlencode('Your session was revoked due to a password or role update. Please sign in again.'));
                exit;
            }
            return false;
        }

        // Synchronize updated role and flags in active session
        $_SESSION['tabeeb_admin_user']['role_id'] = (int)$user['role_id'];
        $_SESSION['tabeeb_admin_user']['role'] = $user['role_slug'];
        $_SESSION['tabeeb_admin_user']['role_name'] = $user['role_name'];
        $_SESSION['tabeeb_admin_user']['force_password_change'] = !empty($user['force_password_change']);
        return true;
    } catch (Throwable $t) {
        error_log('[Session Revalidation Error] ' . $t->getMessage());
        logout_user('Session validation error.');
        if (php_sapi_name() !== 'cli') {
            header('Location: /admin/login.php?error=' . urlencode('Session validation error. Please sign in again.'));
            exit;
        }
        return false;
    }
}

// Auto-run session lifecycle on include
init_secure_session();

/**
 * 3. FAIL-CLOSED AUTHENTICATION (Login / Logout)
 */
function login_user(string $username, string $password): array {
    $cleanUsername = strtolower(trim($username));

    // Rate limiting check
    $rateCheck = check_rate_limit('admin_login', MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME);
    if (!$rateCheck['allowed']) {
        audit_log('auth.rate_limited', 'user', null, null, ['username' => $cleanUsername]);
        return ['success' => false, 'message' => $rateCheck['message']];
    }

    // Fail closed: database connection must be available
    if (!DB::isConnected()) {
        return [
            'success' => false,
            'message' => 'Authentication service is temporarily unavailable. Database connection could not be established.'
        ];
    }

    try {
        $user = db_one(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM `users` u
             JOIN `roles` r ON u.role_id = r.id
             WHERE (LOWER(u.username) = ? OR LOWER(u.email) = ?)
               AND u.deleted_at IS NULL
             LIMIT 1",
            [$cleanUsername, $cleanUsername]
        );

        if (!$user) {
            audit_log('auth.login_failed', 'user', null, null, ['username' => $cleanUsername, 'reason' => 'user_not_found']);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Account active check
        if ($user['status'] !== 'active') {
            audit_log('auth.login_denied', 'user', (string)$user['id'], null, ['status' => $user['status']]);
            return ['success' => false, 'message' => 'Account is deactivated. Please contact an administrator.'];
        }

        // Lockout timestamp check
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $waitMins = ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['success' => false, 'message' => "Account is temporarily locked. Try again in {$waitMins} minute(s)."];
        }

        // Verify password hash
        $hash = $user['password_hash'] ?? '';
        if (!password_verify($password, $hash)) {
            $newFailed = ((int)$user['failed_login_count']) + 1;
            $lockedUntil = null;
            if ($newFailed >= MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
            }
            db_exec("UPDATE `users` SET `failed_login_count` = ?, `locked_until` = ? WHERE `id` = ?", [$newFailed, $lockedUntil, $user['id']]);
            audit_log('auth.login_failed', 'user', (string)$user['id'], null, ['username' => $cleanUsername, 'reason' => 'invalid_password']);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Rotate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['tabeeb_admin_user'] = [
            'id'                    => (int)$user['id'],
            'name'                  => $user['name'],
            'username'              => $user['username'],
            'email'                 => $user['email'],
            'role_id'               => (int)$user['role_id'],
            'role'                  => $user['role_slug'],
            'role_name'             => $user['role_name'],
            'session_version'       => (int)($user['session_version'] ?? 1),
            'force_password_change' => !empty($user['force_password_change'])
        ];
        $_SESSION['session_created_at'] = time();
        $_SESSION['session_last_activity'] = time();

        // Reset failed counter and update login timestamp
        db_exec(
            "UPDATE `users` SET `failed_login_count` = 0, `locked_until` = NULL, `last_login_at` = NOW() WHERE `id` = ?",
            [$user['id']]
        );

        audit_log('auth.login_success', 'user', (string)$user['id'], null, ['username' => $cleanUsername]);

        return ['success' => true, 'user' => $_SESSION['tabeeb_admin_user']];
    } catch (Throwable $t) {
        error_log('[Authentication Failure] ' . $t->getMessage());
        return ['success' => false, 'message' => 'An unexpected authentication error occurred.'];
    }
}

function logout_user(?string $reason = null): void {
    if (!empty($_SESSION['tabeeb_admin_user'])) {
        audit_log('auth.logout', 'user', (string)$_SESSION['tabeeb_admin_user']['id'], null, ['reason' => $reason]);
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies") && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
}

/**
 * Invalidate all active sessions for a user (e.g., after password reset or deactivation)
 */
function revoke_user_sessions(int $userId): void {
    if (DB::isConnected()) {
        db_exec("UPDATE `users` SET `session_version` = `session_version` + 1 WHERE `id` = ?", [$userId]);
    }
}

/**
 * 4. CURRENT USER & ACCESS CHECKS
 */
function is_logged_in(): bool {
    return !empty($_SESSION['tabeeb_admin_user']);
}

function current_user(): ?array {
    return $_SESSION['tabeeb_admin_user'] ?? null;
}

function is_super_admin(): bool {
    $user = current_user();
    return $user && (($user['role'] ?? '') === 'super_admin' || (int)($user['role_id'] ?? 0) === 1);
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

/**
 * Require login and enforce mandatory password change
 */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }

    // Enforce force_password_change before permitting any other protected action
    $user = current_user();
    if (!empty($user['force_password_change'])) {
        $currentScript = basename($_SERVER['PHP_SELF'] ?? '');
        if ($currentScript !== 'change-password.php' && $currentScript !== 'logout.php') {
            header('Location: /admin/change-password.php');
            exit;
        }
    }
}

function require_super_admin(): void {
    require_login();
    if (!is_super_admin()) {
        http_response_code(403);
        include dirname(__DIR__) . '/admin/header.php';
        echo '<div style="padding: 40px; text-align: center;"><h2>403 Forbidden</h2><p>Access Denied: Super Administrator privileges are required.</p><a href="/admin/index.php" class="btn-adm btn-adm-primary">Return to Dashboard</a></div>';
        include dirname(__DIR__) . '/admin/footer.php';
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
 * 5. FINAL ACTIVE SUPER ADMIN INVARIANT
 *
 * Ensures an edit, role demotion, deactivation, or deletion cannot leave 0 active super admins.
 * Supports row-level locks ($forUpdate) to prevent concurrent race conditions.
 */
function count_active_super_admins(bool $forUpdate = false): int {
    if (DB::isConnected()) {
        $pdo = DB::getConnection();
        $lock = ($forUpdate && $pdo && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') ? " FOR UPDATE" : "";
        return (int)db_val(
            "SELECT COUNT(*) FROM `users` u
             JOIN `roles` r ON u.role_id = r.id
             WHERE r.slug = 'super_admin' AND u.status = 'active' AND u.deleted_at IS NULL" . $lock
        );
    }
    return 1;
}

function can_modify_super_admin_status(int $targetUserId, ?int $newRoleId = null, ?string $newStatus = null, bool $isDelete = false, bool $forUpdate = false): bool {
    if (!DB::isConnected()) {
        return false;
    }

    $pdo = DB::getConnection();
    $lock = ($forUpdate && $pdo && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') ? " FOR UPDATE" : "";
    $target = db_one(
        "SELECT u.id, u.status, u.role_id, r.slug AS role_slug
         FROM `users` u
         JOIN `roles` r ON u.role_id = r.id
         WHERE u.id = ? LIMIT 1" . $lock,
        [$targetUserId]
    );

    if (!$target || $target['role_slug'] !== 'super_admin' || $target['status'] !== 'active') {
        return true; // Not an active super admin, no invariant violation
    }

    $superAdminRoleId = (int)db_val("SELECT `id` FROM `roles` WHERE `slug` = 'super_admin' LIMIT 1");

    $isDemoted = ($newRoleId !== null && $newRoleId !== $superAdminRoleId);
    $isDeactivated = ($newStatus !== null && $newStatus !== 'active');

    if ($isDelete || $isDemoted || $isDeactivated) {
        $activeSuperAdmins = count_active_super_admins($forUpdate);
        if ($activeSuperAdmins <= 1) {
            return false; // Invariant violated: cannot remove or demote the last active super admin
        }
    }

    return true;
}
