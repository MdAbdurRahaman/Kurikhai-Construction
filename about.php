<?php
/**
 * About Tabeeb Contractor - Company Profile
 * URL: https://tabeebgroup.com/about
 */

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/business_tracker.php';
require_once __DIR__ . '/includes/tracking.php';

$company = get_company_info();
$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/about';
$pageTitle = 'About ' . htmlspecialchars($company['brand_name']) . ' | Direct Contractor Singapore';
$pageDesc = "Learn about " . htmlspecialchars($company['brand_name']) . ", providing reliable direct renovations, masonry, and commercial reinstatements across Singapore.";
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
    <meta name="geo.position" content="1.3364;103.9066">
    <meta name="ICBM" content="1.3364, 103.9066">

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

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "AboutPage",
          "@id": "https://www.tabeebgroup.com/about#webpage",
          "url": "https://www.tabeebgroup.com/about",
          "name": <?= json_encode($pageTitle) ?>,
          "isPartOf": {
            "@type": "WebSite",
            "name": <?= json_encode($company['brand_name']) ?>,
            "url": "https://www.tabeebgroup.com/"
          }
        },
        {
          "@type": "GeneralContractor",
          "@id": "https://www.tabeebgroup.com/#organization",
          "name": <?= json_encode($company['brand_name']) ?>,
          "telephone": <?= json_encode($company['phone']) ?>,
          "email": <?= json_encode($company['email']) ?>,
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park",
            "addressLocality": "Singapore",
            "postalCode": "417943",
            "addressCountry": "SG"
          },
          "sameAs": [
            <?= json_encode($company['google_maps_url']) ?>
          ]
        },
        {
          "@type": "BreadcrumbList",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "https://www.tabeebgroup.com/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "About Us",
              "item": "https://www.tabeebgroup.com/about"
            }
          ]
        }
      ]
    }
    </script>
    
    <link rel="stylesheet" href="/style.css?v=3.5">
    <?php render_gtm_head(); ?>
</head>
<body>
    <?php render_gtm_body(); ?>
    <?php $activeNav = 'about'; require_once __DIR__ . '/includes/public_header.php'; ?>

    <!-- Subpage Hero Header -->
    <section class="subpage-hero">
        <div class="container">
            <h1 class="fade-in-up">About <?= htmlspecialchars($company['brand_name']) ?></h1>
            <div class="breadcrumbs fade-in-up">
                <a href="/">Home</a> &gt; <span>About Us</span>
            </div>
        </div>
    </section>

    <!-- Company Bio & Values -->
    <section class="section" id="company-profile">
        <div class="container">
            <div class="about-split" style="margin-bottom: 80px;">
                <div class="about-content fade-in-up">
                    <span class="section-tagline">Who We Are</span>
                    <h2>Contracting Craftsmanship in Singapore</h2>
                    <p class="about-highlight">Operating in Singapore, <?= htmlspecialchars($company['brand_name']) ?> focuses on structured site management, safety compliance, and durable finishing quality.</p>
                    <p class="about-para">We service residential estates (HDB, condominiums, landed homes), commercial offices, retail spaces, and industrial units across Singapore. Our teams feature experienced site coordinators, skilled masonry tilers, plumbers, electricians, and waterproofing specialists. By providing direct trade services, we eliminate coordination delays and maintain clear site accountability.</p>
                    <p class="about-para">From commercial tenancy reinstatement, structural hacking, and cement screeding to waterproofing rectification and alterations, we work with property owners through project scoping, statutory permit compliance, and practical site handover.</p>
                </div>
                <div class="about-image-stack fade-in-up">
                    <img class="about-img-1" src="/images/hero-bg.webp" alt="Tabeeb Contractor Operations Singapore" loading="lazy" width="800" height="500">
                </div>
            </div>

            <!-- Core Values Grid -->
            <div class="section-header text-center fade-in-up" style="margin-top: 100px;">
                <span class="section-tagline">Our Pillars</span>
                <h2 class="section-title">Values & Quality Standards</h2>
            </div>
            <div class="features-grid" style="margin-top: 0; padding-top: 0; box-shadow: none; background: transparent;">
                <div class="feature-card fade-in-up" style="box-shadow: var(--shadow-md);">
                    <div class="feature-icon">🛡️</div>
                    <h3>Safety & Quality Control</h3>
                    <p>We prioritize onsite safety through systematic hazard identification, proper access equipment, and daily site coordination.</p>
                </div>
                <div class="feature-card fade-in-up" style="box-shadow: var(--shadow-md);">
                    <div class="feature-icon">✨</div>
                    <h3>Durable Craftsmanship</h3>
                    <p>We utilize proven waterproofing systems, quality piping materials, luxury vinyl flooring, cement screed, and neat drywall finishes.</p>
                </div>
                <div class="feature-card fade-in-up" style="box-shadow: var(--shadow-md);">
                    <div class="feature-icon">💰</div>
                    <h3>Direct Contractor Rates</h3>
                    <p>Direct trade pricing with transparent itemized quotations, scheduled delivery milestones, and standard handover preparation.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Work Process Section -->
    <section class="section section-bg-dark" id="workflow">
        <div class="container">
            <div class="section-header text-center fade-in-up">
                <span class="section-tagline" style="color: var(--color-accent);">How We Work</span>
                <h2 class="section-title">Our Project Lifecycle</h2>
                <p class="section-desc" style="color: var(--color-text-muted);">We follow a structured, transparent process from initial site assessment to final inspection.</p>
            </div>

            <div class="workflow-grid">
                <div class="workflow-card fade-in-up">
                    <div class="workflow-number">01</div>
                    <h3>Consultation</h3>
                    <p>We assess your property floor plans, discuss budget parameters, and identify service needs (demolition, plumbing, screeding, tiling).</p>
                </div>
                <div class="workflow-card fade-in-up">
                    <div class="workflow-number">02</div>
                    <h3>Planning & Permits</h3>
                    <p>We review technical requirements. Relevant works are coordinated with building management (MCST) or HDB guidelines.</p>
                </div>
                <div class="workflow-card fade-in-up">
                    <div class="workflow-number">03</div>
                    <h3>Site Execution</h3>
                    <p>Our trade teams carry out hacking, MEP routing, flooring, false ceilings, painting, and waterproofing works under dedicated supervision.</p>
                </div>
                <div class="workflow-card fade-in-up">
                    <div class="workflow-number">04</div>
                    <h3>Handover</h3>
                    <p>A structured quality check is conducted. We verify electrical continuity, inspect water areas, and hand over a clean space.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="cta-banner fade-in-up">
        <div class="container">
            <h2>Ready to Consult with Our Project Team?</h2>
            <p>Schedule a site visit today. We provide itemized quotations so you know exactly what is included in your renovation scope.</p>
            <a href="javascript:void(0)" class="btn btn-primary open-quote-modal-btn">Request Consultation</a>
        </div>
    </section>

    <?php require_once __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
