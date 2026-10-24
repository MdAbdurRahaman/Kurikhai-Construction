<?php
/**
 * Terms of Service - Tabeeb Contractor Singapore
 * URL: https://tabeebgroup.com/terms
 */

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/business_tracker.php';
require_once __DIR__ . '/includes/tracking.php';

$company = get_company_info();
$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/terms';
$pageTitle = 'Terms of Service | ' . htmlspecialchars($company['brand_name']) . ' Singapore';
$pageDesc = "Terms of Service for Tabeeb Contractor Singapore. Conditions for website usage, quotation requests, and site assessment surveys.";
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

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $canonicalUrl ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:image" content="<?= $baseUrl ?>/images/hero-bg.webp">
    <meta property="og:site_name" content="Tabeeb Contractor">
    <meta property="og:locale" content="en_SG">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="/style.css?v=3.7">

    <!-- Google Analytics & Tag Manager (Privacy-preserving loader) -->
    <?php render_gtm_head(); ?>
</head>
<body>
    <?php render_gtm_body(); ?>

    <?php require_once __DIR__ . '/includes/public_header.php'; ?>

    <main style="padding: 140px 0 80px;">
        <div class="container" style="max-width: 800px;">
            <div style="background: #ffffff; border-radius: 16px; padding: 40px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h1 style="font-size: 32px; color: #07274d; margin-bottom: 12px; font-family: var(--font-heading);">Terms of Service</h1>
                <p style="color: #64748b; font-size: 14px; margin-bottom: 30px;">
                    Last updated: 11 October 2026 &bull; Jurisdiction: Republic of Singapore
                </p>

                <div style="font-size: 15px; line-height: 1.8; color: #334155;">
                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">1. Agreement to Terms</h2>
                    <p>By accessing the website of <strong><?= htmlspecialchars($company['brand_name']) ?></strong> (UEN: <?= htmlspecialchars($company['uen']) ?>) or submitting an online quotation request, you agree to these Terms of Service. If you disagree with any part of these terms, please contact us directly via telephone before using this website.</p>

                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">2. Quotations and On-Site Surveys</h2>
                    <p>Online cost calculators, estimates, or indicative budget ranges provided on this website are for preliminary guidance only. A binding quotation is issued only after on-site measurement, site condition assessment, and formal agreement between the parties.</p>

                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">3. Project Scopes and Permits</h2>
                    <p>All renovation, hacking, reinstatement, and construction works are subject to site-specific building management guidelines, property ownership approvals, and applicable Singapore statutory requirements. Permitting responsibilities, deposit terms, and work schedules will be explicitly specified in individual project contracts.</p>

                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">4. Intellectual Property</h2>
                    <p>All text, diagrams, original brand marks, and layout content on this website are the intellectual property of <?= htmlspecialchars($company['brand_name']) ?> and may not be reproduced without prior written permission.</p>

                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">5. Contact and Inquiries</h2>
                    <p>For questions regarding these terms, please contact our Singapore office:</p>
                    <div style="background: #f1f5f9; padding: 18px 24px; border-radius: 8px; margin-top: 16px;">
                        <strong><?= htmlspecialchars($company['brand_name']) ?></strong><br>
                        Address: <?= htmlspecialchars($company['address']) ?><br>
                        Hotline: <a href="tel:<?= htmlspecialchars($company['phone']) ?>" style="color: #0284c7;"><?= htmlspecialchars($company['phone']) ?></a><br>
                        Email: <a href="mailto:<?= htmlspecialchars($company['email']) ?>" style="color: #0284c7;"><?= htmlspecialchars($company['email']) ?></a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
