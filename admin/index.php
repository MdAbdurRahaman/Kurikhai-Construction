<?php
$pageTitle = 'Overview & Analytics';
require_once __DIR__ . '/header.php';

$allPosts = get_all_posts(false);
$publishedPosts = array_filter($allPosts, fn($p) => ($p['status'] ?? '') === 'published');
$draftPosts = array_filter($allPosts, fn($p) => ($p['status'] ?? '') === 'draft');
$allUsers = get_all_users();
$allServices = get_all_services();

$recentPosts = array_slice($allPosts, 0, 5);
?>

<!-- Metric Stats Row -->
<div class="stat-grid">
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= count($allPosts) ?></div>
            <div class="stat-label">Total Articles</div>
        </div>
        <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">📝</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= count($publishedPosts) ?></div>
            <div class="stat-label">Published Live</div>
        </div>
        <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">🟢</div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= count($draftPosts) ?></div>
            <div class="stat-label">Drafts</div>
        </div>
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">✏️</div>
    </div>
    <?php if (is_super_admin()): ?>
    <a href="users.php" style="text-decoration: none; color: inherit; display: block;">
        <div class="stat-card">
            <div>
                <div class="stat-val"><?= count($allUsers) ?></div>
                <div class="stat-label">Admin & Editors</div>
            </div>
            <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">👥</div>
        </div>
    </a>
    <?php else: ?>
    <div class="stat-card">
        <div>
            <div class="stat-val" style="font-size: 18px; text-transform: capitalize; padding-top: 4px;"><?= htmlspecialchars($currentUser['role'] ?? 'Editor') ?></div>
            <div class="stat-label">Your Role</div>
        </div>
        <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">👤</div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div>
            <div class="stat-val"><?= count($allServices) ?></div>
            <div class="stat-label">Core Services</div>
        </div>
        <div class="stat-icon" style="background: #fef8ee; color: #c2963f;">🏢</div>
    </div>
</div>

