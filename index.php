<?php
/**
 * Tabeeb Contractor Singapore - Official Homepage
 * URL: https://tabeebgroup.com/
 */

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/business_tracker.php';
require_once __DIR__ . '/includes/tracking.php';

$company = get_company_info();
$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/';
$pageTitle = 'Tabeeb Contractor – Renovation Contractor in Singapore';
$pageDesc = "Tabeeb Contractor is Singapore's direct renovation contractor. Specialist in residential & commercial renovation, hacking, tiling, vinyl, waterproofing, plumbing, electrical & ceilings. Call: " . $company['phone'] . ".";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="keywords" content="Tabeeb Contractor, renovation contractor singapore, tiling contractor singapore, vinyl flooring contractor singapore, painting contractor singapore, waterproofing contractor singapore, hacking demolition contractor singapore, plastering contractor singapore, ceiling partition contractor singapore, reinstatement contractor singapore, electrical contractor singapore, plumbing contractor singapore">

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

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:image" content="<?= $baseUrl ?>/images/hero-bg.webp">
    <meta property="og:site_name" content="<?= htmlspecialchars($company['brand_name']) ?>">
    <meta property="og:locale" content="en_SG">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= $canonicalUrl ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="twitter:image" content="<?= $baseUrl ?>/images/hero-bg.webp">

    <!-- Schema.org JSON-LD (Verified Business Facts Only - Zero Fabricated Ratings) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": ["HomeAndConstructionBusiness", "GeneralContractor"],
          "@id": "<?= $baseUrl ?>/#organization",
          "name": <?= json_encode($company['brand_name']) ?>,
          "alternateName": "Tabeeb Contractor Singapore",
          "url": "<?= $baseUrl ?>/",
          "logo": "<?= $baseUrl ?>/images/logo-mark.png",
          "image": "<?= $baseUrl ?>/images/hero-bg.webp",
          "telephone": <?= json_encode($company['phone']) ?>,
          "email": <?= json_encode($company['email']) ?>,
          "priceRange": "$$",
          "description": "Direct renovation and commercial reinstatement contractor in Singapore. Specializing in hacking, demolition, false ceilings, partition walls, tiling, electrical, plumbing, painting, and waterproofing.",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park",
            "addressLocality": "Singapore",
            "postalCode": "417943",
            "addressCountry": "SG"
          },
          "geo": {
            "@type": "GeoCoordinates",
            "latitude": 1.336358,
            "longitude": 103.911744
          },
          "areaServed": {
            "@type": "AdministrativeArea",
            "name": "Singapore"
          },
          "sameAs": [
            <?= json_encode($company['google_maps_url']) ?>
          ],
          "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "Direct Renovation & Contracting Services",
            "itemListElement": [
              {
                "@type": "Offer",
                "itemOffered": {
                  "@type": "Service",
                  "name": "Renovation Contractor in Singapore",
                  "url": "<?= $baseUrl ?>/renovation-contractor-singapore",
                  "description": "Complete residential and commercial turnkey renovations."
                }
              },
              {
                "@type": "Offer",
                "itemOffered": {
                  "@type": "Service",
                  "name": "Tiling Works & Contractor",
                  "url": "<?= $baseUrl ?>/tiling-works",
                  "description": "Supply & installation of wall and floor tiles."
                }
              },
              {
                "@type": "Offer",
                "itemOffered": {
                  "@type": "Service",
                  "name": "Vinyl Flooring Works",
                  "url": "<?= $baseUrl ?>/vinyl-flooring",
                  "description": "Supply & installation of luxury waterproof SPC vinyl flooring."
                }
              },
              {
                "@type": "Offer",
                "itemOffered": {
                  "@type": "Service",
                  "name": "Waterproofing Works",
                  "url": "<?= $baseUrl ?>/waterproofing",
                  "description": "Wet area membranes and leak rectification."
                }
              },
              {
                "@type": "Offer",
                "itemOffered": {
                  "@type": "Service",
                  "name": "Hacking & Demolition Works",
                  "url": "<?= $baseUrl ?>/hacking-demolition",
                  "description": "Hacking of walls, floors, tiles, fixture dismantling & disposal."
                }
              },
              {
                "@type": "Offer",
                "itemOffered": {
                  "@type": "Service",
                  "name": "Reinstatement Works",
                  "url": "<?= $baseUrl ?>/reinstatement",
                  "description": "Commercial office tenancy reinstatement and landlord handover."
                }
              }
            ]
          }
        },
        {
          "@type": "BreadcrumbList",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "<?= $baseUrl ?>/"
            }
          ]
        }
      ]
    }
    </script>

    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="/style.css?v=3.5">

    <?php render_gtm_head(); ?>
