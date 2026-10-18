<?php
/**
 * Project Case Study Detail Page - Tabeeb Contractor Singapore
 * URL Pattern: /projects/{slug}
 */

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/business_tracker.php';

$slug = trim($_GET['slug'] ?? '');
$isStaffPreview = is_logged_in() && has_permission('projects.manage');

// If staff preview, allow viewing drafts; otherwise strictly published & approved only
$project = get_project_by_slug($slug, !$isStaffPreview);

if (!$project) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$isStaffPreviewMode = $isStaffPreview && (($project['publication_state'] ?? '') !== 'published' || ($project['approval_status'] ?? '') !== 'approved');
if ($isStaffPreviewMode) {
    $GLOBALS['isStaffPreview'] = true;
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$company = get_company_info();
$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/projects/' . htmlspecialchars($project['slug']);
$ogTitle = $project['og_title'] ?: ($project['title'] . ' | ' . $company['brand_name']);
$ogDesc = $project['og_description'] ?: substr(strip_tags($project['factual_summary']), 0, 160);
$ogImage = (str_starts_with($project['featured_image'], 'http')) ? $project['featured_image'] : ($baseUrl . '/' . ltrim($project['featured_image'], '/'));

$mediaItems = get_project_media($project['id']);
$beforeMedia = array_filter($mediaItems, fn($m) => $m['media_stage'] === 'before');
$wipMedia = array_filter($mediaItems, fn($m) => $m['media_stage'] === 'in_progress');
$completedMedia = array_filter($mediaItems, fn($m) => $m['media_stage'] === 'completed');
$galleryMedia = array_filter($mediaItems, fn($m) => $m['media_stage'] === 'gallery');

$pageTitle = htmlspecialchars($ogTitle);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars($ogDesc) ?>">
    <?php if ($isStaffPreviewMode): ?>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <?php endif; ?>

    <!-- Geo Targeting Singapore -->
    <meta name="geo.region" content="SG">
    <meta name="geo.placename" content="Singapore">
    <meta name="geo.position" content="1.336358;103.911744">
    <meta name="ICBM" content="1.336358, 103.911744">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $canonicalUrl ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($company['brand_name']) ?>">
    <meta property="og:locale" content="en_SG">

    <!-- Favicon & Styles -->
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
    <link rel="stylesheet" href="/style.css?v=3.7">

    <?php require_once __DIR__ . '/includes/tracking.php'; render_gtm_head(); ?>

    <style>
        .proj-hero {
            padding: 130px 0 50px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            position: relative;
        }
        .proj-breadcrumb {
            font-size: 0.9rem;
            color: #94a3b8;
            margin-bottom: 16px;
        }
        .proj-breadcrumb a {
            color: #38bdf8;
            text-decoration: none;
        }
        .proj-hero h1 {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 12px;
            color: #ffffff;
        }
        .proj-meta-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            font-size: 0.95rem;
            color: #cbd5e1;
            margin-top: 16px;
        }
        .proj-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .proj-summary-box {
            background: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            margin: -30px auto 40px;
            position: relative;
            z-index: 10;
        }
        .stages-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 48px;
        }
        .stage-card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }
        .stage-card-header {
            padding: 14px 18px;
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stage-card.stage-before .stage-card-header { background: #fee2e2; color: #991b1b; }
        .stage-card.stage-wip .stage-card-header { background: #fef3c7; color: #92400e; }
        .stage-card.stage-completed .stage-card-header { background: #dcfce7; color: #166534; }
        .stage-img-wrap {
            width: 100%;
            height: 380px;
            background: #f1f5f9;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stage-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .stage-img-wrap img:hover {
            transform: scale(1.02);
        }
        .stage-caption {
            padding: 14px 18px;
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.5;
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
        }
        .board-viewer-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 36px 30px;
            border: 1px solid #e2e8f0;
            margin-bottom: 48px;
            text-align: center;
        }
        .board-viewer-img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
            border: 1px solid #cbd5e1;
            margin-top: 20px;
        }
        .video-wrapper {
            max-width: 800px;
            margin: 20px auto 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
            background: #000;
        }
        .video-wrapper video {
            width: 100%;
            display: block;
        }
        .cta-banner {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border-radius: 16px;
            padding: 40px 32px;
            color: #ffffff;
            text-align: center;
            margin-bottom: 60px;
        }
        .cta-banner h3 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 12px;
            color: #ffffff;
        }
        .cta-banner p {
            font-size: 1.05rem;
            color: #e0f2fe;
            max-width: 650px;
            margin: 0 auto 24px;
        }
        .btn-cta-white {
            display: inline-flex;
            align-items: center;
            padding: 12px 28px;
            background: #ffffff;
            color: #0369a1;
            font-weight: 700;
            border-radius: 8px;
            text-decoration: none;
            margin: 0 8px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.1);
        }
        .btn-cta-outline {
            display: inline-flex;
            align-items: center;
            padding: 12px 28px;
            background: rgba(255,255,255,0.15);
            color: #ffffff;
            font-weight: 700;
            border-radius: 8px;
            text-decoration: none;
            margin: 0 8px;
            border: 1px solid rgba(255,255,255,0.4);
        }
        @media (max-width: 900px) {
            .stages-grid {
                grid-template-columns: 1fr;
            }
            .stage-img-wrap {
                height: 320px;
            }
            .proj-hero h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php render_gtm_body(); ?>
    <?php require_once __DIR__ . '/includes/public_header.php'; ?>

    <?php if ($project['publication_state'] === 'draft'): ?>
        <div style="background: #fef08a; border-bottom: 1px solid #fde047; color: #854d0e; padding: 10px 20px; text-align: center; font-weight: 600; font-size: 13px;">
            ⚠️ STAFF PREVIEW MODE: This project case study is currently in <strong>DRAFT</strong> state and is not visible to public visitors.
        </div>
    <?php endif; ?>

    <!-- Project Hero -->
    <header class="proj-hero">
        <div class="container" style="max-width: 1140px; margin: 0 auto; padding: 0 20px;">
            <div class="proj-breadcrumb">
                <a href="/">Home</a> / <a href="/projects">Projects</a> / <span><?= htmlspecialchars($project['title']) ?></span>
            </div>
            <h1><?= htmlspecialchars($project['title']) ?></h1>
            <div class="proj-meta-bar">
                <div class="proj-meta-item">
                    <span>📍</span>
                    <strong>Location:</strong> <?= htmlspecialchars($project['public_location']) ?>
                </div>
                <?php if (!empty($project['service_name'])): ?>
                    <div class="proj-meta-item">
                        <span>🛠️</span>
                        <strong>Service:</strong> <?= htmlspecialchars($project['service_name']) ?>
                    </div>
                <?php endif; ?>
                <div class="proj-meta-item">
                    <span>📷</span>
                    <strong>Documentation:</strong> Client Project Board
                </div>
            </div>
        </div>
    </header>

    <main class="container" style="max-width: 1140px; margin: 0 auto; padding: 0 20px;">

        <!-- Factual Summary Box -->
        <section class="proj-summary-box">
            <h2 style="font-size: 1.3rem; font-weight: 700; color: #1e293b; margin-bottom: 12px;">Case Study Overview</h2>
            <p style="font-size: 1.05rem; line-height: 1.6; color: #475569; margin-bottom: 16px;">
                <?= htmlspecialchars($project['factual_summary']) ?>
            </p>
            <?php if (!empty($project['scope_details'])): ?>
                <div style="font-size: 0.95rem; color: #64748b; line-height: 1.5; border-top: 1px solid #f1f5f9; padding-top: 12px;">
                    <strong>Documented Scope:</strong> <?= htmlspecialchars($project['scope_details']) ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- 3 Stage Columns: Before / Work in Progress / Completed -->
        <section>
            <div style="text-align: center; margin-bottom: 32px;">
                <h2 style="font-size: 1.8rem; font-weight: 800; color: #0f172a;">Renovation Progress Stages</h2>
                <p style="color: #64748b; font-size: 1rem;">Direct visual record comparing initial site condition, cement screed levelling, and finished tiled flooring.</p>
            </div>

            <div class="stages-grid">

                <!-- Stage 1: Before -->
                <div class="stage-card stage-before">
                    <div class="stage-card-header">
                        <span>1. Before Condition</span>
                        <span style="font-size: 12px; font-weight: 600;">Existing Floor</span>
                    </div>
                    <div class="stage-img-wrap">
                        <img src="/images/projects/serangoon-before.webp" alt="Existing floor condition before renovation at Serangoon Avenue 3" loading="lazy" width="490" height="690">
                    </div>
                    <div class="stage-caption">
                        Existing worn tile condition, hollow floor segments, and substrate preparation area prior to hacking and screeding.
                    </div>
                </div>

                <!-- Stage 2: Work in Progress -->
                <div class="stage-card stage-wip">
                    <div class="stage-card-header">
                        <span>2. Work in Progress</span>
                        <span style="font-size: 12px; font-weight: 600;">Screed & Prep</span>
                    </div>
                    <div class="stage-img-wrap">
                        <img src="/images/projects/serangoon-in-progress.webp" alt="Cement screeding and floor levelling work in progress at Serangoon Avenue 3" loading="lazy" width="490" height="690">
                    </div>
                    <div class="stage-caption">
                        Application of cement screed base, surface levelling across the living area, and preparatory alignment for tile laying.
                    </div>
                </div>

                <!-- Stage 3: Completed -->
                <div class="stage-card stage-completed">
                    <div class="stage-card-header">
                        <span>3. Completed Finish</span>
                        <span style="font-size: 12px; font-weight: 600;">Tiled Finish</span>
                    </div>
                    <div class="stage-img-wrap">
                        <img src="/images/projects/serangoon-completed.webp" alt="Completed tiled floor finish at Serangoon Avenue 3" loading="lazy" width="490" height="690">
                    </div>
                    <div class="stage-caption">
                        Completed polished floor tile installation with seamless joint grouting and perimeter skirting finish.
                    </div>
                </div>

            </div>
        </section>

        <!-- Full Board Documentation Viewer -->
        <section class="board-viewer-section">
            <h2 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Authentic Project Documentation Board</h2>
            <p style="color: #64748b; font-size: 0.95rem; max-width: 650px; margin: 0 auto;">
                Full documentation board provided from project site (Site Label: <?= htmlspecialchars($project['location_label'] ?: $project['public_location']) ?>).
            </p>
            <a href="/images/projects/serangoon-board-full.webp" target="_blank" title="Click to view full-resolution board">
                <img src="/images/projects/serangoon-board-full.webp" alt="Complete project documentation board for Serangoon Avenue 3" class="board-viewer-img" loading="lazy" width="1200" height="800">
            </a>
            <div style="font-size: 12px; color: #94a3b8; margin-top: 10px;">
                🔍 Click image above to view full-resolution board in a new tab
            </div>
        </section>

        <!-- Optional Video Asset (Below the Fold, User Controlled) -->
        <?php if (!empty($project['video_path'])): ?>
            <section style="margin-bottom: 48px; text-align: center;">
                <h2 style="font-size: 1.4rem; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Project Documentation Video</h2>
                <p style="color: #64748b; font-size: 0.95rem;">Client-supplied 15-second overview of the Serangoon Avenue 3 project board.</p>
                <div class="video-wrapper">
                    <video controls preload="none" poster="<?= htmlspecialchars($project['video_poster'] ?: '/images/projects/serangoon-board-full.webp') ?>">
                        <source src="<?= htmlspecialchars($project['video_path']) ?>" type="video/mp4">
                        Your browser does not support HTML5 video playback.
                    </video>
                </div>
            </section>
        <?php endif; ?>

        <!-- Supporting Renovation Stages Composite -->
        <section style="background: #ffffff; border-radius: 16px; padding: 36px 30px; border: 1px solid #e2e8f0; margin-bottom: 48px; text-align: center;">
            <h2 style="font-size: 1.4rem; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Renovation Stages Overview</h2>
            <p style="color: #64748b; font-size: 0.95rem; max-width: 700px; margin: 0 auto 20px;">
                Multi-stage contractor reference composite showing masonry, screeding, and tiling phases. Privacy blurs preserved as supplied.
            </p>
            <img src="/images/projects/renovation-stages-collage.webp" alt="Renovation process stages reference composite" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #cbd5e1;" loading="lazy" width="1200" height="1700">
        </section>

        <!-- Contextual Tiling Enquiry CTA Banner -->
        <section class="cta-banner">
            <h3>Planning a Flooring or Tiling Renovation?</h3>
            <p>
                Speak directly with Tabeeb Contractor for residential and commercial flooring, hacking, screeding, and tile installation across Singapore.
            </p>
            <div>
                <a href="/contact?service=tiling-works" class="btn-cta-white">Request Quotation</a>
                <a href="https://wa.me/<?= htmlspecialchars($company['phone_raw']) ?>?text=<?= urlencode('Hi Tabeeb Contractor, I saw your Serangoon Avenue 3 flooring project and would like to enquire about tiling works.') ?>" target="_blank" class="btn-cta-outline">WhatsApp Enquiry</a>
            </div>
        </section>

    </main>

    <?php require_once __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
