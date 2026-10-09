<?php
$pageTitle = 'Security Audit Trail';
require_once __DIR__ . '/header.php';

require_super_admin();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;
$filterAction = trim($_GET['action'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($filterAction)) {
    $where[] = "`action` LIKE ?";
    $params[] = "%{$filterAction}%";
}

$whereSql = implode(' AND ', $where);

$totalCount = 0;
$logs = [];

if (DB::isConnected()) {
    $totalCount = (int)db_val("SELECT COUNT(*) FROM `audit_logs` WHERE {$whereSql}", $params);
    $logs = db_all(
        "SELECT a.*, u.name AS user_name, u.username 
         FROM `audit_logs` a 
         LEFT JOIN `users` u ON a.user_id = u.id 
         WHERE {$whereSql} 
         ORDER BY a.created_at DESC 
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );
}

$totalPages = max(1, ceil($totalCount / $limit));
?>

<div class="table-card" style="margin-bottom: 25px;">
    <div style="padding: 18px 24px;">
        <form method="GET" action="audit-logs.php" style="display: flex; gap: 12px; align-items: center;">
            <div style="flex: 1; max-width: 320px;">
                <input type="text" name="action" class="form-control-adm" placeholder="Filter by action (e.g. auth, user, lead)..." value="<?= htmlspecialchars($filterAction) ?>">
            </div>
            <button type="submit" class="btn-adm btn-adm-primary">Filter Logs</button>
            <a href="audit-logs.php" class="btn-adm btn-adm-outline">Reset</a>
        </form>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <div>
            <h3>Administrative & Security Activity Log (<?= number_format($totalCount) ?> Records)</h3>
            <p style="font-size: 13px; color: var(--admin-text-muted); margin-top: 2px;">
                Immutable audit record of all administrative logins, permission checks, and content updates.
            </p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>IP Hash</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                            No audit log events recorded matching the filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $badgeColor = match(explode('.', $log['action'])[0] ?? '') {
                            'auth' => '#3b82f6',
                            'lead' => '#10b981',
                            'user' => '#8b5cf6',
                            'post' => '#f59e0b',
                            'business_info' => '#0d9488',
                            default => '#64748b'
                        };
                    ?>
                        <tr>
                            <td style="font-size: 12px; color: #64748b;">
                                <?= date('M j, Y g:i:s a', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <?php if (!empty($log['user_name'])): ?>
                                    <strong><?= htmlspecialchars($log['user_name']) ?></strong>
                                    <div style="font-size: 11px; color: #64748b;">@<?= htmlspecialchars($log['username']) ?></div>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">System / Anonymous</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background: <?= $badgeColor ?>18; color: <?= $badgeColor ?>; border: 1px solid <?= $badgeColor ?>40; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; font-family: monospace;">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: #475569;">
                                <?= htmlspecialchars($log['entity_type']) ?><?= !empty($log['entity_id']) ? ' #' . htmlspecialchars($log['entity_id']) : '' ?>
                            </td>
                            <td style="font-size: 11px; color: #64748b; font-family: monospace;">
                                <?= substr($log['ip_hash'] ?? 'N/A', 0, 12) ?>...
                            </td>
                            <td style="font-size: 12px; color: #334155; max-width: 300px; word-break: break-word;">
                                <?php if (!empty($log['after_state'])): ?>
                                    <pre style="margin: 0; font-size: 11px; background: #f8fafc; padding: 4px 6px; border-radius: 4px; overflow-x: auto;"><?= htmlspecialchars($log['after_state']) ?></pre>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
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
                <a href="audit-logs.php?page=<?= $i ?><?= !empty($filterAction) ? '&action=' . urlencode($filterAction) : '' ?>" 
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
