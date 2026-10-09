<?php
$pageTitle = 'Leads & Inquiry Management';
require_once __DIR__ . '/header.php';

require_permission('leads.view');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    require_permission('leads.export');

    $exportSql = "SELECT `lead_number`, `name`, `phone`, `email`, `preferred_contact_method`, 
                         `service_requested`, `property_type`, `estimated_budget`, `project_description`, 
                         `status`, `priority`, `source`, `created_at` 
                  FROM `leads` WHERE `deleted_at` IS NULL ORDER BY `created_at` DESC";
    $exportRows = db_all($exportSql);

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

// Filtering & Search
$search = trim($_GET['q'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterService = trim($_GET['service'] ?? '');
$filterPriority = trim($_GET['priority'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = ["`deleted_at` IS NULL"];
$params = [];

if (!empty($search)) {
    $where[] = "(`lead_number` LIKE ? OR `name` LIKE ? OR `phone` LIKE ? OR `email` LIKE ? OR `project_description` LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

if (!empty($filterStatus)) {
    $where[] = "`status` = ?";
    $params[] = $filterStatus;
}

if (!empty($filterService)) {
    $where[] = "`service_requested` = ?";
    $params[] = $filterService;
}

if (!empty($filterPriority)) {
    $where[] = "`priority` = ?";
    $params[] = $filterPriority;
}

$whereClause = implode(' AND ', $where);

$totalCount = 0;
$leads = [];
$servicesList = [];

if (DB::isConnected()) {
    $totalCount = (int)db_val("SELECT COUNT(*) FROM `leads` WHERE {$whereClause}", $params);
    $leads = db_all(
        "SELECT l.*, u.name AS assigned_name 
         FROM `leads` l 
         LEFT JOIN `users` u ON l.assigned_user_id = u.id 
         WHERE {$whereClause} 
         ORDER BY l.created_at DESC 
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );
    $servicesList = db_all("SELECT DISTINCT `service_requested` FROM `leads` WHERE `service_requested` != '' ORDER BY `service_requested` ASC");
}

$totalPages = max(1, ceil($totalCount / $limit));
?>

<!-- Filter & Actions Bar -->
<div class="table-card" style="margin-bottom: 25px;">
    <div style="padding: 20px 24px;">
        <form method="GET" action="leads.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 2; min-width: 220px;">
                <input type="text" name="q" class="form-control-adm" placeholder="Search by name, phone, lead #, or scope..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div style="flex: 1; min-width: 150px;">
                <select name="status" class="form-control-adm">
                    <option value="">All Statuses</option>
                    <?php 
                    $statuses = ['new', 'contacted', 'qualified', 'site_visit_scheduled', 'site_visit_completed', 'quotation_preparing', 'quotation_sent', 'negotiation', 'won', 'lost', 'spam', 'archived'];
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
                <a href="leads.php" class="btn-adm btn-adm-outline">Reset</a>
            </div>

            <div style="margin-left: auto;">
                <a href="leads.php?export=csv<?= !empty($search) ? '&q=' . urlencode($search) : '' ?><?= !empty($filterStatus) ? '&status=' . urlencode($filterStatus) : '' ?>" class="btn-adm btn-adm-accent" style="font-size: 13px;">
                    📥 Export CSV
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Leads List Table -->
<div class="table-card">
    <div class="table-header">
        <div>
            <h3>All Leads (<?= number_format($totalCount) ?> Total)</h3>
            <p style="font-size: 13px; color: var(--admin-text-muted); margin-top: 2px;">Showing Page <?= $page ?> of <?= $totalPages ?></p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Lead #</th>
                    <th>Customer Name</th>
                    <th>Phone / WhatsApp</th>
                    <th>Service Scope</th>
                    <th>Budget</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assignee</th>
                    <th>Date</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 50px; color: #94a3b8;">
                            No leads matched your filter criteria.
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
                            <td>
                                <a href="lead-detail.php?id=<?= $lead['id'] ?>" style="color: var(--admin-navy); font-weight: 700; text-decoration: none;">
                                    <?= htmlspecialchars($lead['lead_number']) ?>
                                </a>
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
                                <a href="lead-detail.php?id=<?= $lead['id'] ?>" class="btn-adm btn-adm-outline" style="padding: 4px 10px; font-size: 12px;">
                                    Details ➔
                                </a>
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
                <a href="leads.php?page=<?= $i ?><?= !empty($search) ? '&q=' . urlencode($search) : '' ?><?= !empty($filterStatus) ? '&status=' . urlencode($filterStatus) : '' ?>" 
                   class="btn-adm <?= $page === $i ? 'btn-adm-primary' : 'btn-adm-outline' ?>" 
                   style="padding: 4px 10px; font-size: 12px;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
