<?php
require_once dirname(__DIR__) . '/includes/data.php';
require_login();
require_permission('leads.view');

$leadId = (int)($_GET['id'] ?? 0);
$leadNum = trim($_GET['num'] ?? '');

$lead = null;
if (DB::isConnected()) {
    if ($leadId > 0) {
        $lead = db_one("SELECT * FROM `leads` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1", [$leadId]);
    } elseif (!empty($leadNum)) {
        $lead = db_one("SELECT * FROM `leads` WHERE `lead_number` = ? AND `deleted_at` IS NULL LIMIT 1", [$leadNum]);
        if ($lead) $leadId = (int)$lead['id'];
    }
}

if (!$lead) {
    header('Location: leads.php?error=' . urlencode('Inquiry record not found.'));
    exit;
}

$pageTitle = "Lead #{$lead['lead_number']} - {$lead['name']}";
$msg = '';
$error = '';

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error = 'Invalid security token. Please refresh.';
    } elseif ($action === 'update_lead') {
        require_permission('leads.edit');

        $newStatus = $_POST['status'] ?? $lead['status'];
        $newPriority = $_POST['priority'] ?? $lead['priority'];
        $newAssignee = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
        $estValue = !empty($_POST['estimated_deal_value']) ? (float)$_POST['estimated_deal_value'] : null;
        $finalValue = !empty($_POST['final_deal_value']) ? (float)$_POST['final_deal_value'] : null;
        $followUp = !empty($_POST['next_followup_at']) ? $_POST['next_followup_at'] : null;

        $statusChanged = ($newStatus !== $lead['status']);
        $assigneeChanged = ($newAssignee !== (int)$lead['assigned_user_id']);

        db_exec(
            "UPDATE `leads` SET 
             `status` = ?, `priority` = ?, `assigned_user_id` = ?, 
             `estimated_deal_value` = ?, `final_deal_value` = ?, `next_followup_at` = ?, 
             `updated_at` = NOW() 
             WHERE `id` = ?",
            [$newStatus, $newPriority, $newAssignee, $estValue, $finalValue, $followUp, $leadId]
        );

        if ($statusChanged) {
            db_exec(
                "INSERT INTO `lead_activities` (`lead_id`, `user_id`, `activity_type`, `description`, `created_at`) 
                 VALUES (?, ?, 'status_changed', ?, NOW())",
                [$leadId, current_user()['id'], "Status changed from {$lead['status']} to {$newStatus}"]
            );
        }

        if ($assigneeChanged) {
            $assigneeName = $newAssignee ? db_val("SELECT name FROM users WHERE id = ?", [$newAssignee]) : 'Unassigned';
            db_exec(
                "INSERT INTO `lead_activities` (`lead_id`, `user_id`, `activity_type`, `description`, `created_at`) 
                 VALUES (?, ?, 'assignee_changed', ?, NOW())",
                [$leadId, current_user()['id'], "Lead assigned to {$assigneeName}"]
            );
        }

        audit_log('lead.updated', 'lead', (string)$leadId, $lead, ['status' => $newStatus, 'priority' => $newPriority]);
        $msg = 'Inquiry details updated successfully!';
        // Refresh record
        $lead = db_one("SELECT * FROM `leads` WHERE `id` = ? LIMIT 1", [$leadId]);

    } elseif ($action === 'add_note') {
        $noteText = trim($_POST['note_text'] ?? '');
        if (!empty($noteText)) {
            db_exec(
                "INSERT INTO `lead_notes` (`lead_id`, `user_id`, `note`, `created_at`) 
                 VALUES (?, ?, ?, NOW())",
                [$leadId, current_user()['id'], $noteText]
            );

            db_exec(
                "INSERT INTO `lead_activities` (`lead_id`, `user_id`, `activity_type`, `description`, `created_at`) 
                 VALUES (?, ?, 'note_added', 'New internal follow-up note added', NOW())",
                [$leadId, current_user()['id']]
            );

            $msg = 'Note added to activity timeline.';
        }
    }
}

require_once __DIR__ . '/header.php';

// Fetch users for assignment dropdown
$assignees = db_all("SELECT `id`, `name` FROM `users` WHERE `status` = 'active' AND `deleted_at` IS NULL ORDER BY `name` ASC");
// Fetch notes
$notes = db_all(
    "SELECT n.*, u.name AS author_name 
     FROM `lead_notes` n 
     JOIN `users` u ON n.user_id = u.id 
     WHERE n.lead_id = ? 
     ORDER BY n.created_at DESC",
    [$leadId]
);
// Fetch activities
$activities = db_all(
    "SELECT a.*, u.name AS actor_name 
     FROM `lead_activities` a 
     LEFT JOIN `users` u ON a.user_id = u.id 
     WHERE a.lead_id = ? 
     ORDER BY a.created_at DESC",
    [$leadId]
);
?>

