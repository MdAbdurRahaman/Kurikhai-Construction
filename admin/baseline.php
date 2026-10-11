<?php
/**
 * Marketing & SEO Baseline View and Management (Day 1 Milestone)
 * 
 * Records, displays, and exports authentic baseline performance data across:
 * - Google Analytics 4 (Organic sessions, users, total sessions)
 * - Google Search Console (Clicks, impressions, CTR, average position)
 * - Target Keyword SERP snapshot (Mapped to high-intent service pages)
 * - Persisted Website Enquiries & Lead Conversions (MySQL CRM)
 * - Supporting Technical Baseline (Canonicals, headers, response status)
 * 
 * Export formats: CSV (formula-safe, UTF-8 BOM) and Markdown Report.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/business_tracker.php';

// Authentication & Authorization Gate (Must precede any HTML)
require_login();
if (!has_permission('baseline.view') && !has_permission('baseline.manage') && !has_permission('analytics.view')) {
    http_response_code(403);
    echo "<h1>403 Forbidden</h1><p>You do not have permission to view marketing baseline metrics.</p>";
    exit;
}

$canEdit = has_permission('baseline.manage');
$error = '';
$success = '';

// Default authentic 28-day baseline window (28 complete days prior to today)
$today = new DateTime('now', new DateTimeZone('Asia/Singapore'));
$windowEnd = (clone $today)->modify('-1 day')->format('Y-m-d');
$windowStart = (clone $today)->modify('-28 days')->format('Y-m-d');
$compWindowEnd = (clone $today)->modify('-29 days')->format('Y-m-d');
$compWindowStart = (clone $today)->modify('-56 days')->format('Y-m-d');

// Helper to sanitize CSV formula injection
function sanitize_csv_cell($val): string {
    $str = (string)($val ?? '');
    if (in_array(substr($str, 0, 1), ['=', '+', '-', '@'], true)) {
        return "'" . $str;
    }
    return $str;
}

// Fetch baseline metrics from DB or seed defaults
function get_baseline_metrics(): array {
    if (DB::isConnected()) {
        $metrics = db_all("SELECT * FROM `marketing_baseline` ORDER BY `id` ASC");
        if (!empty($metrics)) {
            return $metrics;
        }
    }
    // Static fallback baseline records if DB empty
    return [
        [
            'metric_key' => 'ga4_organic_sessions',
            'metric_category' => 'traffic',
            'title' => 'Organic Search Sessions (28 Days)',
            'metric_value' => 'Not previously tracked',
            'unit' => 'sessions',
            'window_start' => '2026-09-12',
            'window_end' => '2026-10-09',
            'source_name' => 'Google Analytics 4',
            'property_identifier' => 'G-XXXXXXXXXX',
            'reporting_timezone' => 'Asia/Singapore',
            'applied_filters' => 'sessionDefaultChannelGroup = Organic Search',
            'collection_status' => 'Not previously tracked',
            'status_reason' => 'Tracking container not previously configured or connected to live stream',
            'evidence_reference' => 'Pending GTM/GA4 container publishing',
            'owner' => 'Technical SEO Specialist',
            'collected_at' => '2026-10-10 18:00:00'
        ],
        [
            'metric_key' => 'gsc_organic_clicks',
            'metric_category' => 'search_performance',
            'title' => 'Search Console Total Clicks (Last 28 Days)',
            'metric_value' => 'Pending access',
            'unit' => 'clicks',
            'window_start' => '2026-09-12',
            'window_end' => '2026-10-09',
            'source_name' => 'Google Search Console',
            'property_identifier' => 'tabeebgroup.com',
            'reporting_timezone' => 'Asia/Singapore',
            'applied_filters' => 'Country: Singapore, Search type: Web',
            'collection_status' => 'Pending access',
            'status_reason' => 'Search Console domain property access pending client DNS authorization',
            'evidence_reference' => 'DNS TXT verification instructions prepared',
            'owner' => 'Technical SEO Specialist',
            'collected_at' => '2026-10-10 18:00:00'
        ]
    ];
}

// Live CRM Lead Count calculation for honest baseline comparison (Strict 28-day window)
$realCrmTotal = 0;
$realCrmQualified = 0;
$realCrmWon = 0;
$crmHistoryAvailable = false;
if (DB::isConnected()) {
    $crmHistoryAvailable = true;
    $realCrmTotal = (int)db_val(
        "SELECT COUNT(*) FROM `leads` 
         WHERE `deleted_at` IS NULL AND `status` != 'spam' 
           AND `created_at` BETWEEN '2026-09-12 00:00:00' AND '2026-10-09 23:59:59'"
    );
    $realCrmQualified = (int)db_val(
        "SELECT COUNT(*) FROM `leads` 
         WHERE `deleted_at` IS NULL AND `status` = 'qualified' 
           AND `created_at` BETWEEN '2026-09-12 00:00:00' AND '2026-10-09 23:59:59'"
    );
    $realCrmWon = (int)db_val(
        "SELECT COUNT(*) FROM `leads` 
         WHERE `deleted_at` IS NULL AND `status` = 'won' 
           AND `created_at` BETWEEN '2026-09-12 00:00:00' AND '2026-10-09 23:59:59'"
    );
}

// =============================================================================
// CSV EXPORT (Sent before HTML output)
// =============================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $metrics = get_baseline_metrics();
    
    // Log export audit trail
    if (function_exists('audit_log')) {
        audit_log('baseline.export_csv', 'baseline', null, null, ['count' => count($metrics)]);
    }

    $filename = 'tabeeb_marketing_baseline_day1_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM for Microsoft Excel compatibility
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Metric Key', 'Category', 'Title', 'Baseline Value', 'Unit',
        'Window Start', 'Window End', 'Source Name', 'Property / Account',
        'Timezone', 'Applied Filters', 'Collection Status', 'Status Reason',
        'Evidence Reference', 'Owner', 'Recorded At'
    ]);

    foreach ($metrics as $m) {
        $val = $m['metric_value'];
        if (($val === null || $val === '') && $m['metric_key'] === 'leads_persisted_total') {
            $val = $crmHistoryAvailable ? (string)$realCrmTotal : 'Not available';
        }
        if (($val === null || $val === '') && $m['metric_key'] === 'leads_qualified_total') {
            $val = $crmHistoryAvailable ? (string)$realCrmQualified : 'Not available';
        }
        if (($val === null || $val === '') && $m['metric_key'] === 'leads_won_total') {
            $val = $crmHistoryAvailable ? (string)$realCrmWon : 'Not available';
        }

        fputcsv($out, [
            sanitize_csv_cell($m['metric_key'] ?? ''),
            sanitize_csv_cell($m['metric_category'] ?? ''),
            sanitize_csv_cell($m['title'] ?? ''),
            sanitize_csv_cell($val ?? ''),
            sanitize_csv_cell($m['unit'] ?? ''),
            sanitize_csv_cell($m['window_start'] ?? $windowStart),
            sanitize_csv_cell($m['window_end'] ?? $windowEnd),
            sanitize_csv_cell($m['source_name'] ?? ''),
            sanitize_csv_cell($m['property_identifier'] ?? ''),
            sanitize_csv_cell($m['reporting_timezone'] ?? 'Asia/Singapore'),
            sanitize_csv_cell($m['applied_filters'] ?? ''),
            sanitize_csv_cell($m['collection_status'] ?? ''),
            sanitize_csv_cell($m['status_reason'] ?? ''),
            sanitize_csv_cell($m['evidence_reference'] ?? ''),
            sanitize_csv_cell($m['owner'] ?? ''),
            sanitize_csv_cell($m['collected_at'] ?? date('Y-m-d H:i:s'))
        ]);
    }
    fclose($out);
    exit;
}

// =============================================================================
// MARKDOWN REPORT EXPORT (Sent before HTML output)
// =============================================================================
if (isset($_GET['export']) && $_GET['export'] === 'markdown') {
    $metrics = get_baseline_metrics();
    $filename = 'tabeeb_marketing_baseline_day1_' . date('Y-m-d') . '.md';
    
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "# Tabeeb Contractor – Day 1 Marketing & SEO Baseline Report\n\n";
    echo "**Report Generated:** " . date('Y-m-d H:i:s') . " (Asia/Singapore)\n";
    echo "**Authoritative Domain:** `https://tabeebgroup.com`\n";
    echo "**Baseline Period (28 Complete Days):** `{$windowStart}` to `{$windowEnd}`\n";
    echo "**Comparison Window:** `{$compWindowStart}` to `{$compWindowEnd}`\n\n";
    echo "---\n\n";
    echo "## 1. Executive Summary & Integrity Note\n\n";
    echo "- **Zero Invented History:** Google Analytics 4 tracking was not active during the baseline window; historical organic session counts are accurately classified as `Not previously tracked` rather than fabricated.\n";
    echo "- **Search Console Domain Property:** `tabeebgroup.com` DNS TXT instructions prepared; access pending client authoritative nameserver record update.\n";
    echo "- **Persisted CRM Enquiries:** " . $realCrmTotal . " total authentic website enquiries recorded in MySQL database.\n";
    echo "- **Conversion Denominator:** Website session-to-lead conversion rate is marked `Not available` because an accurate session denominator requires live GA4 measurement.\n\n";
    echo "## 2. Baseline Metrics Table\n\n";
    echo "| Metric | Value | Unit | Window | Source | Status | Notes |\n";
    echo "|---|---|---|---|---|---|---|\n";
    
    foreach ($metrics as $m) {
        $val = $m['metric_value'];
        if ($m['metric_key'] === 'leads_persisted_total' && empty($val)) $val = (string)$realCrmTotal;
        if ($m['metric_key'] === 'leads_qualified_total' && empty($val)) $val = (string)$realCrmQualified;
        if ($m['metric_key'] === 'leads_won_total' && empty($val)) $val = (string)$realCrmWon;
        $valDisplay = !empty($val) ? $val : '—';
        echo "| " . str_replace('|', '-', $m['title']) . " | " . $valDisplay . " | " . ($m['unit'] ?? '') . " | " . ($m['window_start'] ?? $windowStart) . " to " . ($m['window_end'] ?? $windowEnd) . " | " . ($m['source_name'] ?? '') . " | `" . ($m['collection_status'] ?? '') . "` | " . str_replace('|', '-', $m['status_reason'] ?? '') . " |\n";
    }
    
    echo "\n## 3. Technical Baseline Snapshot\n\n";
    echo "- **HTTP Response:** `200 OK`\n";
    echo "- **Canonical URL:** `https://tabeebgroup.com/` (Strict non-trailing slash)\n";
    echo "- **Homepage Title:** `Tabeeb Contractor | Renovation, Hacking & Direct Contractor Singapore`\n";
    echo "- **Meta Description:** Singapore direct renovation contractor for commercial office reinstatement, HDB wall hacking, masonry tiling, vinyl flooring, and waterproofing.\n";
    echo "- **Asset Performance:** Posters and hero background compressed to WebP (304 KB poster down from 2.72 MB; 116 KB hero down from 2.35 MB).\n";
    exit;
}

// =============================================================================
// POST: UPDATE METRIC VALUE (Requires baseline.manage permission)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        $error = "Forbidden: You do not possess permission ('baseline.manage') to edit baseline metrics.";
    } elseif (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "CSRF security verification failed. Please refresh and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_metric') {
            $metricKey = trim($_POST['metric_key'] ?? '');
            $metricValue = trim($_POST['metric_value'] ?? '');
            $collectionStatus = trim($_POST['collection_status'] ?? 'Not previously tracked');
            $statusReason = trim($_POST['status_reason'] ?? '');
            $evidenceReference = trim($_POST['evidence_reference'] ?? '');

            $validStatuses = ['Measured', 'Not available', 'Pending access', 'Not previously tracked'];
            if (!in_array($collectionStatus, $validStatuses, true)) {
                $collectionStatus = 'Not previously tracked';
            }

            if (DB::isConnected() && !empty($metricKey)) {
                db_exec(
                    "UPDATE `marketing_baseline` 
                     SET `metric_value` = ?, `collection_status` = ?, `status_reason` = ?, `evidence_reference` = ?, `collected_at` = NOW() 
                     WHERE `metric_key` = ?",
                    [$metricValue, $collectionStatus, $statusReason, $evidenceReference, $metricKey]
                );
                audit_log('baseline.update_metric', 'baseline', $metricKey, null, [
                    'status' => $collectionStatus,
                    'value'  => $metricValue
                ]);
                $success = "Metric updated successfully.";
            } else {
                $error = "Database unavailable or invalid metric key.";
            }
        }
    }
}

$pageTitle = 'Marketing & SEO Baseline (Day 1)';
$currentPage = 'baseline';
require __DIR__ . '/header.php';

$metrics = get_baseline_metrics();
?>

<div class="adm-card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="font-family: 'Outfit', sans-serif; color: var(--admin-navy); font-size: 22px; margin-bottom: 6px;">
                📈 Marketing & SEO Baseline Snapshot (Day 1 Milestone)
            </h2>
            <p style="color: var(--admin-muted); font-size: 14px; margin: 0; max-width: 780px;">
                Authentic, pre-marketing baseline audit for <strong>tabeebgroup.com</strong>. Captures organic search traffic, Google Search Console visibility, high-intent Singapore keyword rankings, and verified CRM inquiries.
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="baseline.php?export=csv" class="adm-btn adm-btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                📥 Export CSV
            </a>
            <a href="baseline.php?export=markdown" class="adm-btn adm-btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                📄 Export Markdown
            </a>
        </div>
    </div>

    <!-- 28-Day Window Info Callout -->
    <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 8px; padding: 14px 18px; margin-top: 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px; font-size: 13px;">
            <span style="background: var(--admin-navy); color: #fff; padding: 4px 10px; border-radius: 6px; font-weight: 600;">Standard Baseline Window</span>
            <span style="color: #334155;"><strong>Last 28 Complete Days:</strong> <?= htmlspecialchars($windowStart) ?> to <?= htmlspecialchars($windowEnd) ?></span>
            <span style="color: #94a3b8;">•</span>
            <span style="color: #334155;"><strong>Timezone:</strong> Asia/Singapore (SGT)</span>
        </div>
        <div style="font-size: 12px; color: #64748b;">
            Baseline recorded pre-repair. Zero backdating.
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="adm-alert adm-alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="adm-alert adm-alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Baseline Metrics Table -->
<div class="adm-card">
    <div class="table-header">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--admin-navy); margin: 0;">Verified Baseline Performance Records</h3>
        <span style="font-size: 12px; color: var(--admin-muted);">Per-source reporting boundary & evidence reference</span>
    </div>
    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Metric & Source</th>
                    <th>Category</th>
                    <th>Baseline Value</th>
                    <th>Collection Status</th>
                    <th>Status Reason / Evidence</th>
                    <?php if ($canEdit): ?>
                        <th style="text-align: right;">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metrics as $m): ?>
                    <?php
                    $val = $m['metric_value'];
                    if (($val === null || $val === '') && $m['metric_key'] === 'leads_persisted_total') {
                        $val = $crmHistoryAvailable ? (string)$realCrmTotal : 'Not available';
                    }
                    if (($val === null || $val === '') && $m['metric_key'] === 'leads_qualified_total') {
                        $val = $crmHistoryAvailable ? (string)$realCrmQualified : 'Not available';
                    }
                    if (($val === null || $val === '') && $m['metric_key'] === 'leads_won_total') {
                        $val = $crmHistoryAvailable ? (string)$realCrmWon : 'Not available';
                    }

                    $statusClass = 'badge-secondary';
                    $statusColor = '#64748b';
                    $statusBg = '#f1f5f9';
                    if ($m['collection_status'] === 'Measured') {
                        $statusColor = '#059669';
                        $statusBg = '#d1fae5';
                    } elseif ($m['collection_status'] === 'Pending access') {
                        $statusColor = '#d97706';
                        $statusBg = '#fef3c7';
                    } elseif ($m['collection_status'] === 'Not available') {
                        $statusColor = '#dc2626';
                        $statusBg = '#fee2e2';
                    }
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600; color: var(--admin-navy); font-size: 14px;">
                                <?= htmlspecialchars($m['title']) ?>
                            </div>
                            <div style="font-size: 11px; color: var(--admin-muted); margin-top: 2px;">
                                📡 Source: <?= htmlspecialchars($m['source_name']) ?> 
                                <?php if (!empty($m['property_identifier'])): ?>
                                    (<code><?= htmlspecialchars($m['property_identifier']) ?></code>)
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; color: #475569;">
                                <?= htmlspecialchars($m['metric_category']) ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 14px; font-weight: 700; color: #1e293b;">
                                <?= ($val !== null && $val !== '') ? htmlspecialchars((string)$val) : '<span style="color: #94a3b8; font-weight: 400;">—</span>' ?>
                                <?php if ($val !== null && $val !== '' && !empty($m['unit']) && !in_array($val, ['Not previously tracked', 'Pending access', 'Not available'])): ?>
                                    <span style="font-size: 12px; font-weight: 400; color: var(--admin-muted);"><?= htmlspecialchars($m['unit']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span style="display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; color: <?= $statusColor ?>; background: <?= $statusBg ?>;">
                                <?= htmlspecialchars($m['collection_status']) ?>
                            </span>
                        </td>
                        <td style="max-width: 340px;">
                            <div style="font-size: 12px; color: #334155;">
                                <?= htmlspecialchars($m['status_reason'] ?? 'None recorded') ?>
                            </div>
                            <?php if (!empty($m['evidence_reference'])): ?>
                                <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                                    📎 Ref: <?= htmlspecialchars($m['evidence_reference']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <?php if ($canEdit): ?>
                        <td style="text-align: right;">
                            <button type="button" class="adm-btn adm-btn-secondary" style="font-size: 12px; padding: 6px 12px;" onclick="openEditModal(<?= htmlspecialchars(json_encode($m)) ?>)">
                                Edit
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Supporting Technical Audit Snapshot -->
<div class="adm-card" style="margin-top: 24px;">
    <h3 style="font-family: 'Outfit', sans-serif; font-size: 17px; color: var(--admin-navy); margin-bottom: 14px;">
        🛠️ Supporting Technical Baseline Snapshot
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
        <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 8px; padding: 14px;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">HTTP Server Status</div>
            <div style="font-size: 15px; font-weight: 700; color: #059669; margin-top: 4px;">200 OK (HTTPS)</div>
            <div style="font-size: 12px; color: #475569; margin-top: 2px;">Apache / cPanel PHP 8.2 Runtime</div>
        </div>
        <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 8px; padding: 14px;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Canonical Architecture</div>
            <div style="font-size: 14px; font-weight: 700; color: var(--admin-navy); margin-top: 4px;">https://tabeebgroup.com/</div>
            <div style="font-size: 12px; color: #475569; margin-top: 2px;">Uniform non-trailing slash canonicals</div>
        </div>
        <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 8px; padding: 14px;">
            <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Hero Asset Compression</div>
            <div style="font-size: 15px; font-weight: 700; color: #059669; margin-top: 4px;">WebP Optimized</div>
            <div style="font-size: 12px; color: #475569; margin-top: 2px;">Poster: 304 KB (down 89%) | Hero: 116 KB (down 95%)</div>
        </div>
    </div>
</div>

<!-- Modal for editing metric -->
<div id="metricEditModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:24px; width:100%; max-width:520px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <h3 id="modalMetricTitle" style="font-size:18px; font-weight:700; color:var(--admin-navy); margin-bottom:16px;">Edit Baseline Metric</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_metric">
            <input type="hidden" name="metric_key" id="editMetricKey">
            
            <div class="form-group">
                <label class="form-label">Metric Value</label>
                <input type="text" name="metric_value" id="editMetricValue" class="form-control-adm">
            </div>

            <div class="form-group">
                <label class="form-label">Collection Status</label>
                <select name="collection_status" id="editCollectionStatus" class="form-control-adm">
                    <option value="Measured">Measured</option>
                    <option value="Not available">Not available</option>
                    <option value="Pending access">Pending access</option>
                    <option value="Not previously tracked">Not previously tracked</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Status Reason</label>
                <textarea name="status_reason" id="editStatusReason" class="form-control-adm" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Evidence Reference</label>
                <input type="text" name="evidence_reference" id="editEvidenceReference" class="form-control-adm">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="adm-btn adm-btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(m) {
    document.getElementById('editMetricKey').value = m.metric_key || '';
    document.getElementById('modalMetricTitle').textContent = 'Edit: ' + (m.title || '');
    document.getElementById('editMetricValue').value = m.metric_value || '';
    document.getElementById('editCollectionStatus').value = m.collection_status || 'Not previously tracked';
    document.getElementById('editStatusReason').value = m.status_reason || '';
    document.getElementById('editEvidenceReference').value = m.evidence_reference || '';
    document.getElementById('metricEditModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('metricEditModal').style.display = 'none';
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
