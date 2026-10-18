<?php
/**
 * Tabeeb Contractor Singapore - Services Catalog
 * URL: https://tabeebgroup.com/services
 */

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/business_tracker.php';
require_once __DIR__ . '/includes/tracking.php';

$company = get_company_info();
$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/services';
$pageTitle = 'Renovation & Contracting Services Singapore | ' . htmlspecialchars($company['brand_name']);
$pageDesc = "Explore Singapore's 10 specialized renovation and contracting services: residential & commercial renovation, hacking, tiling, vinyl, waterproofing, plumbing, electrical & reinstatement.";
$services = get_all_services(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="keywords" content="office reinstatement singapore, reinstatement contractor singapore, hacking contractor singapore, commercial renovation singapore, false ceiling contractor, tiling contractor singapore, waterproofing singapore">

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
    <meta property="og:image" content="<?= $baseUrl ?>/images/page-header-bg.jpg">
    <meta property="og:site_name" content="<?= htmlspecialchars($company['brand_name']) ?>">
    <meta property="og:locale" content="en_SG">

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "ItemList",
          "name": "Contracting & Engineering Services in Singapore",
          "itemListElement": [
            <?php
            $pos = 1;
            $itemsLd = [];
            foreach ($services as $srv) {
                $itemsLd[] = json_encode([
                    '@type' => 'Service',
                    'position' => $pos++,
                    'name' => $srv['name'],
                    'url' => $baseUrl . '/' . $srv['slug'],
                    'description' => $srv['overview'] ?? '',
                    'provider' => [
                        '@type' => 'GeneralContractor',
                        'name' => $company['brand_name'],
                        'telephone' => $company['phone'],
                        'url' => $baseUrl . '/'
                    ]
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            echo implode(",\n            ", $itemsLd);
            ?>
          ]
        },
        {
          "@type": "BreadcrumbList",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "<?= $baseUrl ?>/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "Services",
              "item": "<?= $baseUrl ?>/services"
            }
          ]
        }
      ]
    }
    </script>

    <link rel="stylesheet" href="/style.css?v=3.7">
    <?php render_gtm_head(); ?>
</head>
<body>
    <?php render_gtm_body(); ?>
    <?php $activeNav = 'services'; require_once __DIR__ . '/includes/public_header.php'; ?>

    <!-- Subpage Hero Header -->
    <section class="subpage-hero">
        <div class="container">
            <h1 class="fade-in-up">Commercial Reinstatement, Hacking & Renovation Services Singapore</h1>
            <div class="breadcrumbs fade-in-up">
                <a href="/">Home</a> &gt; <span>Services</span>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="section" id="all-services">
        <div class="container">
            <div class="section-header text-center fade-in-up">
                <span class="section-tagline">Specialized Capabilities</span>
                <h2 class="section-title">Contracting & Trade Services</h2>
                <p class="section-desc">Direct trade solutions for residential homeowners, commercial tenants, and facility managers across Singapore.</p>
            </div>

            <div class="services-catalog-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr)); gap: 30px; margin-top: 40px;">
                <?php foreach ($services as $srv): ?>
                    <article class="service-card fade-in-up" style="background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; display: flex; flex-direction: column;">
                        <div style="height: 200px; overflow: hidden; position: relative;">
                            <img src="<?= htmlspecialchars($srv['image']) ?>" alt="<?= htmlspecialchars($srv['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                            <span style="position: absolute; top: 14px; left: 14px; background: rgba(15, 23, 42, 0.85); color: #fff; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-transform: uppercase;">
                                <?= htmlspecialchars($srv['badge'] ?? 'Trade Specialist') ?>
                            </span>
                        </div>
                        <div style="padding: 24px; display: flex; flex-direction: column; flex-grow: 1;">
                            <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 10px; color: #0f172a;">
                                <a href="/<?= htmlspecialchars($srv['slug']) ?>" style="color: inherit; text-decoration: none;">
                                    <?= htmlspecialchars($srv['name']) ?>
                                </a>
                            </h3>
                            <p style="font-size: 0.95rem; color: #64748b; line-height: 1.5; margin-bottom: 20px; flex-grow: 1;">
                                <?= htmlspecialchars(substr(strip_tags($srv['overview']), 0, 150)) ?>...
                            </p>
                            <div style="display: flex; gap: 10px; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                                <a href="/<?= htmlspecialchars($srv['slug']) ?>" class="btn btn-primary" style="padding: 8px 18px; font-size: 13px;">View Scope ➔</a>
                                <a href="/contact?service=<?= htmlspecialchars($srv['slug']) ?>" class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">Enquire</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="cta-banner fade-in-up">
        <div class="container">
            <h2>Need a Tailored Quotation for Your Commercial or Residential Project?</h2>
            <p>Speak directly with Tabeeb Contractor. We review your layout plans, site conditions, and project timeline without delay.</p>
            <a href="javascript:void(0)" class="btn btn-primary open-quote-modal-btn">Request Quotation</a>
        </div>
    </section>

    <?php require_once __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
