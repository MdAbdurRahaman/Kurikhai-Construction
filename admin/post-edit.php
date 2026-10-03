<?php
require_once dirname(__DIR__) . '/includes/data.php';
require_login();

$postId = $_GET['id'] ?? '';
$isEdit = !empty($postId);
$post = $isEdit ? get_post_by_id($postId) : null;

if ($isEdit && !$post) {
    header('Location: posts.php?error=' . urlencode('Article not found.'));
    exit;
}

$pageTitle = $isEdit ? 'Edit Blog Article' : 'Write New Blog Article';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrf)) {
        die('Invalid CSRF token.');
    }

    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    if ($category === '__new__' && !empty($_POST['custom_category'])) {
        $category = trim($_POST['custom_category']);
    }
    $author = trim($_POST['author'] ?? current_user()['name']);
    $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'draft';
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = $_POST['content'] ?? '';
    $readTime = trim($_POST['read_time'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $imagePath = trim($_POST['image_url'] ?? 'images/page-header-bg.jpg');

    // Handle Direct File Upload if provided
    if (isset($_FILES['cover_upload']) && $_FILES['cover_upload']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/images/blog/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $tmpName = $_FILES['cover_upload']['tmp_name'];
        $originalName = basename($_FILES['cover_upload']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowedExts)) {
            $newFileName = 'blog_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
            $destination = $uploadDir . $newFileName;
            
            if (move_uploaded_file($tmpName, $destination)) {
                $imagePath = 'images/blog/' . $newFileName;
            }
        }
    }

    $postData = [
        'id' => $postId,
        'title' => $title,
        'slug' => $slug,
        'category' => $category,
        'author' => $author,
        'status' => $status,
        'excerpt' => $excerpt,
        'content' => $content,
        'read_time' => $readTime,
        'tags' => $tags,
        'image' => $imagePath
    ];

    $saved = save_post($postData);
    if ($saved) {
        $msg = $isEdit ? 'Article updated successfully!' : 'New article published successfully!';
        header('Location: posts.php?msg=' . urlencode($msg));
        exit;
    } else {
        $error = 'Failed to save article. Please check permissions.';
    }
}

// Existing categories for dropdown
$allExistingPosts = get_all_posts(false);
$existingCategories = ['HDB Guidelines', 'Office Reinstatement', 'Flooring & Finishing', 'Waterproofing & Repair', 'General Tips'];
foreach ($allExistingPosts as $ep) {
    if (!empty($ep['category']) && !in_array($ep['category'], $existingCategories)) {
        $existingCategories[] = $ep['category'];
    }
}

require_once __DIR__ . '/header.php';
?>