<?php if (!empty($msg)): ?>
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Lead Profile Header & Quick Actions -->
<div style="background: #ffffff; border: 1px solid var(--admin-border); border-radius: var(--admin-radius); padding: 24px; box-shadow: var(--admin-shadow); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <div>
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
            <h2 style="font-size: 22px; color: var(--admin-navy); margin: 0;"><?= htmlspecialchars($lead['name']) ?></h2>
            <span style="background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: 9999px; font-size: 12px; font-weight: 700;"><?= htmlspecialchars($lead['lead_number']) ?></span>
        </div>
        <p style="color: var(--admin-text-muted); font-size: 13px; margin: 0;">
            Requested: <strong><?= htmlspecialchars($lead['service_requested']) ?></strong> &bull; Received <?= date('D, M j, Y \a\t g:i a', strtotime($lead['created_at'])) ?>
        </p>
    </div>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="tel:<?= preg_replace('/[^\d+]/', '', $lead['phone']) ?>" class="btn-adm btn-adm-outline" style="font-size: 13px;">
            📞 Call Phone
        </a>
        <a href="https://wa.me/<?= preg_replace('/[^\d]/', '', $lead['phone']) ?>?text=<?= urlencode("Hello " . $lead['name'] . ", this is Tabeeb Contractor regarding your inquiry for " . $lead['service_requested'] . ".") ?>" target="_blank" class="btn-adm" style="background: #25d366; color: #fff; text-decoration: none; font-size: 13px;">
            💬 Open WhatsApp
        </a>
        <?php if (!empty($lead['email'])): ?>
            <a href="mailto:<?= htmlspecialchars($lead['email']) ?>?subject=<?= urlencode("Tabeeb Contractor - Quote Inquiry #" . $lead['lead_number']) ?>" class="btn-adm btn-adm-outline" style="font-size: 13px;">
                ✉️ Send Email
            </a>
        <?php endif; ?>
        <a href="leads.php" class="btn-adm btn-adm-outline" style="font-size: 13px;">← Back to List</a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; align-items: start;">
    
    <!-- Left Column: Scope Details & Notes -->
    <div>
        <!-- Inquired Scope Details -->
        <div class="table-card" style="margin-bottom: 25px;">
            <div class="table-header">
                <h3>Customer Scope & Property Information</h3>
            </div>
            <div style="padding: 24px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">SERVICE REQUESTED</div>
                        <div style="font-size: 15px; font-weight: 600; color: #0f172a;"><?= htmlspecialchars($lead['service_requested']) ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">ESTIMATED BUDGET RANGE</div>
                        <div style="font-size: 15px; font-weight: 600; color: #0f172a;"><?= htmlspecialchars($lead['estimated_budget'] ?? 'Unspecified') ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">PROPERTY TYPE</div>
                        <div style="font-size: 14px; color: #334155;"><?= htmlspecialchars($lead['property_type'] ?? 'Unspecified') ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">PREFERRED CONTACT</div>
                        <div style="font-size: 14px; text-transform: capitalize; color: #334155;"><?= htmlspecialchars($lead['preferred_contact_method']) ?></div>
                    </div>
                </div>

                <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 6px;">PROJECT REQUIREMENTS & DESCRIPTION</div>
                <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 8px; padding: 16px; font-size: 14px; line-height: 1.6; color: #1e293b; white-space: pre-wrap;">
                    <?= htmlspecialchars($lead['project_description']) ?>
                </div>

                <div style="display: flex; gap: 20px; font-size: 12px; color: #64748b; margin-top: 16px;">
                    <div>Source: <strong><?= htmlspecialchars($lead['source']) ?></strong></div>
                    <?php if (!empty($lead['landing_page'])): ?>
                        <div>Landing: <strong><?= htmlspecialchars($lead['landing_page']) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Follow-up Notes -->
        <div class="table-card" style="margin-bottom: 25px;">
            <div class="table-header">
                <h3>Internal CRM Notes</h3>
            </div>
            <div style="padding: 24px;">
                <form method="POST" action="lead-detail.php?id=<?= $leadId ?>" style="margin-bottom: 24px;">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="form_action" value="add_note">
                    <div class="form-group">
                        <textarea name="note_text" class="form-control-adm" rows="3" placeholder="Add follow-up note (e.g. Called client, scheduled site visit for Tuesday 2 PM)..." required></textarea>
                    </div>
                    <button type="submit" class="btn-adm btn-adm-primary" style="font-size: 13px;">Add Internal Note</button>
                </form>

                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (empty($notes)): ?>
                        <p style="color: #94a3b8; font-size: 13px; margin: 0;">No internal notes added yet.</p>
                    <?php else: ?>
                        <?php foreach ($notes as $n): ?>
                            <div style="background: #f8fafc; border-left: 3px solid var(--admin-accent); padding: 12px 16px; border-radius: 0 8px 8px 0;">
                                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b; margin-bottom: 4px;">
                                    <strong><?= htmlspecialchars($n['author_name']) ?></strong>
                                    <span><?= date('M j, Y g:i a', strtotime($n['created_at'])) ?></span>
                                </div>
                                <div style="font-size: 14px; color: #334155; line-height: 1.5; white-space: pre-wrap;"><?= htmlspecialchars($n['note']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="table-card">
            <div class="table-header">
                <h3>Activity Timeline & Audit History</h3>
            </div>
            <div style="padding: 20px 24px;">
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($activities as $act): ?>
                        <div style="display: flex; gap: 12px; align-items: start; font-size: 13px;">
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--admin-accent); margin-top: 6px; flex-shrink: 0;"></div>
                            <div>
                                <div style="color: #0f172a; font-weight: 500;"><?= htmlspecialchars($act['description']) ?></div>
                                <div style="color: #64748b; font-size: 12px;">
                                    <?= htmlspecialchars($act['actor_name'] ?? 'System') ?> &bull; <?= date('M j, Y g:i a', strtotime($act['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Status & Assignment Settings -->
    <div>
        <div class="table-card">
            <div class="table-header">
                <h3>Pipeline Status & Control</h3>
            </div>
            <div style="padding: 24px;">
                <form method="POST" action="lead-detail.php?id=<?= $leadId ?>">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="form_action" value="update_lead">

                    <div class="form-group">
                        <label class="form-label" for="status">Lead Status</label>
                        <select name="status" id="status" class="form-control-adm" style="font-weight: 600;">
                            <?php 
                            $statusOptions = [
                                'new' => '🔴 New (Uncontacted)',
                                'contacted' => '🔵 Contacted',
                                'qualified' => '🟣 Qualified Opportunity',
                                'site_visit_scheduled' => '🟡 Site Visit Scheduled',
                                'site_visit_completed' => '🟠 Site Visit Completed',
                                'quotation_preparing' => '📝 Quotation Preparing',
                                'quotation_sent' => '📑 Quotation Sent',
                                'negotiation' => '🤝 In Negotiation',
                                'won' => '🟢 Deal Won',
                                'lost' => '⚫ Deal Lost',
                                'spam' => '❌ Spam',
                                'archived' => '📁 Archived'
                            ];
                            foreach ($statusOptions as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= $lead['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="priority">Priority</label>
                        <select name="priority" id="priority" class="form-control-adm">
                            <option value="urgent" <?= $lead['priority'] === 'urgent' ? 'selected' : '' ?>>🔴 Urgent</option>
                            <option value="high" <?= $lead['priority'] === 'high' ? 'selected' : '' ?>>🟠 High</option>
                            <option value="medium" <?= $lead['priority'] === 'medium' ? 'selected' : '' ?>>🔵 Medium</option>
                            <option value="low" <?= $lead['priority'] === 'low' ? 'selected' : '' ?>>⚪ Low</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="assigned_user_id">Assigned Staff</label>
                        <select name="assigned_user_id" id="assigned_user_id" class="form-control-adm">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($assignees as $asg): ?>
                                <option value="<?= $asg['id'] ?>" <?= (int)$lead['assigned_user_id'] === (int)$asg['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($asg['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="estimated_deal_value">Estimated Value (SGD)</label>
                        <input type="number" step="0.01" name="estimated_deal_value" id="estimated_deal_value" class="form-control-adm" placeholder="e.g. 15000" value="<?= htmlspecialchars($lead['estimated_deal_value'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="final_deal_value">Final Won Value (SGD)</label>
                        <input type="number" step="0.01" name="final_deal_value" id="final_deal_value" class="form-control-adm" placeholder="e.g. 14500" value="<?= htmlspecialchars($lead['final_deal_value'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="next_followup_at">Next Follow-Up Date</label>
                        <input type="datetime-local" name="next_followup_at" id="next_followup_at" class="form-control-adm" value="<?= !empty($lead['next_followup_at']) ? date('Y-m-d\TH:i', strtotime($lead['next_followup_at'])) : '' ?>">
                    </div>

                    <button type="submit" class="btn-adm btn-adm-primary" style="width: 100%; padding: 12px;">Save Lead Updates</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
