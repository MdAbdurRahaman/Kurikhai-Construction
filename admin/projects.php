<?php
/**
 * Projects & Case Studies Portfolio Management - Tabeeb Contractor Portal
 */
require_once dirname(__DIR__) . '/includes/data.php';
require_once __DIR__ . '/header.php';

require_permission('projects.manage');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $projId = $_POST['project_id'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (verify_csrf($csrf) && !empty($projId)) {
        if ($action === 'toggle_status') {
            $proj = get_project_by_id($projId);
            if ($proj) {
                $proj['publication_state'] = ($proj['publication_state'] === 'published') ? 'draft' : 'published';
                save_project($proj);
                header('Location: projects.php?msg=' . urlencode('Project status updated to ' . ucfirst($proj['publication_state']) . '!'));
                exit;
            }
        } elseif ($action === 'delete') {
            if (delete_project($projId)) {
                header('Location: projects.php?msg=' . urlencode('Project deleted successfully.'));
                exit;
            } else {
                header('Location: projects.php?error=' . urlencode('Failed to delete project.'));
                exit;
            }
        }
    }
}

$allProjects = get_all_projects(false);
$filterState = $_GET['state'] ?? 'all';
$searchQuery = strtolower(trim($_GET['q'] ?? ''));

$filtered = array_filter($allProjects, function($p) use ($filterState, $searchQuery) {
    if ($filterState !== 'all' && ($p['publication_state'] ?? 'draft') !== $filterState) {
        return false;
    }
    if (!empty($searchQuery)) {
        $inTitle = str_contains(strtolower($p['title'] ?? ''), $searchQuery);
        $inLoc = str_contains(strtolower($p['location_label'] ?? ''), $searchQuery);
        if (!$inTitle && !$inLoc) return false;
    }
    return true;
});
?>

<div class="table-card">
    <div class="table-header">
        <div>
            <h3>Project Case Studies Portfolio (<?= count($filtered) ?>)</h3>
            <span style="font-size: 13px; color: var(--admin-text-muted);">Manage authentic client renovation projects, media boards, and case studies</span>
        </div>
        <div>
            <a href="project-edit.php" class="btn-adm btn-adm-primary">+ New Case Study Project</a>
        </div>
    </div>

    <!-- Filters & Search -->
    <div style="padding: 16px 24px; background: #f8fafc; border-bottom: 1px solid var(--admin-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="projects.php?state=all" class="btn-adm <?= $filterState === 'all' ? 'btn-adm-primary' : 'btn-adm-outline' ?>" style="padding: 6px 12px; font-size: 12px;">All Projects</a>
            <a href="projects.php?state=published" class="btn-adm <?= $filterState === 'published' ? 'btn-adm-primary' : 'btn-adm-outline' ?>" style="padding: 6px 12px; font-size: 12px;">Published</a>
            <a href="projects.php?state=draft" class="btn-adm <?= $filterState === 'draft' ? 'btn-adm-primary' : 'btn-adm-outline' ?>" style="padding: 6px 12px; font-size: 12px;">Drafts</a>
        </div>

        <form method="GET" action="projects.php" style="display: flex; gap: 8px;">
            <input type="hidden" name="state" value="<?= htmlspecialchars($filterState) ?>">
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search projects..." class="form-control-adm" style="padding: 6px 12px; font-size: 13px; width: 220px;">
            <button type="submit" class="btn-adm btn-adm-outline" style="padding: 6px 12px; font-size: 13px;">Search</button>
            <?php if (!empty($_GET['q']) || $filterState !== 'all'): ?>
                <a href="projects.php" class="btn-adm btn-adm-outline" style="padding: 6px 10px; font-size: 13px; color: #ef4444;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($filtered)): ?>
        <div style="padding: 48px; text-align: center; color: var(--admin-text-muted);">
            <div style="font-size: 32px; margin-bottom: 12px;">🏗️</div>
            <h4>No project case studies found</h4>
            <p style="font-size: 13px; margin-top: 4px;">Click the button above to add your first documented project.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">Media</th>
                        <th>Project Title & Slug</th>
                        <th>Location Label</th>
                        <th>Service Category</th>
                        <th>Status</th>
                        <th>Approval</th>
                        <th>Updated</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered as $proj): ?>
                        <tr>
                            <td>
                                <img src="<?= htmlspecialchars($proj['featured_image']) ?>" alt="Thumbnail" style="width: 55px; height: 42px; object-fit: cover; border-radius: 6px; border: 1px solid var(--admin-border);">
                            </td>
                            <td>
                                <strong style="color: var(--admin-text-main); font-size: 14px;"><?= htmlspecialchars($proj['title']) ?></strong>
                                <div style="font-size: 12px; color: var(--admin-text-muted); font-family: monospace;">/projects/<?= htmlspecialchars($proj['slug']) ?></div>
                            </td>
                            <td>
                                <span style="font-size: 13px;"><?= htmlspecialchars($proj['location_label'] ?: $proj['public_location']) ?></span>
                            </td>
                            <td>
                                <span class="badge" style="background: #e0f2fe; color: #0369a1;"><?= htmlspecialchars($proj['service_id'] ?? 'General') ?></span>
                            </td>
                            <td>
                                <?php if ($proj['publication_state'] === 'published'): ?>
                                    <span class="badge" style="background: #dcfce7; color: #15803d;">Published</span>
                                <?php elseif ($proj['publication_state'] === 'draft'): ?>
                                    <span class="badge" style="background: #fef9c3; color: #a16207;">Draft</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f1f5f9; color: #64748b;">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($proj['approval_status'] === 'approved'): ?>
                                    <span style="font-size: 12px; color: #16a34a; font-weight: 600;">✓ Approved</span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #d97706; font-weight: 600;">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: var(--admin-text-muted);">
                                <?= date('M j, Y', strtotime($proj['updated_at'])) ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="/projects/<?= htmlspecialchars($proj['slug']) ?>" target="_blank" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;" title="Public Preview">Preview</a>
                                <a href="project-edit.php?id=<?= urlencode($proj['id']) ?>" class="btn-adm btn-adm-primary" style="padding: 4px 10px; font-size: 12px;">Edit</a>
                                
                                <form method="POST" action="projects.php" style="display: inline-block;">
                                    <?= csrf_input_field() ?>
                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($proj['id']) ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <button type="submit" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px;">
                                        <?= $proj['publication_state'] === 'published' ? 'Unpublish' : 'Publish' ?>
                                    </button>
                                </form>

                                <form method="POST" action="projects.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to permanently delete this project?');">
                                    <?= csrf_input_field() ?>
                                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($proj['id']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn-adm btn-adm-outline" style="padding: 4px 8px; font-size: 12px; color: #ef4444;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
