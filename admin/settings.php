<?php
$pageTitle = 'Website & Integration Settings';
require_once __DIR__ . '/header.php';

require_super_admin();

$msg = '';
$error = '';

// Helper to fetch or save setting
function get_setting(string $key, string $default = ''): string {
    if (DB::isConnected()) {
        $val = db_val("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1", [$key]);
        return ($val !== false && $val !== null) ? (string)$val : $default;
    }
    return $default;
}

function set_setting(string $group, string $key, string $val): void {
    if (DB::isConnected()) {
        db_exec(
            "INSERT INTO settings (setting_group, setting_key, setting_value, updated_at) 
             VALUES (?, ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
            [$group, $key, $val]
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Invalid security token.';
    } else {
        // Analytics & Tracking
        set_setting('analytics', 'ga4_id', trim($_POST['ga4_id'] ?? ''));
        set_setting('analytics', 'gtm_id', trim($_POST['gtm_id'] ?? ''));
        set_setting('analytics', 'gsc_token', trim($_POST['gsc_token'] ?? ''));
        set_setting('analytics', 'turnstile_key', trim($_POST['turnstile_key'] ?? ''));

        // SEO & Privacy
        set_setting('seo', 'meta_title_suffix', trim($_POST['meta_title_suffix'] ?? ' | Tabeeb Contractor Singapore'));
        set_setting('privacy', 'retention_days', (string)max(30, (int)($_POST['retention_days'] ?? 365)));
        set_setting('privacy', 'privacy_contact_email', trim($_POST['privacy_contact_email'] ?? 'info@tabeebgroup.com'));

        audit_log('settings.updated', 'settings', null, null, ['user' => current_user()['username']]);
        $msg = 'Platform settings and integrations updated successfully!';
    }
}

$ga4Id = get_setting('ga4_id', getenv('GA4_MEASUREMENT_ID') ?: '');
$gtmId = get_setting('gtm_id', getenv('GTM_CONTAINER_ID') ?: '');
$gscToken = get_setting('gsc_token', getenv('GSC_VERIFICATION_TOKEN') ?: 'googlea9c88d1e38989379');
$turnstileKey = get_setting('turnstile_key', getenv('TURNSTILE_SITE_KEY') ?: '');
$titleSuffix = get_setting('meta_title_suffix', ' | Tabeeb Contractor Singapore');
$retentionDays = get_setting('retention_days', '365');
$privacyEmail = get_setting('privacy_contact_email', 'info@tabeebgroup.com');
?>

<?php if (!empty($msg)): ?>
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<form method="POST" action="settings.php">
    <?= csrf_input_field() ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; align-items: start;">
        
        <!-- Left Column: Analytics & Integrations -->
        <div class="table-card">
            <div class="table-header">
                <h3>External Analytics & Search Console</h3>
            </div>
            <div style="padding: 24px;">
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" for="ga4_id" style="margin-bottom: 0;">Google Analytics 4 (GA4)</label>
                        <span style="font-size: 11px; font-weight: 700; color: <?= !empty($ga4Id) ? '#16a34a' : '#94a3b8' ?>;">
                            <?= !empty($ga4Id) ? '● Configured' : '○ Not configured' ?>
                        </span>
                    </div>
                    <input type="text" name="ga4_id" id="ga4_id" class="form-control-adm" placeholder="G-XXXXXXXXXX" value="<?= htmlspecialchars($ga4Id) ?>">
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Measurement ID for privacy-compliant visit tracking.</div>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" for="gtm_id" style="margin-bottom: 0;">Google Tag Manager (GTM)</label>
                        <span style="font-size: 11px; font-weight: 700; color: <?= !empty($gtmId) ? '#16a34a' : '#94a3b8' ?>;">
                            <?= !empty($gtmId) ? '● Configured' : '○ Not configured' ?>
                        </span>
                    </div>
                    <input type="text" name="gtm_id" id="gtm_id" class="form-control-adm" placeholder="GTM-XXXXXXX" value="<?= htmlspecialchars($gtmId) ?>">
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" for="gsc_token" style="margin-bottom: 0;">Google Search Console Token</label>
                        <span style="font-size: 11px; font-weight: 700; color: <?= !empty($gscToken) ? '#16a34a' : '#94a3b8' ?>;">
                            <?= !empty($gscToken) ? '● Active' : '○ Pending' ?>
                        </span>
                    </div>
                    <input type="text" name="gsc_token" id="gsc_token" class="form-control-adm" placeholder="googlea9c88d1e38989379" value="<?= htmlspecialchars($gscToken) ?>">
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Site verification HTML identifier for search indexing.</div>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" for="turnstile_key" style="margin-bottom: 0;">Cloudflare Turnstile (Optional)</label>
                        <span style="font-size: 11px; font-weight: 700; color: <?= !empty($turnstileKey) ? '#16a34a' : '#94a3b8' ?>;">
                            <?= !empty($turnstileKey) ? '● Configured' : '○ Optional' ?>
                        </span>
                    </div>
                    <input type="text" name="turnstile_key" id="turnstile_key" class="form-control-adm" placeholder="0x4AAAAAA..." value="<?= htmlspecialchars($turnstileKey) ?>">
                </div>
            </div>
        </div>

        <!-- Right Column: SEO & Privacy Policies -->
        <div class="table-card">
            <div class="table-header">
                <h3>SEO & PDPA Data Governance</h3>
            </div>
            <div style="padding: 24px;">
                <div class="form-group">
                    <label class="form-label" for="meta_title_suffix">Global Page Title Suffix</label>
                    <input type="text" name="meta_title_suffix" id="meta_title_suffix" class="form-control-adm" value="<?= htmlspecialchars($titleSuffix) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="retention_days">Lead Data Retention Period (Days)</label>
                    <input type="number" name="retention_days" id="retention_days" class="form-control-adm" value="<?= htmlspecialchars($retentionDays) ?>">
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Singapore PDPA compliance: Inquiries older than this threshold are scheduled for secure archival.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="privacy_contact_email">Designated Privacy Contact Email</label>
                    <input type="email" name="privacy_contact_email" id="privacy_contact_email" class="form-control-adm" value="<?= htmlspecialchars($privacyEmail) ?>">
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-radius: 8px; padding: 14px; margin-top: 20px;">
                    <div style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">📋 Single-Source Company Facts</div>
                    <p style="font-size: 13px; color: #64748b; margin: 0 0 10px;">
                        UEN, addresses, hotline numbers, and Google Maps links are governed through the <strong>Business Info Tracker</strong> to prevent unverified publication.
                    </p>
                    <a href="business-info.php" class="btn-adm btn-adm-outline" style="font-size: 12px; padding: 4px 10px;">Go to Business Tracker ➔</a>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn-adm btn-adm-primary" style="width: 100%; padding: 12px;">Save Platform Settings</button>
                </div>
            </div>
        </div>

    </div>
</form>

<?php require_once __DIR__ . '/footer.php'; ?>
