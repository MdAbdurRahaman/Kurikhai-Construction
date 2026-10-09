<?php
$pageTitle = 'Client Information Tracker';
require_once __DIR__ . '/header.php';

require_permission('business_info.manage');

$msg = '';
$error = '';

// Handle Item Edit / Publication Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Invalid security token. Please refresh.';
    } else {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $existing = db_one("SELECT * FROM `business_information` WHERE `id` = ? LIMIT 1", [$itemId]);

        if ($existing) {
            $currentVal = trim($_POST['current_value'] ?? '');
            $draftVal = trim($_POST['draft_value'] ?? '');
            $status = $_POST['status'] ?? $existing['status'];
            $pubState = $_POST['publication_state'] ?? $existing['publication_state'];
            $evidenceRef = trim($_POST['evidence_ref'] ?? '');
            $nextAction = trim($_POST['next_action'] ?? '');
            $internalNotes = trim($_POST['internal_notes'] ?? '');

            // Log change history
            $changeSummary = "Updated by " . current_user()['name'];
            if ($pubState !== $existing['publication_state']) {
                $changeSummary .= " (Publication: {$existing['publication_state']} -> {$pubState})";
            }
            if ($status !== $existing['status']) {
                $changeSummary .= " (Status: {$existing['status']} -> {$status})";
            }

            db_exec(
                "INSERT INTO `business_information_history` 
                 (`item_id`, `previous_value`, `new_value`, `changed_by`, `change_summary`, `created_at`) 
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$itemId, $existing['current_value'], $currentVal, current_user()['id'], $changeSummary]
            );

            // Update item
            $publishedAt = ($pubState === 'published' && $existing['publication_state'] !== 'published') ? date('Y-m-d H:i:s') : $existing['published_at'];
            $approvedBy = ($pubState === 'published') ? current_user()['id'] : $existing['approved_by'];

            db_exec(
                "UPDATE `business_information` SET 
                 `current_value` = ?, `draft_value` = ?, `status` = ?, `publication_state` = ?, 
                 `evidence_ref` = ?, `next_action` = ?, `internal_notes` = ?, 
                 `published_at` = ?, `approved_by` = ?, `updated_at` = NOW() 
                 WHERE `id` = ?",
                [
                    $currentVal, $draftVal, $status, $pubState, 
                    $evidenceRef, $nextAction, $internalNotes, 
                    $publishedAt, $approvedBy, $itemId
                ]
            );

            audit_log('business_info.updated', 'business_info', (string)$itemId, $existing, ['status' => $status, 'publication' => $pubState]);
            $msg = "Information item '{$existing['title']}' updated successfully!";
        }
    }
}

