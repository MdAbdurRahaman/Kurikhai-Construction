<?php
require_once dirname(__DIR__) . '/includes/data.php';

require_login();

// Check view permissions before emitting any HTML output
if (!has_permission('leads.view') && !has_permission('leads.view_assigned')) {
    http_response_code(403);
    require_once __DIR__ . '/header.php';
    echo '<div style="padding: 40px; text-align: center;"><h2>403 Forbidden</h2><p>You do not have permission to view leads.</p><a href="/admin/index.php" class="btn-adm btn-adm-primary">Return to Dashboard</a></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// Scoping: If user only has assigned leads permission, restrict query scope
$currentUser = current_user();
$isAssignedOnly = !has_permission('leads.view') && has_permission('leads.view_assigned');
$assignedUserId = (int)($currentUser['id'] ?? 0);

require_once dirname(__DIR__) . '/includes/lead_management.php';
$trash = ($_GET['trash'] ?? '') === '1';
if ($trash) require_super_admin();
$bulkMessage = '';
$bulkError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $bulkError = 'Invalid security token. Refresh and try again.';
    } else {
        try {
            if (($_POST['bulk_action'] ?? '') === 'purge' && ($_POST['confirm_delete'] ?? '') !== 'DELETE') {
                throw new InvalidArgumentException('Type DELETE to confirm permanent deletion.');
            }
            $count = lead_bulk_action(is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [], (string)($_POST['bulk_action'] ?? ''), (string)($_POST['bulk_value'] ?? ''), $trash);
            $_SESSION['lead_bulk_message'] = $count . ' lead(s) updated successfully.';
            header('Location: leads.php?' . http_build_query($_GET));
            exit;
        } catch (Throwable $e) {
            $bulkError = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Could not update leads. Please try again.';
            if ($e instanceof PDOException) $bulkError = 'Could not update leads. No changes were saved.';
        }
    }
}
$bulkMessage = $_SESSION['lead_bulk_message'] ?? '';
unset($_SESSION['lead_bulk_message']);
$columnDefinitions = [
    'lead_number' => ['Lead #', 'l.lead_number'],
    'customer' => ['Customer name / email', "CONCAT(l.name, ' ', COALESCE(l.email, ''))"],
    'phone' => ['Phone / WhatsApp', 'l.phone'],
    'scope' => ['Service scope', 'l.service_requested'],
    'budget' => ['Budget', "COALESCE(l.estimated_budget, 'Unspecified')"],
    'priority' => ['Priority', 'l.priority'],
    'status' => ['Status', 'l.status'],
    'assignee' => ['Assignee', "COALESCE(u.name, 'Unassigned')"],
    'date' => ['Date (YYYY-MM-DD)', 'l.created_at'],
];
$columnFilters = [];
foreach ($columnDefinitions as $key => $definition) {
    $columnFilters[$key] = substr(is_string($_GET['col'][$key] ?? null) ? $_GET['col'][$key] : '', 0, 200);
}
$columnValues = [];
foreach ($columnDefinitions as $key => $definition) {
    $values = $_GET['values'][$key] ?? [];
    $columnValues[$key] = is_array($values) ? array_values(array_unique(array_slice(array_filter($values, 'is_string'), 0, 200))) : [];
}
$sort = is_string($_GET['sort'] ?? null) && isset($columnDefinitions[$_GET['sort']]) ? $_GET['sort'] : 'date';
$direction = ($_GET['direction'] ?? '') === 'asc' ? 'ASC' : 'DESC';
$orderBy = $columnDefinitions[$sort][1] . ' ' . $direction . ', l.id DESC';
function lead_list_url(array $changes = []): string {
    return 'leads.php?' . http_build_query(array_merge($_GET, $changes));
}

