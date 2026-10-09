<?php
require_once __DIR__ . '/includes/data.php';

$company = get_company_info();
$baseUrl = get_base_url();
$pageTitle = 'Terms of Service | Tabeeb Contractor Singapore';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="Terms of Service for Tabeeb Contractor Singapore. Conditions for website usage, quotation requests, and site assessment surveys.">
    
    <link rel="canonical" href="<?= $baseUrl ?>/terms">
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
                <h1 style="font-size: 32px; color: #07274d; margin-bottom: 12px; font-family: var(--font-heading);">Terms of Service</h1>
                <p style="color: #64748b; font-size: 14px; margin-bottom: 30px;">
                    Last updated: 9 October 2026 &bull; Jurisdiction: Republic of Singapore
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
                        Hotline: <?= htmlspecialchars($company['phone']) ?><br>
                        Email: <?= htmlspecialchars($company['email']) ?>
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
