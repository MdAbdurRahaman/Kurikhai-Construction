<?php
$pageTitle = 'Overview & Analytics';
require_once __DIR__ . '/header.php';

$allPosts = get_all_posts(false);
$allServices = get_all_services();

// Real Database Metrics
$dbConnected = DB::isConnected();
$leadsToday = 0;
$leadsWeek = 0;
$totalLeads = 0;
$leadsQualified = 0;
$siteVisitsScheduled = 0;
$quotationsSent = 0;
$leadsWon = 0;
$pipelineValue = 0.0;
$recentLeads = [];
$serviceDistribution = [];
$statusCounts = [];

if ($dbConnected) {
    $leadsToday = (int)db_val("SELECT COUNT(*) FROM `leads` WHERE DATE(`created_at`) = CURDATE() AND `deleted_at` IS NULL");
    $leadsWeek = (int)db_val("SELECT COUNT(*) FROM `leads` WHERE `created_at` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND `deleted_at` IS NULL");
    $totalLeads = (int)db_val("SELECT COUNT(*) FROM `leads` WHERE `deleted_at` IS NULL");
    $leadsQualified = (int)db_val("SELECT COUNT(*) FROM `leads` WHERE `status` = 'qualified' AND `deleted_at` IS NULL");
    $siteVisitsScheduled = (int)db_val("SELECT COUNT(*) FROM `leads` WHERE `status` = 'site_visit_scheduled' AND `deleted_at` IS NULL");
    $quotationsSent = (int)db_val("SELECT COUNT(*) FROM `leads` WHERE `status` = 'quotation_sent' AND `deleted_at` IS NULL");
    $leadsWon = (int)db_val("SELECT COUNT(*) FROM `leads` WHERE `status` = 'won' AND `deleted_at` IS NULL");
    $pipelineValue = (float)db_val("SELECT COALESCE(SUM(`estimated_deal_value`), 0) FROM `leads` WHERE `status` NOT IN ('lost', 'spam', 'archived') AND `deleted_at` IS NULL");

    $recentLeads = db_all(
        "SELECT l.*, u.name AS assigned_name 
         FROM `leads` l 
         LEFT JOIN `users` u ON l.assigned_user_id = u.id 
         WHERE l.deleted_at IS NULL 
         ORDER BY l.created_at DESC LIMIT 5"
    );

    $serviceRows = db_all(
        "SELECT `service_requested`, COUNT(*) AS count 
         FROM `leads` WHERE `deleted_at` IS NULL 
         GROUP BY `service_requested` ORDER BY count DESC LIMIT 6"
    );
    foreach ($serviceRows as $sr) {
        $serviceDistribution[$sr['service_requested']] = (int)$sr['count'];
    }

    $statusRows = db_all(
        "SELECT `status`, COUNT(*) AS count 
         FROM `leads` WHERE `deleted_at` IS NULL 
         GROUP BY `status`"
    );
    foreach ($statusRows as $str) {
        $statusCounts[$str['status']] = (int)$str['count'];
    }
}

// Business Tracker Metrics
$trackerMetrics = BusinessTracker::getMetrics();
?>

<!-- Metric Stats Grid -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $leadsToday ?></div>
            <div class="stat-label">Inquiries Today</div>
        </div>
        <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">⚡</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $totalLeads ?></div>
            <div class="stat-label">Total Inquiries (All Time)</div>
        </div>
        <div class="stat-icon" style="background: #f0fdf4; color: #16a34a;">🎯</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $siteVisitsScheduled ?></div>
            <div class="stat-label">Site Visits Scheduled</div>
        </div>
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">📐</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $quotationsSent ?></div>
            <div class="stat-label">Quotations Preparing/Sent</div>
        </div>
        <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">📑</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val">$<?= number_format($pipelineValue, 0) ?></div>
            <div class="stat-label">Pipeline Value (SGD)</div>
        </div>
        <div class="stat-icon" style="background: #ecfdf5; color: #059669;">💰</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= $trackerMetrics['confirmed'] ?> / <?= $trackerMetrics['total'] ?></div>
            <div class="stat-label">Client Facts Verified</div>
        </div>
        <div class="stat-icon" style="background: #fff1f2; color: #e11d48;">📋</div>
    </div>
</div>