// Filtering & Search
$search = trim($_GET['q'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterService = trim($_GET['service'] ?? '');
$filterPriority = trim($_GET['priority'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Centralized scoped query builder (strictly l.-prefixed columns to prevent ambiguous SQL join errors)
$where = [$trash ? "l.`deleted_at` IS NOT NULL" : "l.`deleted_at` IS NULL"];
$params = [];

if ($isAssignedOnly) {
    $where[] = "l.`assigned_user_id` = ?";
    $params[] = $assignedUserId;
}

if (!empty($search)) {
    $where[] = "(l.`lead_number` LIKE ? OR l.`name` LIKE ? OR l.`phone` LIKE ? OR l.`email` LIKE ? OR l.`project_description` LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if (!empty($filterStatus)) {
    $where[] = "l.`status` = ?";
    $params[] = $filterStatus;
}

if (!empty($filterService)) {
    $where[] = "l.`service_requested` = ?";
    $params[] = $filterService;
}

if (!empty($filterPriority)) {
    $where[] = "l.`priority` = ?";
    $params[] = $filterPriority;
}

foreach ($columnDefinitions as $key => $definition) {
    if ($columnValues[$key]) {
        $expression = $key === 'date' ? 'DATE(l.created_at)' : $definition[1];
        $where[] = $expression . ' IN (' . implode(',', array_fill(0, count($columnValues[$key]), '?')) . ')';
        $params = array_merge($params, $columnValues[$key]);
    }
    if ($columnFilters[$key] !== '') {
        $where[] = $definition[1] . " LIKE ? ESCAPE '='";
        $params[] = '%' . strtr($columnFilters[$key], ['=' => '==', '%' => '=%', '_' => '=_']) . '%';
    }
}
$whereClause = implode(' AND ', $where);


// Handle CSV Export before any HTML is sent to the client
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    require_permission('leads.export');

    $exportSql = "SELECT l.`lead_number`, l.`name`, l.`phone`, l.`email`, l.`preferred_contact_method`,
                         l.`service_requested`, l.`property_type`, l.`estimated_budget`, l.`project_description`,
                         l.`status`, l.`priority`, l.`source`, l.`created_at`
                  FROM `leads` l LEFT JOIN users u ON l.assigned_user_id = u.id WHERE {$whereClause} ORDER BY {$orderBy}";
    $exportParams = $params;
    $exportRows = db_all($exportSql, $exportParams);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tabeeb_leads_' . date('Ymd_His') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fputs($out, "\xEF\xBB\xBF");

    // CSV Headers
    fputcsv($out, [
        'Lead Number', 'Customer Name', 'Phone', 'Email', 'Preferred Contact',
        'Service Requested', 'Property Type', 'Budget', 'Project Description',
        'Status', 'Priority', 'Source', 'Submitted At'
    ]);

    foreach ($exportRows as $r) {
        fputcsv($out, [
            escape_csv_cell($r['lead_number']),
            escape_csv_cell($r['name']),
            escape_csv_cell($r['phone']),
            escape_csv_cell($r['email']),
            escape_csv_cell($r['preferred_contact_method']),
            escape_csv_cell($r['service_requested']),
            escape_csv_cell($r['property_type']),
            escape_csv_cell($r['estimated_budget']),
            escape_csv_cell($r['project_description']),
            escape_csv_cell($r['status']),
            escape_csv_cell($r['priority']),
            escape_csv_cell($r['source']),
            escape_csv_cell($r['created_at'])
        ]);
    }
    fclose($out);
    audit_log('leads.exported', 'lead', null, null, ['count' => count($exportRows)]);
    exit;
}

$pageTitle = $trash ? 'Lead Recycle Bin' : 'Leads & Inquiry Management';
require_once __DIR__ . '/header.php';

$totalCount = 0;
$leads = [];
$filterOptions = [];

if (DB::isConnected()) {
    $totalCount = (int)db_val("SELECT COUNT(*) FROM `leads` l LEFT JOIN users u ON l.assigned_user_id = u.id WHERE {$whereClause}", $params);
    $page = min($page, max(1, (int)ceil($totalCount / $limit)));
    $offset = ($page - 1) * $limit;
    $leads = db_all(
        "SELECT l.*, u.name AS assigned_name
         FROM `leads` l
         LEFT JOIN `users` u ON l.assigned_user_id = u.id
         WHERE {$whereClause}
         ORDER BY {$orderBy}
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );
    $optionScope = $trash ? 'l.deleted_at IS NOT NULL' : 'l.deleted_at IS NULL';
    $optionParams = [];
    if ($isAssignedOnly) { $optionScope .= ' AND l.assigned_user_id = ?'; $optionParams[] = $assignedUserId; }
    foreach ($columnDefinitions as $key => $definition) {
        $expression = $key === 'date' ? 'DATE(l.created_at)' : $definition[1];
        $filterOptions[$key] = array_column(db_all("SELECT DISTINCT $expression AS filter_value FROM leads l LEFT JOIN users u ON l.assigned_user_id=u.id WHERE $optionScope ORDER BY filter_value LIMIT 200", $optionParams), 'filter_value');
        $filterOptions[$key] = array_values(array_unique(array_merge(array_map(fn($v) => (string)$v, $filterOptions[$key]), $columnValues[$key])));
    }
}

$totalPages = max(1, ceil($totalCount / $limit));
$bulkAssignees = has_permission('leads.assign') ? db_all("SELECT id, name FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY name") : [];
$canBulkEdit = has_permission('leads.edit') || has_permission('leads.edit_assigned');
$canBulk = $trash || $canBulkEdit || has_permission('leads.assign') || has_permission('leads.delete');
?>

<?php if ($isAssignedOnly): ?>
<div class="table-card" style="padding: 18px 24px; margin-bottom: 20px;">
    <strong>Your assigned enquiries</strong>
    <p>Only enquiries assigned to you are shown. Ask your administrator or sales manager to assign new enquiries to your account.</p>
</div>
<?php endif; ?>
<?php if ($bulkMessage): ?><div role="status" class="table-card" style="padding:16px;margin-bottom:16px;"><?= htmlspecialchars($bulkMessage) ?></div><?php endif; ?>
<?php if ($bulkError): ?><div role="alert" class="table-card" style="padding:16px;margin-bottom:16px;color:#b91c1c;"><?= htmlspecialchars($bulkError) ?></div><?php endif; ?>
<?php if (is_super_admin()): ?><p style="margin-bottom:16px;"><a class="btn-adm btn-adm-outline" href="leads.php<?= $trash ? '' : '?trash=1' ?>"><?= $trash ? 'Back to active leads' : 'Open recycle bin' ?></a></p><?php endif; ?>
<!-- Filter & Actions Bar -->
<div class="table-card" style="margin-bottom: 25px;">
    <div style="padding: 20px 24px;">
        <form id="lead-filters" method="GET" action="leads.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <?php if ($trash): ?><input type="hidden" name="trash" value="1"><?php endif; ?>
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <input type="hidden" name="direction" value="<?= strtolower($direction) ?>">
            <div style="flex: 2; min-width: 220px;">
                <input type="text" name="q" class="form-control-adm" placeholder="Search by name, phone, lead #, or scope..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div style="flex: 1; min-width: 150px;">
                <select name="status" class="form-control-adm">
                    <option value="">All Statuses</option>
                    <?php
                    $statuses = ['new', 'unreviewed', 'contacted', 'qualified', 'site_visit_scheduled', 'site_visit_completed', 'quotation_preparing', 'quotation_sent', 'negotiation', 'won', 'lost', 'spam', 'archived'];
                    foreach ($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <select name="priority" class="form-control-adm">
                    <option value="">All Priorities</option>
                    <option value="urgent" <?= $filterPriority === 'urgent' ? 'selected' : '' ?>>🔴 Urgent</option>
                    <option value="high" <?= $filterPriority === 'high' ? 'selected' : '' ?>>🟠 High</option>
                    <option value="medium" <?= $filterPriority === 'medium' ? 'selected' : '' ?>>🔵 Medium</option>
                    <option value="low" <?= $filterPriority === 'low' ? 'selected' : '' ?>>⚪ Low</option>
                </select>
            </div>

            <div>
                <button type="submit" class="btn-adm btn-adm-primary">Filter</button>
                <a href="leads.php<?= $trash ? '?trash=1' : '' ?>" class="btn-adm btn-adm-outline">Reset</a>
            </div>

            <?php if (has_permission('leads.export')): ?>
            <div style="margin-left: auto;">
                <a href="<?= htmlspecialchars(lead_list_url(['export' => 'csv', 'page' => 1])) ?>" class="btn-adm btn-adm-accent" style="font-size: 13px;">
                    📥 Export CSV
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Bulk selection is limited to the current page; filters apply across all records. -->
<?php if ($canBulk): ?>
<form id="lead-bulk" method="POST" action="<?= htmlspecialchars(lead_list_url()) ?>" class="table-card" style="padding:16px;margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
    <?= csrf_input_field() ?>
    <strong id="selection-count" aria-live="polite">0 selected on this page</strong>
    <select name="bulk_action" id="bulk-action" aria-label="Bulk action" class="form-control-adm" style="width:auto;">
        <option value="">Choose action</option>
        <?php if ($trash): ?>
        <option value="restore">Restore selected</option><option value="purge">Permanently delete selected</option>
        <?php else: ?>
        <?php if ($canBulkEdit): ?><option value="status">Change status</option><option value="priority">Change priority</option><?php endif; ?>
        <?php if (has_permission('leads.assign')): ?><option value="assign">Assign staff</option><?php endif; ?>
        <?php if (has_permission('leads.delete')): ?><option value="trash">Move to recycle bin</option><?php endif; ?>
        <?php endif; ?>
    </select>
    <select id="bulk-value" name="bulk_value" aria-label="New value" class="form-control-adm" style="width:auto;" hidden disabled></select>
    <label id="purge-confirm" hidden>Type DELETE <input name="confirm_delete" autocomplete="off" aria-label="Type DELETE to permanently delete" class="form-control-adm"></label>
    <button id="bulk-submit" class="btn-adm btn-adm-primary" disabled>Apply to selected</button>
    <?php if ($trash): ?><span>Permanent deletion also removes linked notes, activity and queued notifications.</span><?php endif; ?>
</form>
<?php endif; ?>
<!-- Leads List Table -->
<div class="table-card">
    <div class="table-header">
        <div>
            <h3><?= $trash ? 'Recycle Bin' : ($isAssignedOnly ? 'My Assigned Leads' : 'All Leads') ?> (<?= number_format($totalCount) ?> Total)</h3>
            <p style="font-size: 13px; color: var(--admin-text-muted); margin-top: 2px;">Showing Page <?= $page ?> of <?= $totalPages ?></p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <?php if ($canBulk): ?><th><input type="checkbox" id="select-page" aria-label="Select all leads on this page"></th><?php endif; ?>
                    <?php foreach ($columnDefinitions as $key => $definition): ?>
                    <th>
                        <a href="<?= htmlspecialchars(lead_list_url(['sort' => $key, 'direction' => $sort === $key && $direction === 'ASC' ? 'desc' : 'asc', 'page' => 1])) ?>"><?= htmlspecialchars($definition[0]) ?> <?= $sort === $key ? ($direction === 'ASC' ? '↑' : '↓') : '↕' ?></a>
                        <details style="font-weight:normal;margin-top:8px;">
                            <summary style="cursor:pointer;">Filter<?= $columnFilters[$key] !== '' || $columnValues[$key] ? ' • active' : '' ?></summary>
                            <input form="lead-filters" class="form-control-adm" style="min-width:150px;margin:8px 0;" name="col[<?= $key ?>]" value="<?= htmlspecialchars($columnFilters[$key]) ?>" placeholder="Contains…" aria-label="Filter <?= htmlspecialchars($definition[0]) ?>">
                            <div style="max-height:180px;overflow:auto;min-width:160px;">
                            <?php foreach ($filterOptions[$key] ?? [] as $option): ?>
                            <label style="display:flex;gap:6px;padding:5px;white-space:normal;">
                                <input form="lead-filters" type="checkbox" name="values[<?= $key ?>][]" value="<?= htmlspecialchars($option) ?>" <?= in_array($option, $columnValues[$key], true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($option === '' ? '(Blank)' : $option) ?>
                            </label>
                            <?php endforeach; ?>
                            </div>
                            <button form="lead-filters" type="submit" class="btn-adm btn-adm-primary" style="margin-top:8px;">Apply filter</button>
                        </details>
                    </th>
                    <?php endforeach; ?>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="<?= $canBulk ? 11 : 10 ?>" style="text-align: center; padding: 50px; color: #94a3b8;">
                            <?= $isAssignedOnly && $search === '' && $filterStatus === '' && $filterPriority === '' && $filterService === '' ? 'No enquiries have been assigned to you yet. Ask your administrator or sales manager to assign them.' : 'No leads matched your filter criteria.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead):
                        $badgeColor = match($lead['status']) {
                            'new' => '#ef4444',
                            'contacted' => '#0284c7',
                            'qualified' => '#8b5cf6',
                            'site_visit_scheduled' => '#f59e0b',
                            'site_visit_completed' => '#ea580c',
                            'quotation_preparing' => '#d97706',
                            'quotation_sent' => '#0d9488',
                            'negotiation' => '#3b82f6',
                            'won' => '#10b981',
                            'lost' => '#64748b',
                            default => '#94a3b8'
                        };
                        $priorityColor = match($lead['priority']) {
                            'urgent' => '#dc2626',
                            'high' => '#ea580c',
                            'medium' => '#0284c7',
                            default => '#64748b'
                        };
                    ?>
                        <tr>
                            <?php if ($canBulk): ?><td><input form="lead-bulk" class="lead-select" type="checkbox" name="ids[]" value="<?= (int)$lead['id'] ?>" aria-label="Select <?= htmlspecialchars($lead['lead_number']) ?>"></td><?php endif; ?>
                            <td>
                                <?php if (!$trash): ?><a href="lead-detail.php?id=<?= (int)$lead['id'] ?>"><?php endif; ?>
                                <?= htmlspecialchars($lead['lead_number']) ?>
                                <?php if (!$trash): ?></a><?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #0f172a;"><?= htmlspecialchars($lead['name']) ?></strong>
                                <?php if (!empty($lead['email'])): ?>
                                    <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($lead['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="https://wa.me/<?= preg_replace('/[^\d]/', '', $lead['phone']) ?>" target="_blank" style="color: #16a34a; font-weight: 600; text-decoration: none;">
                                    <?= htmlspecialchars($lead['phone']) ?> 💬
                                </a>
                            </td>
                            <td>
                                <span style="font-weight: 500; color: #1e293b;"><?= htmlspecialchars($lead['service_requested']) ?></span>
                                <?php if (!empty($lead['property_type']) && $lead['property_type'] !== 'Unspecified'): ?>
                                    <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($lead['property_type']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($lead['estimated_budget'] ?? 'Unspecified') ?></td>
                            <td>
                                <span style="color: <?= $priorityColor ?>; font-weight: 700; font-size: 12px; text-transform: uppercase;">
                                    <?= htmlspecialchars($lead['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="background: <?= $badgeColor ?>18; color: <?= $badgeColor ?>; border: 1px solid <?= $badgeColor ?>40; padding: 3px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                    <?= str_replace('_', ' ', $lead['status']) ?>
                                </span>
                            </td>
                            <td style="color: #475569;">
                                <?= htmlspecialchars($lead['assigned_name'] ?? 'Unassigned') ?>
                            </td>
                            <td style="color: #64748b; font-size: 12px;">
                                <?= date('M j, Y g:i a', strtotime($lead['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if (!$trash): ?><a href="lead-detail.php?id=<?= $lead['id'] ?>" class="btn-adm btn-adm-outline" style="padding: 4px 10px; font-size: 12px;">
                                    Details ➔
                                </a><?php else: ?>Deleted <?= htmlspecialchars($lead['deleted_at']) ?><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="padding: 16px 24px; border-top: 1px solid var(--admin-border); display: flex; justify-content: space-between; align-items: center;">
        <div style="font-size: 13px; color: var(--admin-text-muted);">
            Page <?= $page ?> of <?= $totalPages ?>
        </div>
        <div style="display: flex; gap: 6px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= htmlspecialchars(lead_list_url(['page' => $i])) ?>"
                   class="btn-adm <?= $page === $i ? 'btn-adm-primary' : 'btn-adm-outline' ?>"
                   style="padding: 4px 10px; font-size: 12px;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($canBulk): ?>
<script>
(() => {
    const boxes = [...document.querySelectorAll('.lead-select')];
    const all = document.getElementById('select-page');
    const action = document.getElementById('bulk-action');
    const value = document.getElementById('bulk-value');
    const button = document.getElementById('bulk-submit');
    const statuses = <?= json_encode($statuses) ?>;
    const staff = <?= json_encode(array_map(fn($u) => [(string)$u['id'], $u['name']], $bulkAssignees), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    function refresh() {
        const n = boxes.filter(b => b.checked).length;
        document.getElementById('selection-count').textContent = n + ' selected on this page';
        all.checked = boxes.length > 0 && n === boxes.length;
        all.indeterminate = n > 0 && n < boxes.length;
        button.disabled = !n || !action.value;
    }
    all.addEventListener('change', () => { boxes.forEach(b => b.checked = all.checked); refresh(); });
    boxes.forEach(b => b.addEventListener('change', refresh));
    action.addEventListener('change', () => {
        let options = [];
        if (action.value === 'status') options = statuses.map(s => [s, s.replaceAll('_', ' ')]);
        if (action.value === 'priority') options = ['low','medium','high','urgent'].map(s => [s,s]);
        if (action.value === 'assign') options = [['0','Unassigned'], ...staff];
        value.replaceChildren(...options.map(([key,label]) => new Option(label,key)));
        value.hidden = value.disabled = !options.length;
        document.getElementById('purge-confirm').hidden = action.value !== 'purge';
        refresh();
    });
    document.getElementById('lead-bulk').addEventListener('submit', e => {
        if (!boxes.some(b => b.checked) || !action.value) { e.preventDefault(); return; }
        if (['trash','purge'].includes(action.value) && !confirm(action.value === 'purge' ? 'Permanently delete selected leads and their linked notes and activity? This cannot be undone.' : 'Move selected leads to the super-admin recycle bin?')) e.preventDefault();
    });
    refresh();
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
