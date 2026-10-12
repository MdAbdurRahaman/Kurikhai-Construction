<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$user = current_user();
$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $error = 'All password fields are required.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'The new password and confirmation password do not match.';
        } elseif (strlen($newPass) < 10) {
            $error = 'The new password must be at least 10 characters long.';
        } elseif (!preg_match('/[A-Z]/', $newPass) || !preg_match('/[a-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
            $error = 'The new password must contain at least one uppercase letter, one lowercase letter, and one number.';
        } elseif ($currentPass === $newPass) {
            $error = 'Your new password cannot be identical to your current password.';
        } else {
            // Verify current password against database
            $dbUser = db_one("SELECT id, password_hash, session_version FROM `users` WHERE `id` = ? LIMIT 1", [$user['id']]);
            if (!$dbUser || !password_verify($currentPass, $dbUser['password_hash'])) {
                $error = 'The current password you entered is incorrect.';
                audit_log('auth.password_change_failed', 'user', (string)$user['id'], null, ['reason' => 'invalid_current_password']);
            } else {
                $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                $newHash = password_hash($newPass, $algo);
                $newSessionVer = ((int)($dbUser['session_version'] ?? 1)) + 1;

                db_exec(
                    "UPDATE `users` SET `password_hash` = ?, `force_password_change` = 0, `session_version` = ?, `updated_at` = NOW() WHERE `id` = ?",
                    [$newHash, $newSessionVer, $user['id']]
                );

                // Update session state
                $_SESSION['tabeeb_admin_user']['force_password_change'] = false;
                $_SESSION['tabeeb_admin_user']['session_version'] = $newSessionVer;

                audit_log('auth.password_changed', 'user', (string)$user['id'], null, ['status' => 'success']);

                header('Location: /admin/index.php?msg=' . urlencode('Your password has been successfully updated!'));
                exit;
            }
        }
    }
}

$pageTitle = 'Update Account Password';
require_once __DIR__ . '/header.php';
?>

<div style="max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 32px;">
    <h2 style="font-family: 'Outfit', sans-serif; font-size: 22px; color: #0a1128; margin-top: 0; margin-bottom: 8px;">
        🔒 Update Your Password
    </h2>
    <p style="font-size: 14px; color: #64748b; margin-bottom: 24px;">
        <?= !empty($user['force_password_change']) ? 'For security reasons, you must establish a new password before accessing portal features.' : 'Establish a strong, unique password for your staff account.' ?>
    </p>

    <?php if (!empty($error)): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="change-password.php">
        <?= csrf_input_field() ?>

        <div class="form-group" style="margin-bottom: 20px;">
            <label class="form-label" for="current_password" style="display: block; font-weight: 600; font-size: 13px; color: #1e293b; margin-bottom: 6px;">Current Password</label>
            <input type="password" name="current_password" id="current_password" class="form-control-adm" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label class="form-label" for="new_password" style="display: block; font-weight: 600; font-size: 13px; color: #1e293b; margin-bottom: 6px;">New Password (min 10 characters)</label>
            <input type="password" name="new_password" id="new_password" class="form-control-adm" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Must contain uppercase, lowercase, and number characters.</div>
        </div>

        <div class="form-group" style="margin-bottom: 28px;">
            <label class="form-label" for="confirm_password" style="display: block; font-weight: 600; font-size: 13px; color: #1e293b; margin-bottom: 6px;">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control-adm" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
        </div>

        <button type="submit" class="btn-adm btn-adm-primary" style="width: 100%; padding: 12px; background: #0a1128; color: #ffffff; border: none; border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer;">
            Save New Password & Continue
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