// Filters
$catFilter = trim($_GET['category'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$pubFilter = trim($_GET['publication'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($catFilter)) {
    $where[] = "`category` = ?";
    $params[] = $catFilter;
}
if (!empty($statusFilter)) {
    $where[] = "`status` = ?";
    $params[] = $statusFilter;
}
if (!empty($pubFilter)) {
    $where[] = "`publication_state` = ?";
    $params[] = $pubFilter;
}

$whereSql = implode(' AND ', $where);

$items = db_all("SELECT * FROM `business_information` WHERE {$whereSql} ORDER BY `category` ASC, `id` ASC", $params);
$metrics = BusinessTracker::getMetrics();

// Categories lookup
$categories = [
    'company_identity'   => '🏢 Company Identity',
    'contacts'           => '📞 Contacts & Channels',
    'credentials'        => '📜 Credentials & Licenses',
    'services'           => '🛠️ Services & Property Types',
    'service_areas'      => '📍 Service Areas & Coverage',
    'claims'             => '⭐ Experience & Claims',
    'projects'           => '🏗️ Completed Projects',
    'reviews'            => '💬 Genuine Reviews',
    'commercial_policies'=> '📄 Commercial Policies',
    'privacy'            => '🔒 Privacy & Data Retention',
    'marketing'          => '🌐 Marketing & References'
];
?>

<?php if (!empty($msg)): ?>
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Top Metrics -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 25px;">
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $metrics['total'] ?></div>
            <div class="stat-label">Tracked Items</div>
        </div>
        <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">📊</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $metrics['confirmed'] ?></div>
            <div class="stat-label">Confirmed / Verified</div>
        </div>
        <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;">✅</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $metrics['pending'] ?></div>
            <div class="stat-label">Pending / Unverified</div>
        </div>
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">⏳</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $metrics['published'] ?></div>
            <div class="stat-label">Published Live</div>
        </div>
        <div class="stat-icon" style="background: #ecfdf5; color: #059669;">🌐</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $metrics['hidden'] ?></div>
            <div class="stat-label">Hidden / Draft</div>
        </div>
        <div class="stat-icon" style="background: #f1f5f9; color: #64748b;">👁️</div>
    </div>
</div>

<!-- Filters -->
<div class="table-card" style="margin-bottom: 25px;">
    <div style="padding: 18px 24px;">
        <form method="GET" action="business-info.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 180px;">
                <select name="category" class="form-control-adm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $ck => $cv): ?>
                        <option value="<?= $ck ?>" <?= $catFilter === $ck ? 'selected' : '' ?>><?= $cv ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 180px;">
                <select name="status" class="form-control-adm">
                    <option value="">All Statuses</option>
                    <option value="client_confirmed" <?= $statusFilter === 'client_confirmed' ? 'selected' : '' ?>>Client confirmed</option>
                    <option value="document_verified" <?= $statusFilter === 'document_verified' ? 'selected' : '' ?>>Document verified</option>
                    <option value="received_needs_clarification" <?= $statusFilter === 'received_needs_clarification' ? 'selected' : '' ?>>Received—needs clarification</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="needs_update" <?= $statusFilter === 'needs_update' ? 'selected' : '' ?>>Needs update</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <select name="publication" class="form-control-adm">
                    <option value="">All Publication States</option>
                    <option value="published" <?= $pubFilter === 'published' ? 'selected' : '' ?>>🟢 Published</option>
                    <option value="hidden" <?= $pubFilter === 'hidden' ? 'selected' : '' ?>>⚫ Hidden</option>
                    <option value="draft" <?= $pubFilter === 'draft' ? 'selected' : '' ?>>🟡 Draft</option>
                </select>
            </div>

            <div>
                <button type="submit" class="btn-adm btn-adm-primary">Filter Items</button>
                <a href="business-info.php" class="btn-adm btn-adm-outline">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tracker Items Table -->
<div class="table-card">
    <div class="table-header">
        <div>
            <h3>Client Information Items (<?= count($items) ?> Records)</h3>
            <p style="font-size: 13px; color: var(--admin-text-muted); margin-top: 2px;">
                Manage verified company facts, pending credentials, and public publication approvals.
            </p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th style="width: 200px;">Information Item</th>
                    <th>Current / Approved Value</th>
                    <th>Status</th>
                    <th>Publication</th>
                    <th>Next Action</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                            No tracker items found matching the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): 
                        $statusBadge = match($item['status']) {
                            'client_confirmed' => ['#16a34a', 'Client confirmed'],
                            'document_verified' => ['#0284c7', 'Document verified'],
                            'received_needs_clarification' => ['#ea580c', 'Needs clarification'],
                            'pending' => ['#f59e0b', 'Pending'],
                            'needs_update' => ['#dc2626', 'Needs update'],
                            default => ['#94a3b8', 'N/A']
                        };
                        $pubBadge = match($item['publication_state']) {
                            'published' => ['#10b981', 'Published'],
                            'hidden' => ['#64748b', 'Hidden'],
                            'draft' => ['#f59e0b', 'Draft'],
                            default => ['#94a3b8', 'Hidden']
                        };
                    ?>
                        <tr>
                            <td>
                                <strong style="color: var(--admin-navy);"><?= htmlspecialchars($item['title']) ?></strong>
                                <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($categories[$item['category']] ?? $item['category']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($item['current_value'])): ?>
                                    <div style="font-size: 13px; color: #1e293b; max-width: 320px; word-break: break-word;">
                                        <?= htmlspecialchars($item['current_value']) ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">Not yet supplied</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background: <?= $statusBadge[0] ?>18; color: <?= $statusBadge[0] ?>; border: 1px solid <?= $statusBadge[0] ?>40; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700;">
                                    <?= $statusBadge[1] ?>
                                </span>
                            </td>
                            <td>
                                <span style="background: <?= $pubBadge[0] ?>18; color: <?= $pubBadge[0] ?>; border: 1px solid <?= $pubBadge[0] ?>40; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700;">
                                    <?= $pubBadge[1] ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: #475569; max-width: 250px;">
                                <?= htmlspecialchars($item['next_action'] ?? '—') ?>
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 10px; font-size: 12px;" onclick='openEditModal(<?= json_encode($item) ?>)'>
                                    Edit / Review ➔
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for Item Editing -->
<div class="modal" id="editItemModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalItemTitle" style="font-size: 18px; color: var(--admin-navy); margin: 0;">Edit Information Item</h3>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <form method="POST" action="business-info.php">
            <?= csrf_input_field() ?>
            <input type="hidden" name="item_id" id="modalItemId">

            <div class="form-group">
                <label class="form-label" for="modalCurrentValue">Current / Approved Value</label>
                <textarea name="current_value" id="modalCurrentValue" class="form-control-adm" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="modalDraftValue">Draft Value (Unapproved / In Review)</label>
                <textarea name="draft_value" id="modalDraftValue" class="form-control-adm" rows="2"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="modalStatus">Verification Status</label>
                    <select name="status" id="modalStatus" class="form-control-adm">
                        <option value="client_confirmed">Client confirmed</option>
                        <option value="document_verified">Document verified</option>
                        <option value="received_needs_clarification">Received—needs clarification</option>
                        <option value="pending">Pending</option>
                        <option value="needs_update">Needs update</option>
                        <option value="not_applicable">Not applicable</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="modalPubState">Publication State</label>
                    <select name="publication_state" id="modalPubState" class="form-control-adm">
                        <option value="published">🟢 Published (Live on site)</option>
                        <option value="hidden">⚫ Hidden (Internal only)</option>
                        <option value="draft">🟡 Draft</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="modalEvidence">Evidence Reference / Source</label>
                <input type="text" name="evidence_ref" id="modalEvidence" class="form-control-adm" placeholder="e.g. ACRA Bizfile PDF dated Oct 2026">
            </div>

            <div class="form-group">
                <label class="form-label" for="modalNextAction">Next Action</label>
                <input type="text" name="next_action" id="modalNextAction" class="form-control-adm" placeholder="e.g. Verify tax registration number">
            </div>

            <div class="form-group">
                <label class="form-label" for="modalNotes">Internal Notes (Kept Private)</label>
                <textarea name="internal_notes" id="modalNotes" class="form-control-adm" rows="2" placeholder="Private internal observations..."></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn-adm btn-adm-outline" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-adm btn-adm-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(item) {
    document.getElementById('modalItemId').value = item.id;
    document.getElementById('modalItemTitle').textContent = 'Edit: ' + item.title;
    document.getElementById('modalCurrentValue').value = item.current_value || '';
    document.getElementById('modalDraftValue').value = item.draft_value || '';
    document.getElementById('modalStatus').value = item.status;
    document.getElementById('modalPubState').value = item.publication_state;
    document.getElementById('modalEvidence').value = item.evidence_ref || '';
    document.getElementById('modalNextAction').value = item.next_action || '';
    document.getElementById('modalNotes').value = item.internal_notes || '';

    const modal = document.getElementById('editItemModal');
    modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editItemModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
