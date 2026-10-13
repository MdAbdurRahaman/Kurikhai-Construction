<?php
/**
 * Project Case Study Editor - Tabeeb Contractor Portal
 */
require_once dirname(__DIR__) . '/includes/data.php';
require_login();
require_permission('projects.manage');

$projId = $_GET['id'] ?? '';
$isEdit = !empty($projId);
$proj = $isEdit ? get_project_by_id($projId) : null;

if ($isEdit && !$proj) {
    header('Location: projects.php?error=' . urlencode('Project not found.'));
    exit;
}

$pageTitle = $isEdit ? 'Edit Case Study Project' : 'Create New Case Study Project';
$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $locLabel = trim($_POST['location_label'] ?? '');
        $pubLoc = trim($_POST['public_location'] ?? 'Singapore');
        $serviceId = trim($_POST['service_id'] ?? '');
        $factualSummary = trim($_POST['factual_summary'] ?? '');
        $scopeDetails = trim($_POST['scope_details'] ?? '');
        $durationLabel = trim($_POST['duration_label'] ?? '');
        $materialsUsed = trim($_POST['materials_used'] ?? '');
        $sourceFile = trim($_POST['source_file'] ?? '');
        $featuredImage = trim($_POST['featured_image'] ?? '/images/projects/serangoon-featured-thumb.webp');
        $videoPath = trim($_POST['video_path'] ?? '');
        $videoPoster = trim($_POST['video_poster'] ?? '');
        $pubState = in_array($_POST['publication_state'] ?? '', ['draft', 'published', 'archived']) ? $_POST['publication_state'] : 'draft';
        $approvalEvidence = trim((string)($_POST['approval_evidence'] ?? ''));
        $approvalStatus = in_array($_POST['approval_status'] ?? '', ['pending', 'approved', 'restricted']) ? $_POST['approval_status'] : 'pending';
        $ogTitle = trim($_POST['og_title'] ?? '');
        $ogDesc = trim($_POST['og_description'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if ($approvalStatus === 'approved' && (!has_permission('projects.approve') || strlen($approvalEvidence) < 10)) {
            $error = 'Publication approval requires an authorized administrator and dated client evidence.';
        } elseif (empty($title)) {
            $error = 'Project Title is required.';
        } elseif (empty($factualSummary)) {
            $error = 'Factual Summary is required to document genuine project evidence.';
        } else {
            $projData = [
                'id'                => $projId,
                'title'             => $title,
                'slug'              => $slug,
                'location_label'    => $locLabel,
                'public_location'   => $pubLoc,
                'service_id'        => $serviceId,
                'factual_summary'   => $factualSummary,
                'scope_details'     => $scopeDetails,
                'duration_label'    => $durationLabel,
                'materials_used'    => $materialsUsed,
                'source_file'       => $sourceFile,
                'featured_image'    => $featuredImage,
                'video_path'        => $videoPath,
                'video_poster'      => $videoPoster,
                'publication_state' => $pubState,
                'approval_status'   => $approvalStatus,
                'og_title'          => $ogTitle,
                'og_description'    => $ogDesc,
                'display_order'     => $displayOrder
            ];

            if (!DB::isConnected()) { $error = 'Database unavailable; project changes were not saved.'; $res = ['success'=>false,'message'=>$error]; } else {
            $res = DB::transaction(function()use($projData,$approvalStatus,$approvalEvidence){
                $r = save_project($projData);
                if (!empty($r['success'])) {
                    db_exec('UPDATE projects SET approved_by=?,approved_at=? WHERE id=?',[$approvalStatus==='approved'?(current_user()['id']??null):null,$approvalStatus==='approved'?date('Y-m-d H:i:s'):null,$r['project']['id']]);
                    audit_log('project.approval_recorded','projects',$r['project']['id'],null,['status'=>$approvalStatus,'evidence'=>$approvalEvidence]);
                }
                return $r;
            });
            }
            if (!empty($res['success'])) {
                header('Location: projects.php?msg=' . urlencode('Project case study saved successfully!'));
                exit;
            } else {
                $error = $res['message'] ?? 'Failed to save project.';
            }
        }
    }
}

$services = get_all_services(false);

