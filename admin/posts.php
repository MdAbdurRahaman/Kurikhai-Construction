<?php
require_once dirname(__DIR__) . '/includes/data.php';
require_login();

// Handle Actions (Toggle status, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postId = $_POST['post_id'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf($csrf) && !empty($postId)) {
        if ($action === 'toggle_status') {
            $post = get_post_by_id($postId);
            if ($post) {
                $post['status'] = ($post['status'] === 'published') ? 'draft' : 'published';
                save_post($post);
                header('Location: posts.php?msg=' . urlencode('Post status updated to ' . ucfirst($post['status']) . '!'));
                exit;
            }
        } elseif ($action === 'delete') {
            if (delete_post($postId)) {
                header('Location: posts.php?msg=' . urlencode('Article deleted successfully.'));
                exit;
            } else {
                header('Location: posts.php?error=' . urlencode('Failed to delete article.'));
                exit;
            }
        }
    }
}

$pageTitle = 'Blog Posts Management';
require_once __DIR__ . '/header.php';

$allPosts = get_all_posts(false);
$filterStatus = $_GET['status'] ?? 'all';
$filterCat = $_GET['cat'] ?? 'all';
$searchQuery = strtolower(trim($_GET['q'] ?? ''));

// Filter logic
$filteredPosts = array_filter($allPosts, function($p) use ($filterStatus, $filterCat, $searchQuery) {
    if ($filterStatus !== 'all' && ($p['status'] ?? 'draft') !== $filterStatus) {
        return false;
    }
    if ($filterCat !== 'all' && ($p['category'] ?? '') !== $filterCat) {
        return false;
    }
    if (!empty($searchQuery)) {
        $inTitle = str_contains(strtolower($p['title'] ?? ''), $searchQuery);
        $inContent = str_contains(strtolower($p['excerpt'] ?? ''), $searchQuery);
        if (!$inTitle && !$inContent) return false;
    }
    return true;
});

// Categories list
$categories = [];
foreach ($allPosts as $p) {
    if (!empty($p['category']) && !in_array($p['category'], $categories)) {
        $categories[] = $p['category'];
    }
}
?>

<div class="table-card">
    <div class="table-header">
        <div>
            <h3>All Blog Articles (<?= count($filteredPosts) ?>)</h3>
            <span style="font-size: 13px; color: var(--admin-text-muted);">Publish, edit, and categorize your construction guides</span>
        </div>
        <div>
            <a href="post-edit.php" class="btn-adm btn-adm-primary">+ Write New Article</a>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="padding: 16px 24px; background: #f8fafc; border-bottom: 1px solid var(--admin-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <a href="posts.php?status=all" class="btn-adm <?= $filterStatus === 'all' ? 'btn-adm-primary' : 'btn-adm-outline' ?>" style="padding: 6px 12px; font-size: 12px;">All</a>
            <a href="posts.php?status=published" class="btn-adm <?= $filterStatus === 'published' ? 'btn-adm-primary' : 'btn-adm-outline' ?>" style="padding: 6px 12px; font-size: 12px;">Published</a>
            <a href="posts.php?status=draft" class="btn-adm <?= $filterStatus === 'draft' ? 'btn-adm-primary' : 'btn-adm-outline' ?>" style="padding: 6px 12px; font-size: 12px;">Drafts</a>
            
            <form method="GET" action="posts.php" style="display: inline-flex; margin-left: 10px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                <select name="cat" class="form-control-adm" style="padding: 5px 10px; font-size: 12px; width: auto;" onchange="this.form.submit()">
                    <option value="all">Filter by Category...</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $filterCat === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <form method="GET" action="posts.php" style="display: flex; gap: 8px;">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
            <input type="hidden" name="cat" value="<?= htmlspecialchars($filterCat) ?>">
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search titles..." class="form-control-adm" style="padding: 6px 12px; font-size: 13px; width: 200px;">
            <button type="submit" class="btn-adm btn-adm-outline" style="padding: 6px 12px; font-size: 13px;">Search</button>
            <?php if (!empty($_GET['q']) || $filterCat !== 'all' || $filterStatus !== 'all'): ?>
                <a href="posts.php" class="btn-adm btn-adm-outline" style="padding: 6px 10px; font-size: 13px; color: #ef4444;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-wrapper">
        <table class="adm-table">
            <thead>
                <tr>
                    <th style="width: 70px;">Cover</th>
                    <th>Article Details</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Published Date</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filteredPosts)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 50px; color: #64748b;">
                            No articles match your filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($filteredPosts as $p): 
                        $img = (strpos($p['image'], 'http') === 0) ? $p['image'] : ('/' . ltrim($p['image'], '/'));
                    ?>
                        <tr>
                            <td>
                                <img src="<?= $img ?>" alt="Cover" style="width: 56px; height: 42px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0;">
                            </td>
                            <td>
                                <a href="post-edit.php?id=<?= urlencode($p['id']) ?>" style="font-weight: 600; font-size: 14px; color: var(--admin-navy); text-decoration: none;">
                                    <?= htmlspecialchars($p['title']) ?>
                                </a>
                                <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                                    <?= htmlspecialchars(substr($p['excerpt'], 0, 75)) ?>...
                                </div>
                            </td>
                            <td>
                                <span class="badge-adm badge-adm-blue"><?= htmlspecialchars($p['category']) ?></span>
                            </td>
                            <td>
                                <form method="POST" action="posts.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="post_id" value="<?= htmlspecialchars($p['id']) ?>">
                                    <button type="submit" style="background: none; border: none; cursor: pointer;" title="Click to toggle status">
                                        <?php if (($p['status'] ?? '') === 'published'): ?>
                                            <span class="badge-adm badge-adm-success" style="cursor: pointer;">✓ Published</span>
                                        <?php else: ?>
                                            <span class="badge-adm badge-adm-warning" style="cursor: pointer;">Draft (Click to Publish)</span>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </td>
                            <td style="color: #475569;">
                                <?= htmlspecialchars($p['author'] ?? 'Admin') ?>
                            </td>
                            <td style="color: #64748b; font-size: 12px;">
                                <?= format_date($p['created_at']) ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <?php if (($p['status'] ?? '') === 'published'): ?>
                                        <a href="/blog/<?= htmlspecialchars($p['slug']) ?>" target="_blank" class="btn-adm btn-adm-outline" style="padding: 5px 9px; font-size: 12px;" title="View Live Article">
                                            👁️
                                        </a>
                                    <?php endif; ?>
                                    <a href="post-edit.php?id=<?= urlencode($p['id']) ?>" class="btn-adm btn-adm-outline" style="padding: 5px 9px; font-size: 12px;" title="Edit Article">
                                        ✏️
                                    </a>
                                    <form method="POST" action="posts.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this article? This action cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="post_id" value="<?= htmlspecialchars($p['id']) ?>">
                                        <button type="submit" class="btn-adm btn-adm-outline" style="padding: 5px 9px; font-size: 12px; color: #ef4444;" title="Delete Article">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
