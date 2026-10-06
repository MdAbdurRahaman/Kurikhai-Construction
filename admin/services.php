<?php
$pageTitle = 'Service Social Share Links Hub';
require_once __DIR__ . '/header.php';

$services = get_all_services();
$baseUrl = get_base_url();
?>

<div class="table-card" style="border-left: 4px solid var(--admin-accent);">
    <div class="table-header" style="background: #f0fdfa;">
        <div>
            <h3 style="color: #0f766e; display: flex; align-items: center; gap: 8px;">
                <span>🔗</span> Direct Service Shareable Links
            </h3>
            <p style="font-size: 13px; color: #115e59; margin-top: 4px;">
                Every service now has its own unique URL with dedicated OpenGraph titles and images. Share these links on WhatsApp and Facebook to display the specific service card instead of the generic homepage!
            </p>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th style="width: 70px;">Cover</th>
                    <th>Service & Social Card Title</th>
                    <th>Category</th>
                    <th>Direct Dedicated URL</th>
                    <th style="text-align: right;">Share & Copy Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $srv): 
                    $srvUrl = $baseUrl . '/' . htmlspecialchars($srv['slug']) . '/';
                    $img = (strpos($srv['image'], 'http') === 0) ? $srv['image'] : ('/' . ltrim($srv['image'], '/'));
                    $waText = urlencode($srv['og_title'] . "\n" . $srvUrl);
                    $fbUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($srvUrl);
                ?>
                    <tr>
                        <td>
                            <img src="<?= $img ?>" alt="Service" style="width: 56px; height: 42px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0;">
                        </td>
                        <td>
                            <div style="font-weight: 700; color: var(--admin-navy); font-size: 14px; margin-bottom: 2px;">
                                <?= $srv['icon'] ?> <?= htmlspecialchars($srv['name']) ?>
                            </div>
                            <div style="font-size: 12px; color: #0284c7; font-weight: 500;">
                                <?= htmlspecialchars($srv['og_title']) ?>
                            </div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 2px; max-width: 400px; line-height: 1.4;">
                                <?= htmlspecialchars(substr($srv['og_description'] ?? $srv['overview'], 0, 110)) ?>...
                            </div>
                        </td>
                        <td>
                            <span class="badge-adm badge-adm-blue"><?= htmlspecialchars($srv['category_name']) ?></span>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="text" value="<?= $srvUrl ?>" readonly class="form-control-adm srv-url-input" style="font-family: monospace; font-size: 11px; padding: 4px 8px; width: 220px; background: #f8fafc;">
                                <button type="button" class="btn-adm btn-adm-outline copy-srv-btn" data-url="<?= $srvUrl ?>" style="padding: 4px 8px; font-size: 11px;" title="Copy to clipboard">
                                    Copy
                                </button>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 6px;">
                                <a href="https://wa.me/?text=<?= $waText ?>" target="_blank" rel="noopener noreferrer" class="btn-adm" style="background: #25d366; color: #fff; padding: 5px 9px; font-size: 12px;" title="Share this service on WhatsApp">
                                    📲 WhatsApp
                                </a>
                                <a href="<?= $fbUrl ?>" target="_blank" rel="noopener noreferrer" class="btn-adm" style="background: #1877f2; color: #fff; padding: 5px 9px; font-size: 12px;" title="Share this service on Facebook">
                                    🌐 Facebook
                                </a>
                                <a href="/<?= htmlspecialchars($srv['slug']) ?>/" target="_blank" class="btn-adm btn-adm-outline" style="padding: 5px 9px; font-size: 12px;" title="View Live Page">
                                    👁️
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const copyBtns = document.querySelectorAll('.copy-srv-btn');
        copyBtns.forEach(btn => {
            btn.addEventListener('click', async () => {
                const url = btn.getAttribute('data-url');
                try {
                    await navigator.clipboard.writeText(url);
                    showToast('Service link copied! Ready to paste into WhatsApp or Facebook.');
                } catch (e) {
                    prompt('Copy link:', url);
                }
            });
        });
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
