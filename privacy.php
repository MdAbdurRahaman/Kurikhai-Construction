<?php
/**
 * Privacy Policy - Tabeeb Contractor Singapore
 * URL: https://tabeebgroup.com/privacy
 */

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/business_tracker.php';
require_once __DIR__ . '/includes/tracking.php';

$company = get_company_info();
$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/privacy';
$pageTitle = 'Privacy Policy | ' . htmlspecialchars($company['brand_name']) . ' Singapore';
$pageDesc = "Privacy Policy for Tabeeb Contractor Singapore. Information on how we collect, process, and protect customer inquiry data under the Singapore Personal Data Protection Act (PDPA).";
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
    <meta property="og:site_name" content="<?= htmlspecialchars($company['brand_name']) ?>">
    <meta property="og:locale" content="en_SG">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="/style.css?v=3.5">

    <!-- Google Analytics & Tag Manager (Privacy-preserving loader) -->
    <?php render_gtm_head(); ?>
</head>
<body>
    <?php render_gtm_body(); ?>

    <?php require_once __DIR__ . '/includes/public_header.php'; ?>

    <main style="padding: 140px 0 80px;">
        <div class="container" style="max-width: 800px;">
            <div style="background: #ffffff; border-radius: 16px; padding: 40px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h1 style="font-size: 32px; color: #07274d; margin-bottom: 12px; font-family: var(--font-heading);">Privacy Policy</h1>
                <p style="color: #64748b; font-size: 14px; margin-bottom: 30px;">
                    Last updated: 14 October 2026 &bull; Governing Law: Singapore (PDPA)
                </p>

                <div style="font-size: 15px; line-height: 1.8; color: #334155;">
                    <div style="background: #f8fafc; border-left: 4px solid #00b4d8; padding: 16px 20px; border-radius: 0 8px 8px 0; margin-bottom: 25px; font-size: 13px; color: #475569;">
                        <strong>Notice:</strong> This policy outlines the personal data practices of <strong><?= htmlspecialchars($company['brand_name']) ?></strong> (UEN: <?= htmlspecialchars($company['uen']) ?>) for website inquiries. This document is provided for transparency under the Singapore Personal Data Protection Act (PDPA).
                    </div>

                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">1. Collection of Personal Data</h2>
                    <p>When you submit an online quote inquiry, contact request, or site survey booking through our website, we may collect the following personal information:</p>
                    <ul style="padding-left: 20px; margin-bottom: 16px;">
                        <li><strong>Contact Information:</strong> Full name, telephone/mobile number (for calls and WhatsApp follow-up), and email address.</li>
                        <li><strong>Project Details:</strong> Type of property, requested contracting service (e.g., reinstatement, hacking, tiling, painting), estimated budget, and project description.</li>
                        <li><strong>Technical Data:</strong> IP hash and timestamp strictly for fraud prevention, rate limiting, and spam protection.</li>
                    </ul>

                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">2. Purpose of Collection and Processing</h2>
                    <p>We collect and use your personal information exclusively for the following purposes:</p>
                    <ul style="padding-left: 20px; margin-bottom: 16px;">
                        <li>Evaluating your project scope and preparing an itemized quotation.</li>
                        <li>Coordinating on-site inspections, measurements, and site surveys across Singapore.</li>
                        <li>Communicating updates, schedule confirmations, and handover details via telephone, WhatsApp, or email.</li>
                        <li>Preventing automated bot abuse and maintaining platform security.</li>
                    </ul>

                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">3. Data Storage & Security</h2>
                    <p>Inquiry records are stored in a secure, authenticated database with strict role-based access controls. We do not sell, rent, or trade your personal data to external marketing companies. Inquiries are only accessible by authorized project estimators and site supervisors for project execution.</p>

                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">4. Retention and Disposal Policy</h2>
                    <p>We retain customer quote inquiries as necessary to fulfill project estimation, warranty coordination, audit requirements, and dispute resolution. In accordance with the Singapore Personal Data Protection Act (PDPA), personal data that is no longer required for legal or business operational purposes is subject to archiving or secure disposal upon customer request or periodic operational review.</p>

                    <h2 style="font-size:20px;margin:28px 0 12px;">Optional analytics and performance measurement</h2>
                    <p>With your permission, we measure page visits, traffic sources, device categories, form interactions, contact-button clicks and website speed. Browsing identifiers are random and stored as hashes in our database. Our measurement records exclude enquiry names, email addresses, phone numbers and project messages. Raw first-party analytics is retained for up to 90 days, with expired records removed during periodic processing. Analytics may also be sent to Google Analytics 4 when configured. Google processes this data under its own privacy terms and the account retention settings.</p>
                    <p>Analytics is optional. You can decline or withdraw permission using the “Analytics preferences” button. Quote enquiries continue to work. A phone or WhatsApp click records intent and does not confirm a completed conversation.</p>
                    <h2 style="font-size: 20px; color: #07274d; margin: 28px 0 12px;">5. Your Rights and Data Protection Officer Contact</h2>
                    <p>You have the right to request access to or correction of your personal data, or to withdraw your consent for future communications at any time. To make a request, please contact our designated privacy coordinator:</p>

                    <div style="background: #f1f5f9; padding: 18px 24px; border-radius: 8px; margin-top: 16px;">
                        <strong><?= htmlspecialchars($company['brand_name']) ?></strong><br>
                        UEN: <?= htmlspecialchars($company['uen']) ?><br>
                        Address: <?= htmlspecialchars($company['address']) ?><br>
                        Email: <a href="mailto:<?= htmlspecialchars($company['email']) ?>" style="color: #0284c7;"><?= htmlspecialchars($company['email']) ?></a><br>
                        Hotline: <a href="tel:<?= htmlspecialchars($company['phone']) ?>" style="color: #0284c7;"><?= htmlspecialchars($company['phone']) ?></a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