<!-- Quick Action & Verification Bar -->
<div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px; background: #ffffff; border: 1px solid var(--admin-border); border-radius: var(--admin-radius); padding: 18px 24px; box-shadow: var(--admin-shadow); display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h4 style="font-size: 15px; color: var(--admin-navy); margin-bottom: 4px;">🎯 Live CRM & Leads Funnel</h4>
            <p style="font-size: 13px; color: var(--admin-text-muted); margin: 0;">Same-origin endpoint receiving inquiries directly from index and modal forms.</p>
        </div>
        <a href="leads.php" class="btn-adm btn-adm-primary" style="font-size: 13px;">Manage Leads ➔</a>
    </div>

    <div style="flex: 1; min-width: 300px; background: #ffffff; border: 1px solid var(--admin-border); border-radius: var(--admin-radius); padding: 18px 24px; box-shadow: var(--admin-shadow); display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h4 style="font-size: 15px; color: var(--admin-navy); margin-bottom: 4px;">📋 Client Information Tracker</h4>
            <p style="font-size: 13px; color: var(--admin-text-muted); margin: 0;">Verified: <?= $trackerMetrics['confirmed'] ?> &bull; Pending: <?= $trackerMetrics['pending'] ?> &bull; Published: <?= $trackerMetrics['published'] ?></p>
        </div>
        <a href="business-info.php" class="btn-adm btn-adm-outline" style="font-size: 13px;">Review Facts ➔</a>
    </div>
</div>

<!-- Recent Leads Table -->
<div class="table-card" style="margin-bottom: 35px;">
    <div class="table-header">
        <div>
            <h3>Recent Customer Inquiries</h3>
            <p style="font-size: 13px; color: var(--admin-text-muted); margin-top: 2px;">Latest requests received through website quote forms</p>
        </div>
        <a href="leads.php" class="btn-adm btn-adm-outline" style="font-size: 12px; padding: 6px 14px;">View All Inquiries ➔</a>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Customer Name</th>
                    <th>Phone / WhatsApp</th>
                    <th>Service Requested</th>
                    <th>Budget Range</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLeads)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                            No incoming leads yet. As visitors submit quote requests on the website, they will appear here instantly.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLeads as $lead): 
                        $badgeColor = match($lead['status']) {
                            'new' => '#ef4444',
                            'contacted' => '#0284c7',
                            'qualified' => '#8b5cf6',
                            'site_visit_scheduled' => '#f59e0b',
                            'quotation_sent' => '#0d9488',
                            'won' => '#10b981',
                            'lost' => '#64748b',
                            default => '#94a3b8'
                        };
                    ?>
                        <tr>
                            <td><strong style="color: var(--admin-navy);"><?= htmlspecialchars($lead['lead_number']) ?></strong></td>
                            <td><?= htmlspecialchars($lead['name']) ?></td>
                            <td>
                                <a href="https://wa.me/<?= preg_replace('/[^\d]/', '', $lead['phone']) ?>" target="_blank" style="color: #16a34a; font-weight: 600; text-decoration: none;">
                                    <?= htmlspecialchars($lead['phone']) ?> 💬
                                </a>
                            </td>
                            <td><?= htmlspecialchars($lead['service_requested']) ?></td>
                            <td><?= htmlspecialchars($lead['estimated_budget'] ?? 'Unspecified') ?></td>
                            <td>
                                <span style="background: <?= $badgeColor ?>18; color: <?= $badgeColor ?>; border: 1px solid <?= $badgeColor ?>40; padding: 3px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                    <?= str_replace('_', ' ', $lead['status']) ?>
                                </span>
                            </td>
                            <td style="color: #64748b;"><?= date('M j, g:i a', strtotime($lead['created_at'])) ?></td>
                            <td style="text-align: right;">
                                <a href="lead-detail.php?id=<?= $lead['id'] ?>" class="btn-adm btn-adm-outline" style="padding: 4px 10px; font-size: 12px;">Review ➔</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Social Share Simulator Box (Client WhatsApp Feature) -->