</head>
<body>
    <?php render_gtm_body(); ?>
    <?php $activeNav = 'home'; require_once __DIR__ . '/includes/public_header.php'; ?>

    <!-- Hero Section -->
    <section class="hero" id="hero" style="background: linear-gradient(rgba(7, 39, 77, 0.88), rgba(15, 23, 42, 0.92)), url('/images/hero-bg.webp') center/cover no-repeat;">
        <div class="container">
            <div class="hero-content fade-in-up">
                <span class="hero-trusted-badge">🛡️ DIRECT RENOVATION CONTRACTOR</span>
                <h1><span class="hero-keyword-eyebrow"><?= htmlspecialchars($company['brand_name']) ?></span>Renovation Contractor in Singapore</h1>
                <div class="hero-pill-row">
                    <span class="hero-pill-item">Residential</span>
                    <span class="hero-pill-dot">&bull;</span>
                    <span class="hero-pill-item">Commercial</span>
                    <span class="hero-pill-dot">&bull;</span>
                    <span class="hero-pill-item">Direct Rates</span>
                    <span class="hero-pill-dot">&bull;</span>
                    <span class="hero-pill-item">Turnkey Delivery</span>
                </div>
                <p class="hero-desc">Building better spaces across Singapore. Direct trade specialist for hacking, tiling, vinyl flooring, waterproofing, plumbing, electrical, painting, plastering, ceilings, and reinstatement. Quality workmanship at competitive direct rates.</p>
                <div class="hero-actions">
                    <a href="javascript:void(0)" class="btn btn-primary open-quote-modal-btn">Request Project Quote</a>
                    <a href="#services-overview" class="btn btn-secondary">Explore 10 Core Services</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust & Quality Standards Bar -->
    <section class="trust-bar-section">
        <div class="container">
            <div class="trust-bar-grid">
                <div class="trust-item">
                    <div class="trust-icon">🛡️</div>
                    <div class="trust-text">
                        <strong>Quality Workmanship</strong>
                        <span>Strict Quality Control & Tested Materials</span>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon">🤝</div>
                    <div class="trust-text">
                        <strong>Reliable Team</strong>
                        <span>Experienced, Safe & Dedicated Trade Specialists</span>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon">⏱️</div>
                    <div class="trust-text">
                        <strong>Disciplined Delivery</strong>
                        <span>Punctual Milestones & Structured Scheduling</span>
                    </div>
                </div>
                <div class="trust-item">
                    <div class="trust-icon">💰</div>
                    <div class="trust-text">
                        <strong>Direct Pricing</strong>
                        <span>Direct Contractor Rates with Clear Itemized Quotations</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Floating Features Grid -->
    <section class="container">
        <div class="features-grid">
            <div class="feature-card fade-in-up">
                <div class="feature-icon">🔍</div>
                <h3>Site Condition Inspection</h3>
                <p>On-site measurement, site inspection, and transparent itemized quotation tailored to your architectural plans.</p>
            </div>
            <div class="feature-card fade-in-up">
                <div class="feature-icon">⚙️</div>
                <h3>End-to-End Solutions</h3>
                <p>We handle hacking, demolition, tiling, vinyl flooring, false ceiling, waterproofing, plumbing, electrical, and painting.</p>
            </div>
            <div class="feature-card fade-in-up">
                <div class="feature-icon">🤝</div>
                <h3>Dedicated Project Supervision</h3>
                <p>Direct communication, dedicated site coordinators, and timely handovers. We turn initial layouts into premium living and working spaces.</p>
            </div>
        </div>
    </section>

    <!-- Services Overview Section -->
    <section class="section section-bg-light" id="services-overview">
        <div class="container">
            <div class="section-header text-center fade-in-up">
                <span class="section-tagline">Core Capabilities</span>
                <h2 class="section-title">Our 10 Specialized Renovation Services</h2>
                <p class="section-desc">Direct from our official services portfolio. Residential and commercial renovation executed with quality craftsmanship and disciplined delivery.</p>
            </div>

            <!-- Overarching Renovation Highlights -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;" class="fade-in-up">
                <div style="background: linear-gradient(135deg, #07274d 0%, #0f4c81 100%); color: #fff; padding: 24px; border-radius: 12px; border-left: 4px solid var(--color-gold); display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--color-gold); font-weight: 700;">Complete Turnkey Service</span>
                        <h3 style="color: #fff; font-size: 20px; margin: 6px 0 10px;">Renovation Contractor in Singapore</h3>
                        <p style="font-size: 14px; color: #cbd5e1; line-height: 1.5; margin: 0 0 16px;">Residential & commercial renovations, space planning, full project supervision, and quality handovers.</p>
                    </div>
                    <a href="/renovation-contractor-singapore" class="btn btn-quote-header" style="align-self: flex-start; padding: 8px 18px; font-size: 13px;">Explore Renovation Contractor ➔</a>
                </div>
            </div>

            <div class="poster-services-grid">
                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/service-hacking.jpg" alt="Hacking & Demolition Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">1</span><span class="service-icon">🔨</span><h3>Hacking & Demolition Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>Hacking of walls, floors, tiles</li><li>Dismantling of fixtures</li><li>Disposal & site cleaning</li><li>Protection to surrounding areas</li></ul>
                        <a href="/hacking-demolition" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>

                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/service-flooring.jpg" alt="Tiling Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">2</span><span class="service-icon">🧱</span><h3>Tiles Flooring Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>Supply & installation of tiles</li><li>Floor & wall tiling works</li><li>Surface preparation & screeding</li><li>Grouting & finishing</li><li>Wide range of tile options</li></ul>
                        <a href="/tiling-works" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>

                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/flooring.png" alt="Vinyl Flooring Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">3</span><span class="service-icon">🪵</span><h3>Vinyl Flooring Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>Supply & installation of vinyl flooring</li><li>Surface preparation</li><li>Self-levelling works</li><li>Skirting installation</li><li>Neat and professional finish</li></ul>
                        <a href="/vinyl-flooring" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>

                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/waterproofing.png" alt="Waterproofing Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">4</span><span class="service-icon">💧</span><h3>Waterproofing Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>Bathroom, kitchen & balcony waterproofing</li><li>Wet area waterproofing</li><li>External wall / roof waterproofing</li><li>Leak rectification</li><li>Testing & quality assurance</li></ul>
                        <a href="/waterproofing" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>

                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/service-plumbing.jpg" alt="Plumbing Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">5</span><span class="service-icon">🚰</span><h3>Plumbing Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>Water supply & drainage works</li><li>Installation of sanitary fittings</li><li>Toilet, basin, sink installation</li><li>Pipe replacement & rectification</li><li>Leak testing & inspection</li></ul>
                        <a href="/plumbing" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>

                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/service-electrical.jpg" alt="Electrical Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">6</span><span class="service-icon">⚡</span><h3>Electrical Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>Wiring & rewiring</li><li>Lighting installation</li><li>Power points & switches</li><li>Electrical fittings installation</li><li>Testing & commissioning</li></ul>
                        <a href="/electrical" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>

                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/service-painting.jpg" alt="Painting Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">7</span><span class="service-icon">🖌️</span><h3>Painting Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>Interior & exterior painting</li><li>Wall & ceiling painting</li><li>Skim coat & surface preparation</li><li>Feature wall painting</li><li>Neat and long-lasting finish</li></ul>
                        <a href="/painting-works" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>

                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/painting.png" alt="Plastering Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">8</span><span class="service-icon">🩹</span><h3>Plastering & Skim Coating Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>Wall plastering works</li><li>Skim coating works</li><li>Crack rectification</li><li>Leveling & surface preparation</li><li>Smooth and clean finish</li></ul>
                        <a href="/painting-works" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>

                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/service-ceiling.jpg" alt="Ceiling & Partition Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">9</span><span class="service-icon">📐</span><h3>Ceiling & Partition Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>False ceiling installation</li><li>Gypsum board / metal stud partition works</li><li>Ceiling board works</li><li>Skimming & finishing</li><li>Custom design and installation</li></ul>
                        <a href="/ceiling-partition" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>

                <div class="poster-service-card fade-in-up">
                    <div class="poster-service-img-wrap"><img src="/images/service-interior.jpg" alt="Reinstatement Contractor Singapore" loading="lazy"></div>
                    <div class="poster-service-header"><span class="service-number-badge">10</span><span class="service-icon">🏢</span><h3>Reinstatement Works</h3></div>
                    <div class="poster-service-body">
                        <ul class="poster-service-list"><li>Reinstatement after renovation</li><li>Repair of walls, ceiling & floors</li><li>Reinstatement to original condition</li><li>Make good works</li><li>Handover in compliance with landlord / authority standards</li></ul>
                        <a href="/reinstatement" class="btn-card-inquire">View Scope & Quote ➔</a>
                    </div>
                </div>
            </div>

            <div class="text-center mb-1" style="margin-top: 50px;">
                <a href="/services" class="btn btn-primary" style="padding: 14px 32px;">Explore Detailed Services Catalog</a>
            </div>
        </div>
    </section>

    <!-- Featured Case Study Section (Authoritative Approval Gated) -->
    <?php
    $approvedProjects = get_all_projects(true);
    $featuredProject = !empty($approvedProjects) ? $approvedProjects[0] : null;
    ?>
    <?php if ($featuredProject): ?>
    <section class="section" style="background: #ffffff; padding: 70px 0;">
        <div class="container">
            <div class="section-header text-center fade-in-up">
                <span class="section-tagline">Authentic Workmanship</span>
                <h2 class="section-title">Featured Project Case Study</h2>
                <p class="section-desc">Photographic site documentation from our completed contracting projects across Singapore.</p>
            </div>
            <div style="max-width: 950px; margin: 0 auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.06); display: grid; grid-template-columns: 1fr 1fr; gap: 0;" class="fade-in-up">
                <div style="position: relative; min-height: 280px;">
                    <img src="<?= htmlspecialchars($featuredProject['featured_image']) ?>" alt="<?= htmlspecialchars($featuredProject['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy" width="490" height="325">
                </div>
                <div style="padding: 36px 32px; display: flex; flex-direction: column; justify-content: center;">
                    <span style="font-size: 12px; font-weight: 700; color: #0284c7; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;"><?= htmlspecialchars($featuredProject['service_name'] ?? 'Contracting') ?> Case Study</span>
                    <h3 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 12px;"><?= htmlspecialchars($featuredProject['title']) ?></h3>
                    <p style="font-size: 0.95rem; color: #475569; line-height: 1.6; margin-bottom: 20px;">
                        <?= htmlspecialchars($featuredProject['factual_summary']) ?>
                    </p>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <a href="/projects/<?= htmlspecialchars($featuredProject['slug']) ?>" class="btn btn-primary" style="padding: 10px 22px; font-size: 14px;">View Case Study ➔</a>
                        <a href="/projects" class="btn btn-secondary" style="padding: 10px 20px; font-size: 14px;">All Projects</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Official Company Profile & Poster Showcase -->
    <section class="poster-showcase-section">
        <div class="container">
            <div class="poster-showcase-grid">
                <div class="poster-showcase-info fade-in-up">
                    <span class="hero-trusted-badge">Official Company Profile</span>
                    <h2 style="font-size: 38px; color: #ffffff; margin-bottom: 20px;">Your Trusted Renovation Partner In Singapore</h2>
                    <p style="font-size: 16px; color: rgba(255, 255, 255, 0.88); margin-bottom: 24px; line-height: 1.6;">Tabeeb Contractor provides complete building solutions. From initial structural demolition and hacking to electrical distribution, sanitary piping, false ceilings, and interior masonry, we ensure every project delivers solid craftsmanship, disciplined handover, and direct contractor value.</p>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                        <a href="/images/tabeeb-services-poster-20261015.jpg" target="_blank" class="btn btn-quote-header" style="padding: 14px 28px; font-size: 15px;">🔍 View Official Poster (HD)</a>
                        <a href="/services" class="btn btn-outline-white" style="padding: 14px 28px; font-size: 15px;">Explore All Services</a>
                    </div>
                </div>
                <div class="poster-showcase-card fade-in-up">
                    <a href="/images/tabeeb-services-poster-20261015.jpg" target="_blank" title="Click to view full high-definition poster">
                        <img src="/images/tabeeb-services-poster-20261015.jpg" alt="Tabeeb Contractor Official Services Poster" loading="lazy" width="1254" height="1254">
                    </a>
                    <span style="display: block; font-size: 12px; color: var(--color-gold); margin-top: 10px; font-weight: 600;">Click image to enlarge official poster</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Banner -->
    <section class="cta-banner fade-in-up">
        <div class="container">
            <h2>Looking to Alter, Renovate or Reinstate Your Property?</h2>
            <p>Commercial reinstatement, home extensions, and alteration works require compliant planning. Speak to our team to plan your site inspection and requirements checklist today.</p>
            <a href="javascript:void(0)" class="btn btn-primary open-quote-modal-btn">Request Consultation</a>
        </div>
    </section>

    <?php require_once __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
