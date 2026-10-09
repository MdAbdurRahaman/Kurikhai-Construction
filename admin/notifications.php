<?php
$pageTitle = 'Notification Routing & Logs';
require_once __DIR__ . '/header.php';

require_super_admin();

$msg = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'add_recipient') {
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $rcptType = in_array($_POST['recipient_type'] ?? '', ['to', 'cc', 'bcc']) ? $_POST['recipient_type'] : 'to';
            $serviceFilter = trim($_POST['service_filter'] ?? '') ?: null;

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                db_exec(
                    "INSERT INTO `notification_recipients` (`name`, `email`, `is_active`, `recipient_type`, `service_filter`, `created_at`) 
                     VALUES (?, ?, 1, ?, ?, NOW())",
                    [$name ?: $email, $email, $rcptType, $serviceFilter]
                );
                audit_log('notification.recipient_added', 'notification_recipients', null, null, ['email' => $email]);
                $msg = "Recipient '{$email}' registered successfully!";
            } else {
                $error = 'Please provide a valid email address.';
            }

        } elseif ($action === 'toggle_active') {
            $id = (int)($_POST['recipient_id'] ?? 0);
            $curr = (int)db_val("SELECT is_active FROM notification_recipients WHERE id = ?", [$id]);
            $new = $curr ? 0 : 1;
            db_exec("UPDATE notification_recipients SET is_active = ? WHERE id = ?", [$new, $id]);
            $msg = 'Recipient status updated.';

        } elseif ($action === 'delete_recipient') {
            $id = (int)($_POST['recipient_id'] ?? 0);
            db_exec("DELETE FROM notification_recipients WHERE id = ?", [$id]);
            $msg = 'Recipient removed.';

        } elseif ($action === 'send_test_email') {
            $testEmail = strtolower(trim($_POST['test_email'] ?? ''));
            if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $res = Mailer::sendTestEmail($testEmail);
                if ($res['success']) {
                    $msg = "Test email dispatched successfully to '{$testEmail}'!";
                } else {
                    $error = "Test email failed: " . ($res['error'] ?? 'Unknown SMTP error');
                }
            } else {
                $error = 'Please enter a valid test email address.';
            }
        }
    }
}

// Fetch recipients & logs
$recipients = db_all("SELECT * FROM `notification_recipients` ORDER BY `id` ASC");
$logs = db_all("SELECT * FROM `notification_logs` ORDER BY `created_at` DESC LIMIT 25");
?>

