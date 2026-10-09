<?php
require_once __DIR__ . '/includes/data.php';

$company = get_company_info();
$baseUrl = get_base_url();
$pageTitle = 'Privacy Policy | Tabeeb Contractor Singapore';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="Privacy Policy for Tabeeb Contractor Singapore. Information on how we collect, process, and protect customer inquiry data under the Singapore Personal Data Protection Act (PDPA).">
    
    <link rel="canonical" href="<?= $baseUrl ?>/privacy">
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">
    <link rel="stylesheet" href="/style.css?v=3.5">
</head>
<body>
    <!-- Top Header -->
    <header id="header" class="header scrolled">
        <div class="container header-container">
            <a href="/" class="logo">
                <img src="/images/logo-horizontal.png?v=3.3" alt="Tabeeb Contractor">
            </a>
            <nav class="nav-desktop">
                <ul class="nav-list">
                    <li><a href="/" class="nav-link">Home</a></li>
                    <li><a href="/services" class="nav-link">Services</a></li>
                    <li><a href="/about" class="nav-link">About Us</a></li>
                    <li><a href="/blog" class="nav-link">Blog</a></li>
                    <li><a href="/contact" class="nav-link">Contact</a></li>
                </ul>
            </nav>
            <div class="header-action">
                <a href="/contact" class="btn btn-primary">Get a Quote</a>
            </div>
        </div>
    </header>

    <main style="padding: 140px 0 80px;">
        <div class="container" style="max-width: 800px;">
            <div style="background: #ffffff; border-radius: 16px; padding: 40px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h1 style="font-size: 32px; color: #07274d; margin-bottom: 12px; font-family: var(--font-heading);">Privacy Policy</h1>
                <p style="color: #64748b; font-size: 14px; margin-bottom: 30px;">
                    Last updated: 9 October 2026 &bull; Governing Law: Singapore (PDPA)
                </p>

                <div style="font-size: 15px; line-height: 1.8; color: #334155;">
                    <div style="background: #f8fafc; border-left: 4px solid #00b4d8; padding: 16px 20px; border-radius: 0 8px 8px 0; margin-bottom: 25px; font-size: 13px; color: #475569;">
                        <strong>Notice:</strong> This policy outlines the personal data practices of <strong><?= htmlspecialchars($company['brand_name']) ?></strong> (UEN: <?= htmlspecialchars($company['uen']) ?>) for website inquiries. This document is provided for transparency and should be reviewed with qualified legal counsel.
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
                    <p>We retain customer quote inquiries for a standard period of 365 days to assist with follow-ups, warranty verification, and customer service. Data beyond this period is routinely archived or permanently deleted in accordance with the Singapore Personal Data Protection Act (PDPA).</p>

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

    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-container">
            <div class="footer-grid">
                <div class="footer-col">
                    <img src="/images/logo-horizontal.png?v=3.3" alt="Tabeeb Contractor" class="footer-logo">
                    <p style="color: #94a3b8; font-size: 14px; margin-top: 12px;">
                        <?= htmlspecialchars($company['brand_name']) ?> &bull; UEN: <?= htmlspecialchars($company['uen']) ?>
                    </p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($company['brand_name']) ?>. All rights reserved.</p>
                <div class="footer-legal-links">
                    <a href="/privacy">Privacy Policy</a>
                    <a href="/terms">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
