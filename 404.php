<?php
/**
 * 404 Not Found Page - Tabeeb Contractor Singapore
 */
http_response_code(404);
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/business_tracker.php';

$company = get_company_info();
$pageTitle = 'Page Not Found (404) | ' . htmlspecialchars($company['brand_name']);
$canonicalUrl = APP_URL . '/404';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
    <link rel="stylesheet" href="/style.css?v=3.5">
    <?php require_once __DIR__ . '/includes/tracking.php'; render_gtm_head(); ?>
    <style>
        .error-section {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 120px 20px 80px;
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
        }
        .error-card {
            max-width: 650px;
            background: #ffffff;
            border-radius: 16px;
            padding: 50px 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .error-code {
            font-size: 5rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 12px;
            letter-spacing: -2px;
        }
        .error-title {
            font-size: 1.6rem;
            color: #1e293b;
            margin-bottom: 16px;
            font-weight: 700;
        }
        .error-desc {
            font-size: 1.05rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .error-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            padding: 12px 28px;
            background: #0f172a;
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-home:hover {
            background: #1e293b;
            transform: translateY(-2px);
        }
        .btn-services {
            display: inline-flex;
            align-items: center;
            padding: 12px 28px;
            background: #e2e8f0;
            color: #0f172a;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-services:hover {
            background: #cbd5e1;
            transform: translateY(-2px);
        }
        .popular-links {
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            font-size: 0.95rem;
            color: #64748b;
        }
        .popular-links a {
            color: #0284c7;
            text-decoration: none;
            margin: 0 8px;
            font-weight: 500;
        }
        .popular-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php render_gtm_body(); ?>

    <main data-page-error="not_found" class="error-section">
        <div class="error-card">
            <div class="error-code">404</div>
            <h1 class="error-title">Page or Service Not Found</h1>
            <p class="error-desc">
                The page or specialist service you are looking for may have been moved, renamed, or is currently unavailable. Please verify the URL or explore our core commercial and residential contractor services.
            </p>
            <div class="error-actions">
                <a href="/" class="btn-home">Return to Homepage</a>
                <a href="/services" class="btn-services">View All Services</a>
                <a href="/contact" class="btn-services">Contact Office</a>
            </div>
            <div class="popular-links">
                Quick Links:
                <a href="/renovation-contractor-singapore">Renovation Contractor</a> •
                <a href="/hdb-renovation">HDB Renovation</a> •
                <a href="/tiling-works">Tiling Works</a> •
                <a href="/waterproofing">Waterproofing</a> •
                <a href="/reinstatement">Reinstatement</a>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