<?php if (!empty($msg)): ?>
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: start; margin-bottom: 30px;">
    
    <!-- Recipients Table -->
    <div class="table-card">
        <div class="table-header">
            <div>
                <h3>Lead Notification Recipient Routing</h3>
                <p style="font-size: 13px; color: var(--admin-text-muted); margin-top: 2px;">
                    Configure who receives email alerts when customers submit quote requests.
                </p>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Type</th>
                        <th>Service Filter</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recipients)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">
                                No recipients configured yet. Adding a recipient below will activate automated notifications.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recipients as $rcpt): ?>
                            <tr>
                                <td>
                                    <strong style="color: #0f172a;"><?= htmlspecialchars($rcpt['name']) ?></strong>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($rcpt['email']) ?></div>
                                </td>
                                <td>
                                    <span style="background: #e0f2fe; color: #0284c7; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                        <?= htmlspecialchars($rcpt['recipient_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= !empty($rcpt['service_filter']) ? htmlspecialchars($rcpt['service_filter']) : '<span style="color: #94a3b8;">All Services</span>' ?>
                                </td>
                                <td>
                                    <form method="POST" action="notifications.php" style="margin: 0; display: inline;">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="form_action" value="toggle_active">
                                        <input type="hidden" name="recipient_id" value="<?= $rcpt['id'] ?>">
                                        <button type="submit" style="background: none; border: none; cursor: pointer; padding: 0;">
                                            <?php if ($rcpt['is_active']): ?>
                                                <span style="color: #16a34a; font-weight: 600; font-size: 12px;">🟢 Active</span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-weight: 600; font-size: 12px;">⚪ Paused</span>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </td>
                                <td style="text-align: right;">
                                    <form method="POST" action="notifications.php" style="margin: 0; display: inline;" onsubmit="return confirm('Remove this notification recipient?');">
                                        <?= csrf_input_field() ?>
                                        <input type="hidden" name="form_action" value="delete_recipient">
                                        <input type="hidden" name="recipient_id" value="<?= $rcpt['id'] ?>">
                                        <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 12px;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Add Recipient & Test Mailer -->
    <div>
        <!-- Add Recipient Box -->
        <div class="table-card" style="margin-bottom: 25px;">
            <div class="table-header">
                <h3>+ Add Recipient</h3>
            </div>
            <div style="padding: 20px;">
                <form method="POST" action="notifications.php">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="form_action" value="add_recipient">

                    <div class="form-group">
                        <label class="form-label" for="rcptName">Recipient Label / Name</label>
                        <input type="text" name="name" id="rcptName" class="form-control-adm" placeholder="e.g. Sales Team" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="rcptEmail">Email Address</label>
                        <input type="email" name="email" id="rcptEmail" class="form-control-adm" placeholder="info@tabeebgroup.com" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="rcptType">Delivery Mode</label>
                        <select name="recipient_type" id="rcptType" class="form-control-adm">
                            <option value="to">To (Direct Primary)</option>
                            <option value="cc">CC (Carbon Copy)</option>
                            <option value="bcc">BCC (Blind Copy)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="rcptFilter">Filter by Service (Optional)</label>
                        <input type="text" name="service_filter" id="rcptFilter" class="form-control-adm" placeholder="Leave empty for all leads">
                    </div>

                    <button type="submit" class="btn-adm btn-adm-primary" style="width: 100%;">Save Recipient</button>
                </form>
            </div>
        </div>

        <!-- Test Email Dispatch -->
        <div class="table-card">
            <div class="table-header">
                <h3>Test SMTP Delivery</h3>
            </div>
            <div style="padding: 20px;">
                <p style="font-size: 13px; color: var(--admin-text-muted); margin-bottom: 14px;">
                    Send an instantaneous test alert to verify host connection (<?= htmlspecialchars(SMTP_HOST) ?>:<?= SMTP_PORT ?>).
                </p>
                <form method="POST" action="notifications.php">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="form_action" value="send_test_email">

                    <div class="form-group">
                        <input type="email" name="test_email" class="form-control-adm" placeholder="Enter recipient email..." required value="<?= htmlspecialchars(current_user()['email'] ?? 'info@tabeebgroup.com') ?>">
                    </div>

                    <button type="submit" class="btn-adm btn-adm-outline" style="width: 100%;">🚀 Dispatch Test Email</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Notification Delivery Logs Table -->
<div class="table-card">
    <div class="table-header">
        <div>
            <h3>Notification Delivery Logs (Last 25 Events)</h3>
            <p style="font-size: 13px; color: var(--admin-text-muted); margin-top: 2px;">
                Track automated SMTP alert transmissions and status responses.
            </p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Provider Response / Error</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">
                            No notification delivery logs recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-size: 12px; color: #64748b;"><?= date('M j, Y g:i a', strtotime($log['created_at'])) ?></td>
                            <td><?= htmlspecialchars($log['recipient_email']) ?></td>
                            <td style="font-size: 13px;"><?= htmlspecialchars($log['subject']) ?></td>
                            <td>
                                <?php if ($log['delivery_status'] === 'sent'): ?>
                                    <span style="color: #16a34a; font-weight: 700; font-size: 11px;">✓ DELIVERED</span>
                                <?php else: ?>
                                    <span style="color: #dc2626; font-weight: 700; font-size: 11px;">✕ FAILED</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: #475569; font-family: monospace;">
                                <?= htmlspecialchars($log['provider_response'] ?: ($log['error_message'] ?: 'OK')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