<!-- Social Share Simulator Box (Special Feature solving the client's WhatsApp issue!) -->
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
                        <optgroup label="Blog Posts & Guides">
                            <?php foreach ($allPosts as $p): ?>
                                <option value="blog"
                                        data-title="<?= htmlspecialchars($p['title']) ?> | Tabeeb Contractor"
                                        data-desc="<?= htmlspecialchars($p['excerpt']) ?>"
                                        data-img="/<?= ltrim($p['image'], '/') ?>"
                                        data-url="<?= $baseUrl ?>/blog/<?= htmlspecialchars($p['slug']) ?>">
                                    📝 Blog: <?= htmlspecialchars($p['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Direct Shareable Link:</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="simShareUrl" class="form-control-adm" readonly style="background: #f8fafc; font-family: monospace; font-size: 13px;">
                        <button type="button" class="btn-adm btn-adm-outline" id="simCopyBtn">Copy</button>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                    <a href="#" id="simWhatsAppBtn" target="_blank" rel="noopener noreferrer" class="btn-adm" style="background: #25d366; color: #fff;">
                        <span>📲 Share to WhatsApp</span>
                    </a>
                    <a href="#" id="simFacebookBtn" target="_blank" rel="noopener noreferrer" class="btn-adm" style="background: #1877f2; color: #fff;">
                        <span>🌐 Share to Facebook</span>
                    </a>
                </div>
            </div>

            <!-- Visual WhatsApp Preview Mockup -->
            <div>
                <label class="form-label">WhatsApp Chat Card Preview:</label>
                <div style="background: #efeae2; border-radius: 12px; padding: 16px; border: 1px solid #d1d7db; max-width: 380px;">
                    <div style="background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                        <img id="simPreviewImg" src="/images/hero-bg.jpg" alt="Preview" style="width: 100%; height: 160px; object-fit: cover;">
                        <div style="padding: 12px 14px; background: #f0f2f5;">
                            <div id="simPreviewTitle" style="font-weight: 700; font-size: 14px; color: #111b21; line-height: 1.3; margin-bottom: 4px;">
                                Title Preview
                            </div>
                            <div id="simPreviewDesc" style="font-size: 12px; color: #667781; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 6px;">
                                Description Preview
                            </div>
                            <div style="font-size: 11px; color: #8696a0; text-transform: lowercase;">
                                <?= parse_url($baseUrl, PHP_URL_HOST) ?? 'tabeebgroup.com' ?>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: right; margin-top: 6px; font-size: 11px; color: #667781;">
                        10:48 PM ✓✓
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Recent Blog Posts Table -->
<div class="table-card">
    <div class="table-header">
        <div>
            <h3>Recent Blog Articles</h3>
            <span style="font-size: 13px; color: var(--admin-text-muted);">Manage articles, view drafts, and update content</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="posts.php" class="btn-adm btn-adm-outline">View All (<?= count($allPosts) ?>)</a>
            <a href="post-edit.php" class="btn-adm btn-adm-primary">+ New Article</a>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th style="width: 70px;">Cover</th>
                    <th>Article Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentPosts)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">
                            No articles written yet. Click "+ New Article" to post your first guide!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentPosts as $p): 
                        $img = (strpos($p['image'], 'http') === 0) ? $p['image'] : ('/' . ltrim($p['image'], '/'));
                    ?>
                        <tr>
                            <td>
                                <img src="<?= $img ?>" alt="Cover" style="width: 50px; height: 38px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0;">
                            </td>
                            <td>
                                <a href="post-edit.php?id=<?= urlencode($p['id']) ?>" style="font-weight: 600; color: var(--admin-navy); text-decoration: none;">
                                    <?= htmlspecialchars($p['title']) ?>
                                </a>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                    /blog/<?= htmlspecialchars($p['slug']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge-adm badge-adm-blue"><?= htmlspecialchars($p['category']) ?></span>
                            </td>
                            <td style="color: #475569;">
                                <?= htmlspecialchars($p['author'] ?? 'Admin') ?>
                            </td>
                            <td>
                                <?php if (($p['status'] ?? '') === 'published'): ?>
                                    <span class="badge-adm badge-adm-success">Published</span>
                                <?php else: ?>
                                    <span class="badge-adm badge-adm-warning">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #64748b; font-size: 12px;">
                                <?= format_date($p['created_at']) ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <?php if (($p['status'] ?? '') === 'published'): ?>
                                        <a href="/blog/<?= htmlspecialchars($p['slug']) ?>" target="_blank" class="btn-adm btn-adm-outline" style="padding: 5px 9px; font-size: 12px;" title="View Live Article">
                                            👁️ View
                                        </a>
                                    <?php endif; ?>
                                    <a href="post-edit.php?id=<?= urlencode($p['id']) ?>" class="btn-adm btn-adm-outline" style="padding: 5px 9px; font-size: 12px;" title="Edit Article">
                                        ✏️ Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const selector = document.getElementById('shareItemSelector');
        const urlInput = document.getElementById('simShareUrl');
        const copyBtn = document.getElementById('simCopyBtn');
        const waBtn = document.getElementById('simWhatsAppBtn');
        const fbBtn = document.getElementById('simFacebookBtn');
        const pImg = document.getElementById('simPreviewImg');
        const pTitle = document.getElementById('simPreviewTitle');
        const pDesc = document.getElementById('simPreviewDesc');

        const updatePreview = () => {
            const opt = selector.options[selector.selectedIndex];
            if (!opt) return;

            const title = opt.getAttribute('data-title');
            const desc = opt.getAttribute('data-desc');
            const img = opt.getAttribute('data-img');
            const url = opt.getAttribute('data-url');

            urlInput.value = url;
            pTitle.textContent = title;
            pDesc.textContent = desc;
            pImg.src = img;

            const waText = encodeURIComponent(title + "\n" + url);
            waBtn.href = "https://wa.me/?text=" + waText;
            fbBtn.href = "https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(url);
        };

        selector.addEventListener('change', updatePreview);
        updatePreview();

        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(urlInput.value);
                showToast('Link copied to clipboard!');
            } catch (e) {
                prompt('Copy share link:', urlInput.value);
            }
        });
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