require_once __DIR__ . '/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <span style="font-size: 13px; color: var(--admin-text-muted);">Ensure all facts, locations, and media reflect client-supplied documentation only.</span>
        </div>
        <div>
            <?php if($isEdit):?><a href="project-media.php?project=<?= urlencode($projId) ?>">Edit project media</a><?php endif;?>
            <a href="projects.php" class="btn-adm btn-adm-outline">← Back to Projects</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="project-edit.php<?= $isEdit ? '?id=' . urlencode($projId) : '' ?>">
        <?= csrf_input_field() ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">

            <!-- Left Column: Core Facts & Evidence -->
            <div>
                <div class="table-card" style="margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Project Identity & Documentation</h3>
                    </div>
                    <div style="padding: 24px;">
                        <div class="form-group">
                            <label class="form-label" for="title">Project Title *</label>
                            <input type="text" name="title" id="title" class="form-control-adm" required value="<?= htmlspecialchars($_POST['title'] ?? $proj['title'] ?? '') ?>" placeholder="e.g. Serangoon Avenue 3 Flooring Works">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="slug">URL Slug (Auto-generated if blank)</label>
                            <input type="text" name="slug" id="slug" class="form-control-adm" value="<?= htmlspecialchars($_POST['slug'] ?? $proj['slug'] ?? '') ?>" placeholder="serangoon-avenue-3-flooring-works">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label" for="location_label">Supplied Location Label</label>
                                <input type="text" name="location_label" id="location_label" class="form-control-adm" value="<?= htmlspecialchars($_POST['location_label'] ?? $proj['location_label'] ?? '') ?>" placeholder="e.g. Blk 234 Serangoon Avenue 3">
                                <div style="font-size: 11px; color: var(--admin-text-muted); margin-top: 4px;">Internal/artwork location identifier.</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="public_location">Public Location Display *</label>
                                <input type="text" name="public_location" id="public_location" class="form-control-adm" required value="<?= htmlspecialchars($_POST['public_location'] ?? $proj['public_location'] ?? 'Serangoon, Singapore') ?>" placeholder="e.g. Serangoon, Singapore">
                                <div style="font-size: 11px; color: var(--admin-text-muted); margin-top: 4px;">Broad location for privacy compliance.</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="service_id">Related Service Category</label>
                            <select name="service_id" id="service_id" class="form-control-adm">
                                <option value="">-- Select Service --</option>
                                <?php foreach ($services as $srv): ?>
                                    <option value="<?= htmlspecialchars($srv['slug']) ?>" <?= (($_POST['service_id'] ?? $proj['service_id'] ?? '') === $srv['slug']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($srv['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="factual_summary">Factual Summary * (No invented claims)</label>
                            <textarea name="factual_summary" id="factual_summary" class="form-control-adm" rows="4" required placeholder="Factual description based strictly on client photographs and project artwork..."><?= htmlspecialchars($_POST['factual_summary'] ?? $proj['factual_summary'] ?? 'Client-supplied photographs show the existing floor condition, work in progress and the completed tiled finish at Serangoon Avenue 3.') ?></textarea>
                            <div style="font-size: 11px; color: var(--admin-text-muted); margin-top: 4px;">Do NOT invent tile brands, warranties, pricing, permits, or customer ratings.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="scope_details">Documented Scope Details</label>
                            <textarea name="scope_details" id="scope_details" class="form-control-adm" rows="3" placeholder="Documented scope stages..."><?= htmlspecialchars($_POST['scope_details'] ?? $proj['scope_details'] ?? '') ?></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label" for="duration_label">Duration / Timeline (Nullable)</label>
                                <input type="text" name="duration_label" id="duration_label" class="form-control-adm" value="<?= htmlspecialchars($_POST['duration_label'] ?? $proj['duration_label'] ?? '') ?>" placeholder="Leave blank if unconfirmed">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="materials_used">Materials Verified (Nullable)</label>
                                <input type="text" name="materials_used" id="materials_used" class="form-control-adm" value="<?= htmlspecialchars($_POST['materials_used'] ?? $proj['materials_used'] ?? '') ?>" placeholder="Leave blank if unconfirmed">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Media Assets Selection -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>Media Assets & Provenance</h3>
                    </div>
                    <div style="padding: 24px;">
                        <div class="form-group">
                            <label class="form-label" for="source_file">Original Source File Reference</label>
                            <input type="text" name="source_file" id="source_file" class="form-control-adm" value="<?= htmlspecialchars($_POST['source_file'] ?? $proj['source_file'] ?? 'WhatsApp Image 2026-10-09 at 7.11.04 PM.jpeg') ?>" placeholder="Client source filename">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="featured_image">Featured Image Path *</label>
                            <input type="text" name="featured_image" id="featured_image" class="form-control-adm" required value="<?= htmlspecialchars($_POST['featured_image'] ?? $proj['featured_image'] ?? '/images/projects/serangoon-featured-thumb.webp') ?>">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label" for="video_path">Project Video Path (Optional)</label>
                                <input type="text" name="video_path" id="video_path" class="form-control-adm" value="<?= htmlspecialchars($_POST['video_path'] ?? $proj['video_path'] ?? '/images/projects/serangoon-board-video.mp4') ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="video_poster">Video Poster Image</label>
                                <input type="text" name="video_poster" id="video_poster" class="form-control-adm" value="<?= htmlspecialchars($_POST['video_poster'] ?? $proj['video_poster'] ?? '/images/projects/serangoon-board-full.webp') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Publication, Governance & SEO -->
            <div>
                <div class="table-card" style="margin-bottom: 24px;">
                    <div class="table-header">
                        <h3>Publication Gate</h3>
                    </div>
                    <div style="padding: 24px;">
                        <div class="form-group">
                            <label class="form-label" for="publication_state">Publication State *</label>
                            <select name="publication_state" id="publication_state" class="form-control-adm">
                                <option value="draft" <?= (($_POST['publication_state'] ?? $proj['publication_state'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft (Internal Preview Only)</option>
                                <option value="published" <?= (($_POST['publication_state'] ?? $proj['publication_state'] ?? '') === 'published') ? 'selected' : '' ?>>Published (Live Website)</option>
                                <option value="archived" <?= (($_POST['publication_state'] ?? $proj['publication_state'] ?? '') === 'archived') ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Dated client approval evidence <input name="approval_evidence" class="form-control-adm" placeholder="Client confirmation reference and date"></label>
                            <label class="form-label" for="approval_status">Client Approval Status</label>
                            <select name="approval_status" id="approval_status" class="form-control-adm">
                                <option value="pending" <?= (($_POST['approval_status'] ?? $proj['approval_status'] ?? 'pending') === 'pending') ? 'selected' : '' ?>>Pending Confirmation</option>
                                <option value="approved" <?= (($_POST['approval_status'] ?? $proj['approval_status'] ?? '') === 'approved') ? 'selected' : '' ?>>Approved by Client</option>
                                <option value="restricted" <?= (($_POST['approval_status'] ?? $proj['approval_status'] ?? '') === 'restricted') ? 'selected' : '' ?>>Restricted / Broad Location Only</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="display_order">Display Order</label>
                            <input type="number" name="display_order" id="display_order" class="form-control-adm" value="<?= htmlspecialchars($_POST['display_order'] ?? $proj['display_order'] ?? 1) ?>">
                        </div>

                        <button type="submit" class="btn-adm btn-adm-primary" style="width: 100%; padding: 12px; margin-top: 12px; font-weight: 700;">
                            <?= $isEdit ? 'Save Changes' : 'Create Project' ?>
                        </button>
                    </div>
                </div>

                <div class="table-card">
                    <div class="table-header">
                        <h3>SEO Metadata</h3>
                    </div>
                    <div style="padding: 24px;">
                        <div class="form-group">
                            <label class="form-label" for="og_title">Social & Page Title</label>
                            <input type="text" name="og_title" id="og_title" class="form-control-adm" value="<?= htmlspecialchars($_POST['og_title'] ?? $proj['og_title'] ?? '') ?>" placeholder="Title for Open Graph and search snippets">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="og_description">Social Description</label>
                            <textarea name="og_description" id="og_description" class="form-control-adm" rows="3" placeholder="Factual snippet for search and social preview..."><?= htmlspecialchars($_POST['og_description'] ?? $proj['og_description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