<div class="table-card" style="margin-bottom: 35px; border-left: 4px solid var(--admin-accent);">
    <div class="table-header" style="background: #f0fdfa;">
        <div>
            <h3 style="color: #0f766e; display: flex; align-items: center; gap: 8px;">
                <span>📲</span> Social Media Card Simulator & Share Hub
            </h3>
            <p style="font-size: 13px; color: #115e59; margin-top: 4px;">
                Select any service or blog post to preview exactly how it appears when shared on WhatsApp, Facebook, or Instagram!
            </p>
        </div>
        <div>
            <a href="services.php" class="btn-adm btn-adm-accent" style="font-size: 12px; padding: 6px 14px;">
                All Service Links ➔
            </a>
        </div>
    </div>

    <div style="padding: 24px;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;" class="preview-layout-grid">
            
            <!-- Selector Controls -->
            <div>
                <div class="form-group">
                    <label class="form-label" for="shareItemSelector">Choose Item to Share:</label>
                    <select id="shareItemSelector" class="form-control-adm" style="font-weight: 500;">
                        <optgroup label="Core Contracting Services (Individual Links)">
                            <?php foreach ($allServices as $srv): ?>
                                <option value="service" 
                                        data-title="<?= htmlspecialchars($srv['og_title'] ?? $srv['name']) ?>"
                                        data-desc="<?= htmlspecialchars($srv['og_description'] ?? $srv['overview']) ?>"
                                        data-img="/<?= ltrim($srv['image'], '/') ?>"
                                        data-url="<?= $baseUrl ?>/service/<?= htmlspecialchars($srv['slug']) ?>">
                                    🏢 Service: <?= htmlspecialchars($srv['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php if (!empty($allPosts)): ?>
                        <optgroup label="Latest Renovation Guides & Articles">
                            <?php foreach ($allPosts as $bp): ?>
                                <option value="blog"
                                        data-title="<?= htmlspecialchars($bp['title']) ?> | Tabeeb Contractor"
                                        data-desc="<?= htmlspecialchars($bp['excerpt'] ?? '') ?>"
                                        data-img="/<?= ltrim($bp['image'], '/') ?>"
                                        data-url="<?= $baseUrl ?>/blog/<?= htmlspecialchars($bp['slug']) ?>">
                                    📝 Blog: <?= htmlspecialchars($bp['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 8px; padding: 14px; margin-top: 15px;">
                    <div style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">Selected Share URL:</div>
                    <input type="text" id="shareTargetUrl" class="form-control-adm" readonly style="background: #fff; font-family: monospace; font-size: 12px; margin-bottom: 12px;">

                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="btn-adm btn-adm-outline" id="simCopyBtn" style="flex: 1; font-size: 12px;">📋 Copy Link</button>
                        <a id="simWaBtn" href="#" target="_blank" class="btn-adm" style="flex: 1; background: #25d366; color: #fff; font-size: 12px; text-align: center; text-decoration: none;">💬 WhatsApp</a>
                        <a id="simFbBtn" href="#" target="_blank" class="btn-adm" style="flex: 1; background: #1877f2; color: #fff; font-size: 12px; text-align: center; text-decoration: none;">📘 Facebook</a>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Preview Mockup -->
            <div>
                <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 10px;">
                    📱 Live WhatsApp Bubble Preview
                </div>
                <div style="background: #efeae2; border-radius: 14px; padding: 16px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);">
                    <div style="background: #ffffff; border-radius: 10px; max-width: 320px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.15); margin-left: auto;">
                        <div style="height: 160px; background: #e2e8f0; overflow: hidden; position: relative;">
                            <img id="simPreviewImg" src="" alt="Card" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div style="padding: 10px 12px;">
                            <div id="simPreviewTitle" style="font-size: 14px; font-weight: 700; color: #111b21; line-height: 1.3; margin-bottom: 4px;"></div>
                            <div id="simPreviewDesc" style="font-size: 12px; color: #667781; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 6px;"></div>
                            <div id="simPreviewDomain" style="font-size: 11px; color: #8696a0; text-transform: lowercase;">tabeebgroup.com</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selector = document.getElementById('shareItemSelector');
    const targetUrlInput = document.getElementById('shareTargetUrl');
    const previewImg = document.getElementById('simPreviewImg');
    const previewTitle = document.getElementById('simPreviewTitle');
    const previewDesc = document.getElementById('simPreviewDesc');
    const simWaBtn = document.getElementById('simWaBtn');
    const simFbBtn = document.getElementById('simFbBtn');
    const simCopyBtn = document.getElementById('simCopyBtn');

    function updatePreview() {
        if (!selector) return;
        const opt = selector.options[selector.selectedIndex];
        if (!opt) return;

        const title = opt.getAttribute('data-title') || '';
        const desc = opt.getAttribute('data-desc') || '';
        const img = opt.getAttribute('data-img') || '';
        const url = opt.getAttribute('data-url') || '';

        targetUrlInput.value = url;
        previewImg.src = img;
        previewTitle.textContent = title;
        previewDesc.textContent = desc;

        simWaBtn.href = 'https://wa.me/?text=' + encodeURIComponent(title + '\n' + url);
        simFbBtn.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
    }

    if (selector) {
        selector.addEventListener('change', updatePreview);
        updatePreview();
    }

    if (simCopyBtn) {
        simCopyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(targetUrlInput.value).then(() => {
                const orig = simCopyBtn.textContent;
                simCopyBtn.textContent = '✓ Copied!';
                setTimeout(() => simCopyBtn.textContent = orig, 2000);
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