<form method="POST" action="post-edit.php<?= $isEdit ? '?id=' . urlencode($postId) : '' ?>" enctype="multipart/form-data" id="postForm">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="id" value="<?= htmlspecialchars($postId) ?>">

    <div style="display: grid; grid-template-columns: 2.2fr 1fr; gap: 30px; align-items: start;" class="editor-layout-grid">
        
        <!-- Left Main Form Column -->
        <div>
            
            <!-- Article Title -->
            <div class="form-group" style="background: #fff; padding: 24px; border-radius: var(--admin-radius); border: 1px solid var(--admin-border); box-shadow: var(--admin-shadow); margin-bottom: 24px;">
                <label class="form-label" for="postTitle" style="font-size: 15px;">Article Title <span style="color: #ef4444;">*</span></label>
                <input type="text" id="postTitle" name="title" class="form-control-adm" style="font-size: 18px; font-weight: 600; padding: 12px 16px;" placeholder="e.g. HDB Wall Hacking & Demolition Permit Guide Singapore (2026)" value="<?= htmlspecialchars($post['title'] ?? '') ?>" required>
                
                <!-- Slug & Permalink Preview -->
                <div style="margin-top: 12px; font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span><strong>Public URL:</strong> <?= $baseUrl ?>/blog/<span id="slugPreviewText" style="color: #0284c7; font-weight: 600;"><?= htmlspecialchars($post['slug'] ?? 'url-slug') ?></span></span>
                    <button type="button" class="btn-adm btn-adm-outline" id="unlockSlugBtn" style="padding: 2px 8px; font-size: 11px;">Edit Slug</button>
                </div>
                <div id="slugInputWrap" style="display: none; margin-top: 8px;">
                    <input type="text" id="postSlug" name="slug" class="form-control-adm" style="font-size: 13px; padding: 6px 12px;" value="<?= htmlspecialchars($post['slug'] ?? '') ?>" placeholder="custom-url-slug">
                </div>
            </div>

            <!-- Excerpt / Meta Description (Crucial for Social Media Preview!) -->
            <div class="form-group" style="background: #fff; padding: 24px; border-radius: var(--admin-radius); border: 1px solid var(--admin-border); box-shadow: var(--admin-shadow); margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label class="form-label" for="postExcerpt" style="margin-bottom: 0;">Summary / Social Media Description <span style="color: #ef4444;">*</span></label>
                    <span id="charCountBadge" style="font-size: 12px; color: #64748b; font-weight: 500;">0 / 160 characters</span>
                </div>
                <p style="font-size: 12px; color: #64748b; margin-bottom: 8px;">
                    This description is what WhatsApp, Facebook, and Google display under the title when shared.
                </p>
                <textarea id="postExcerpt" name="excerpt" class="form-control-adm" rows="3" placeholder="Brief 1-2 sentence summary of the article..." required><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
            </div>

            <!-- Rich Content Editor & Toolbar -->
            <div class="form-group" style="background: #fff; padding: 24px; border-radius: var(--admin-radius); border: 1px solid var(--admin-border); box-shadow: var(--admin-shadow); margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <label class="form-label" style="font-size: 15px; margin-bottom: 0;">Article Content <span style="color: #ef4444;">*</span></label>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn-adm btn-adm-primary" id="editorTabBtn" style="padding: 4px 12px; font-size: 12px;">Write</button>
                        <button type="button" class="btn-adm btn-adm-outline" id="previewTabBtn" style="padding: 4px 12px; font-size: 12px;">Preview Output</button>
                    </div>
                </div>

                <!-- Formatting Toolbar -->
                <div style="background: #f8fafc; border: 1px solid var(--admin-border); border-bottom: none; border-radius: 8px 8px 0 0; padding: 8px 12px; display: flex; flex-wrap: wrap; gap: 6px;" id="toolbarWrap">
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertTag('<strong>', '</strong>')" title="Bold"><strong>B</strong></button>
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertTag('<em>', '</em>')" title="Italic"><em>I</em></button>
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertTag('<h2>', '</h2>')" title="Heading 2">H2</button>
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertTag('<h3>', '</h3>')" title="Heading 3">H3</button>
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertList('ul')" title="Bullet List">• List</button>
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertList('ol')" title="Numbered List">1. List</button>
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertTag('<blockquote>', '</blockquote>')" title="Quote Box">❝ Quote</button>
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertCallout('tip')" title="Pro Tip Box">💡 Tip Box</button>
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertCallout('warning')" title="Warning Box">⚠️ Warning Box</button>
                    <button type="button" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" onclick="insertLink()" title="Add Link">🔗 Link</button>
                </div>

                <!-- Textarea Editor -->
                <textarea id="postContent" name="content" class="form-control-adm" style="min-height: 420px; border-radius: 0 0 8px 8px; font-family: 'Inter', monospace; font-size: 14px; line-height: 1.7;" placeholder="Write your article content here using HTML tags or formatting buttons..." required><?= htmlspecialchars($post['content'] ?? '') ?></textarea>

                <!-- Live Preview Container -->
                <div id="previewContainer" style="display: none; min-height: 420px; border: 1px solid var(--admin-border); border-radius: 0 0 8px 8px; padding: 24px; background: #fff; line-height: 1.8;" class="article-content"></div>
            </div>

        </div>

        <!-- Right Settings & Live Preview Sidebar -->
        <div>
            
            <!-- Publish Actions Card -->
            <div style="background: #fff; padding: 24px; border-radius: var(--admin-radius); border: 1px solid var(--admin-border); box-shadow: var(--admin-shadow); margin-bottom: 24px;">
                <h3 style="font-size: 16px; font-family: 'Outfit', sans-serif; margin-bottom: 16px; color: var(--admin-navy);">Publishing Options</h3>
                
                <div class="form-group">
                    <label class="form-label" for="postStatus">Publication Status:</label>
                    <select id="postStatus" name="status" class="form-control-adm" style="font-weight: 600;">
                        <option value="published" <?= (($post['status'] ?? 'published') === 'published') ? 'selected' : '' ?>>🟢 Published (Visible to Public)</option>
                        <option value="draft" <?= (($post['status'] ?? '') === 'draft') ? 'selected' : '' ?>>✏️ Draft (Save privately)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="postCategory">Category:</label>
                    <select id="postCategory" name="category" class="form-control-adm">
                        <?php foreach ($existingCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= (($post['category'] ?? '') === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                        <option value="__new__">+ Add New Category...</option>
                    </select>
                    <div id="newCatWrap" style="display: none; margin-top: 8px;">
                        <input type="text" name="custom_category" class="form-control-adm" placeholder="Enter new category name">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="postAuthor">Author Byline:</label>
                    <input type="text" id="postAuthor" name="author" class="form-control-adm" value="<?= htmlspecialchars($post['author'] ?? current_user()['name'] ?? 'Tabeeb Contractor') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="postReadTime">Reading Time:</label>
                    <input type="text" id="postReadTime" name="read_time" class="form-control-adm" placeholder="e.g. 5 min read" value="<?= htmlspecialchars($post['read_time'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="postTags">Tags (comma-separated):</label>
                    <input type="text" id="postTags" name="tags" class="form-control-adm" placeholder="HDB Hacking, Renovation, Permit" value="<?= htmlspecialchars(is_array($post['tags'] ?? null) ? implode(', ', $post['tags']) : ($post['tags'] ?? '')) ?>">
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-adm btn-adm-primary" style="justify-content: center; padding: 12px;">
                        <span>💾 <?= $isEdit ? 'Save Changes' : 'Publish Article' ?></span>
                    </button>
                    <?php if ($isEdit && ($post['status'] ?? '') === 'published'): ?>
                        <a href="/blog/<?= htmlspecialchars($post['slug']) ?>" target="_blank" class="btn-adm btn-adm-outline" style="justify-content: center;">
                            <span>👁️ View Live Post ↗</span>
                        </a>
                    <?php endif; ?>
                    <a href="posts.php" class="btn-adm btn-adm-outline" style="justify-content: center;">
                        Cancel
                    </a>
                </div>
            </div>

            <!-- Featured Cover Image Card -->
            <div style="background: #fff; padding: 24px; border-radius: var(--admin-radius); border: 1px solid var(--admin-border); box-shadow: var(--admin-shadow); margin-bottom: 24px;">
                <h3 style="font-size: 16px; font-family: 'Outfit', sans-serif; margin-bottom: 14px; color: var(--admin-navy);">Featured Cover Image</h3>
                
                <!-- Image Preview Box -->
                <div style="width: 100%; height: 160px; border-radius: 8px; overflow: hidden; background: #07274d; margin-bottom: 14px; border: 1px solid var(--admin-border);">
                    <img id="coverPreviewImg" src="/<?= ltrim($post['image'] ?? 'images/page-header-bg.jpg', '/') ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                </div>

                <!-- Upload File option -->
                <div class="form-group">
                    <label class="form-label">Upload Image from Computer:</label>
                    <input type="file" name="cover_upload" id="coverFileInput" accept="image/*" class="form-control-adm" style="padding: 6px 10px; font-size: 12px;">
                </div>

                <!-- Or preset / URL option -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="imageUrlInput">Or Image Path / Preset:</label>
                    <select id="presetImageSelect" class="form-control-adm" style="margin-bottom: 8px; font-size: 12px;">
                        <option value="">-- Choose from preset images --</option>
                        <option value="images/demolition.png">🔨 Wall Hacking & Demolition (demolition.png)</option>
                        <option value="images/service-interior.jpg">🏢 Office Reinstatement (service-interior.jpg)</option>
                        <option value="images/flooring.png">🪵 Flooring & Cement Screed (flooring.png)</option>
                        <option value="images/ceiling.png">🏠 False Ceiling & Drywall (ceiling.png)</option>
                        <option value="images/painting.png">🖌️ Painting & Skim Coating (painting.png)</option>
                        <option value="images/waterproofing.png">💧 Waterproofing & PU Injection (waterproofing.png)</option>
                        <option value="images/electrical.png">⚡ Electrical & Rewiring (electrical.png)</option>
                        <option value="images/service-plumbing.jpg">🚰 Plumbing & Sanitary (service-plumbing.jpg)</option>
                        <option value="images/metal-fabrication.png">🔩 Metal Gate & Grilles (metal-fabrication.png)</option>
                        <option value="images/home-extensions.png">🏗️ Home Extension (home-extensions.png)</option>
                    </select>
                    <input type="text" id="imageUrlInput" name="image_url" class="form-control-adm" style="font-size: 12px;" value="<?= htmlspecialchars($post['image'] ?? 'images/page-header-bg.jpg') ?>">
                </div>
            </div>

            <!-- Live Social Share Card Preview Box (Directly addresses the WhatsApp share preview feature!) -->
            <div style="background: #fff; padding: 24px; border-radius: var(--admin-radius); border: 1px solid var(--admin-border); box-shadow: var(--admin-shadow); border-top: 3px solid #25d366;">
                <h3 style="font-size: 15px; font-family: 'Outfit', sans-serif; margin-bottom: 6px; color: var(--admin-navy); display: flex; align-items: center; gap: 8px;">
                    <span>📲</span> Live WhatsApp Card Preview
                </h3>
                <p style="font-size: 12px; color: #64748b; margin-bottom: 14px;">
                    This is how your link appears in WhatsApp chat:
                </p>

                <div style="background: #efeae2; border-radius: 12px; padding: 12px; border: 1px solid #d1d7db;">
                    <div style="background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.12);">
                        <img id="waLiveImg" src="/<?= ltrim($post['image'] ?? 'images/page-header-bg.jpg', '/') ?>" alt="WA Preview" style="width: 100%; height: 130px; object-fit: cover;">
                        <div style="padding: 10px 12px; background: #f0f2f5;">
                            <div id="waLiveTitle" style="font-weight: 700; font-size: 13px; color: #111b21; line-height: 1.3; margin-bottom: 4px;">
                                <?= htmlspecialchars(!empty($post['title']) ? $post['title'] : 'Article Title Preview') ?>
                            </div>
                            <div id="waLiveDesc" style="font-size: 11px; color: #667781; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 4px;">
                                <?= htmlspecialchars(!empty($post['excerpt']) ? $post['excerpt'] : 'Summary description preview for WhatsApp share cards...') ?>
                            </div>
                            <div style="font-size: 10px; color: #8696a0;">
                                <?= parse_url($baseUrl, PHP_URL_HOST) ?? 'tabeebgroup.com' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const titleInput = document.getElementById('postTitle');
        const slugInput = document.getElementById('postSlug');
        const slugPreviewText = document.getElementById('slugPreviewText');
        const unlockSlugBtn = document.getElementById('unlockSlugBtn');
        const slugInputWrap = document.getElementById('slugInputWrap');

        const excerptInput = document.getElementById('postExcerpt');
        const charCountBadge = document.getElementById('charCountBadge');

        const contentInput = document.getElementById('postContent');
        const editorTabBtn = document.getElementById('editorTabBtn');
        const previewTabBtn = document.getElementById('previewTabBtn');
        const toolbarWrap = document.getElementById('toolbarWrap');
        const previewContainer = document.getElementById('previewContainer');

        const catSelect = document.getElementById('postCategory');
        const newCatWrap = document.getElementById('newCatWrap');

        const coverFileInput = document.getElementById('coverFileInput');
        const presetImageSelect = document.getElementById('presetImageSelect');
        const imageUrlInput = document.getElementById('imageUrlInput');
        const coverPreviewImg = document.getElementById('coverPreviewImg');

        const waLiveImg = document.getElementById('waLiveImg');
        const waLiveTitle = document.getElementById('waLiveTitle');
        const waLiveDesc = document.getElementById('waLiveDesc');

        let slugManuallyEdited = <?= $isEdit ? 'true' : 'false' ?>;

        // Auto slugify helper
        const slugify = (text) => {
            return text.toString().toLowerCase().trim()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-');
        };

        // Title input sync
        titleInput.addEventListener('input', () => {
            const val = titleInput.value.trim();
            if (!slugManuallyEdited) {
                const s = slugify(val);
                slugInput.value = s;
                slugPreviewText.textContent = s || 'url-slug';
            }
            waLiveTitle.textContent = val || 'Article Title Preview';
        });

        // Slug manual unlock
        unlockSlugBtn.addEventListener('click', () => {
            slugInputWrap.style.display = (slugInputWrap.style.display === 'none') ? 'block' : 'none';
        });

        slugInput.addEventListener('input', () => {
            slugManuallyEdited = true;
            slugPreviewText.textContent = slugify(slugInput.value) || 'url-slug';
        });

        // Excerpt char count
        const updateExcerptCount = () => {
            const len = excerptInput.value.length;
            charCountBadge.textContent = len + ' / 160 characters';
            if (len >= 120 && len <= 160) {
                charCountBadge.style.color = '#16a34a';
            } else if (len > 160) {
                charCountBadge.style.color = '#dc2626';
            } else {
                charCountBadge.style.color = '#64748b';
            }
            waLiveDesc.textContent = excerptInput.value || 'Summary description preview for WhatsApp share cards...';
        };
        excerptInput.addEventListener('input', updateExcerptCount);
        updateExcerptCount();

        // Category dropdown custom input
        catSelect.addEventListener('change', () => {
            newCatWrap.style.display = (catSelect.value === '__new__') ? 'block' : 'none';
        });

        // Image selector
        presetImageSelect.addEventListener('change', () => {
            if (presetImageSelect.value) {
                imageUrlInput.value = presetImageSelect.value;
                coverPreviewImg.src = '/' + presetImageSelect.value;
                waLiveImg.src = '/' + presetImageSelect.value;
            }
        });

        imageUrlInput.addEventListener('input', () => {
            const val = imageUrlInput.value.trim();
            if (val) {
                const src = val.startsWith('http') ? val : ('/' + val.replace(/^\//, ''));
                coverPreviewImg.src = src;
                waLiveImg.src = src;
            }
        });

        // File upload local preview
        coverFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    coverPreviewImg.src = event.target.result;
                    waLiveImg.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Tab Switching between Edit and Preview
        editorTabBtn.addEventListener('click', () => {
            editorTabBtn.classList.replace('btn-adm-outline', 'btn-adm-primary');
            previewTabBtn.classList.replace('btn-adm-primary', 'btn-adm-outline');
            toolbarWrap.style.display = 'flex';
            contentInput.style.display = 'block';
            previewContainer.style.display = 'none';
        });

        previewTabBtn.addEventListener('click', () => {
            previewTabBtn.classList.replace('btn-adm-outline', 'btn-adm-primary');
            editorTabBtn.classList.replace('btn-adm-primary', 'btn-adm-outline');
            toolbarWrap.style.display = 'none';
            contentInput.style.display = 'none';
            previewContainer.innerHTML = contentInput.value;
            previewContainer.style.display = 'block';
        });
    });

    // Formatting Helpers for Rich Editor
    function insertTag(openTag, closeTag) {
        const textarea = document.getElementById('postContent');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const selected = text.substring(start, end) || 'Text here';
        
        textarea.value = text.substring(0, start) + openTag + selected + closeTag + text.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + openTag.length, start + openTag.length + selected.length);
    }

    function insertList(type) {
        const textarea = document.getElementById('postContent');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const listHtml = `\n<${type}>\n  <li>Item 1</li>\n  <li>Item 2</li>\n  <li>Item 3</li>\n</${type}>\n`;
        
        textarea.value = text.substring(0, start) + listHtml + text.substring(end);
        textarea.focus();
    }

    function insertCallout(type) {
        const textarea = document.getElementById('postContent');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const title = (type === 'tip') ? '💡 Expert Pro Tip' : '⚠️ Statutory Warning';
        const calloutHtml = `\n<div class="blog-callout ${type}">\n  <h4>${title}</h4>\n  <p>Helpful advice or compliance note here...</p>\n</div>\n`;
        
        textarea.value = text.substring(0, start) + calloutHtml + text.substring(end);
        textarea.focus();
    }

    function insertLink() {
        const url = prompt('Enter link URL (e.g. https://... or /contact):', 'https://');
        if (url) {
            insertTag(`<a href="${url}">`, '</a>');
        }
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
