<?php
/**
 * Projects & Completed Works Portfolio - Tabeeb Contractor Singapore
 * URL Pattern: /projects
 */

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/business_tracker.php';

$isStaffPreview = is_logged_in() && has_permission('projects.manage');
$projects = get_all_projects(!$isStaffPreview);

$company = get_company_info();
$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/projects';
$pageTitle = 'Completed Renovation & Contracting Projects | ' . htmlspecialchars($company['brand_name']);
$pageDesc = 'Explore authentic residential and commercial renovation case studies, flooring works, and documented project boards by Tabeeb Contractor Singapore.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    
    <!-- Geo Targeting Singapore -->
    <meta name="geo.region" content="SG">
    <meta name="geo.placename" content="Singapore">
    <meta name="geo.position" content="1.336358;103.911744">
    <meta name="ICBM" content="1.336358, 103.911744">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $canonicalUrl ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:image" content="<?= $baseUrl ?>/images/projects/serangoon-featured-thumb.webp">
    <meta property="og:site_name" content="<?= htmlspecialchars($company['brand_name']) ?>">
    <meta property="og:locale" content="en_SG">

    <!-- Favicon & Styles -->
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
    <link rel="stylesheet" href="/style.css?v=3.5">
    
    <?php require_once __DIR__ . '/includes/tracking.php'; render_gtm_head(); ?>

    <style>
        .projects-hero {
            padding: 130px 0 60px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            text-align: center;
        }
        .projects-hero h1 {
            font-size: 2.6rem;
            font-weight: 800;
            margin-bottom: 14px;
            color: #ffffff;
        }
        .projects-hero p {
            font-size: 1.1rem;
            color: #cbd5e1;
            max-width: 680px;
            margin: 0 auto;
        }
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 32px;
            margin: 50px 0 80px;
        }
        .project-card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            display: flex;
            flex-direction: column;
        }
        .project-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(0,0,0,0.1);
        }
        .project-card-thumb {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: #f1f5f9;
        }
        .project-card-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .project-card-badge {
            display: inline-block;
            align-self: flex-start;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            margin-bottom: 12px;
        }
        .project-card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
            line-height: 1.3;
        }
        .project-card-loc {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .project-card-summary {
            font-size: 0.95rem;
            color: #475569;
            line-height: 1.5;
            margin-bottom: 20px;
            flex-grow: 1;
        }
        .btn-view-proj {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 20px;
            background: #0f172a;
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.2s ease;
        }
        .btn-view-proj:hover {
            background: #0284c7;
        }
    </style>
</head>
<body>
    <?php render_gtm_body(); ?>
    <?php require_once __DIR__ . '/includes/public_header.php'; ?>

    <header class="projects-hero">
        <div class="container" style="max-width: 1140px; margin: 0 auto; padding: 0 20px;">
            <h1>Documented Renovation Projects</h1>
            <p>
                Authentic photographic records of our residential and commercial renovation stages across Singapore. No stock imagery—only genuine project boards and completed finishes.
            </p>
        </div>
    </header>

    <main class="container" style="max-width: 1140px; margin: 0 auto; padding: 0 20px;">
        <?php if (empty($projects)): ?>
            <div style="padding: 60px 20px; text-align: center; color: #64748b;">
                <p>New project case studies are currently being documented. Please check back soon.</p>
            </div>
        <?php else: ?>
            <div class="portfolio-grid">
                <?php foreach ($projects as $p): ?>
                    <article class="project-card">
                        <img src="<?= htmlspecialchars($p['featured_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="project-card-thumb" loading="lazy" width="490" height="325">
                        <div class="project-card-body">
                            <span class="project-card-badge"><?= htmlspecialchars($p['service_id'] ?? 'Renovation') ?></span>
                            <h2 class="project-card-title"><?= htmlspecialchars($p['title']) ?></h2>
                            <div class="project-card-loc">
                                <span>📍</span> <?= htmlspecialchars($p['public_location']) ?>
                            </div>
                            <p class="project-card-summary">
                                <?= htmlspecialchars(substr(strip_tags($p['factual_summary']), 0, 140)) ?>...
                            </p>
                            <a href="/projects/<?= htmlspecialchars($p['slug']) ?>" class="btn-view-proj">
                                View Case Study <span>→</span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
