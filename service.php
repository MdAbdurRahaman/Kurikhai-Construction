<?php
require_once __DIR__ . '/includes/data.php';

$slug = $_GET['slug'] ?? $_GET['id'] ?? '';
$service = get_service_by_slug($slug);

// If service not found, default to first service or redirect to services.html
if (!$service) {
    $allServices = get_all_services();
    if (!empty($allServices)) {
        $service = $allServices[0];
    } else {
        header('Location: services.html');
        exit;
    }
}

$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/' . htmlspecialchars($service['slug']) . '/';
$imageUrl = (strpos($service['image'], 'http') === 0) ? $service['image'] : ($baseUrl . '/' . ltrim($service['image'], '/'));
$ogTitle = $service['og_title'] ?? ($service['name'] . ' | Tabeeb Contractor Singapore');
$ogDesc = $service['og_description'] ?? $service['overview'];
$whatsappShareText = urlencode($service['og_title'] . "\n" . $canonicalUrl);
$facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($canonicalUrl);
$linkedinShareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($canonicalUrl);
$twitterShareUrl = 'https://twitter.com/intent/tweet?text=' . urlencode($service['og_title']) . '&url=' . urlencode($canonicalUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($ogTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($ogDesc) ?>">
    
    <!-- Geo Targeting Singapore -->
    <meta name="geo.region" content="SG">
    <meta name="geo.placename" content="Singapore">
    <meta name="geo.position" content="1.3364;103.9066">
    <meta name="ICBM" content="1.3364, 103.9066">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $canonicalUrl ?>">

    <!-- Open Graph / Facebook & WhatsApp Social Previews -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta property="og:image" content="<?= $imageUrl ?>">
    <meta property="og:image:secure_url" content="<?= $imageUrl ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="Tabeeb Contractor Singapore">
    <meta property="og:locale" content="en_SG">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= $canonicalUrl ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta name="twitter:image" content="<?= $imageUrl ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/style.css?v=3.4">

    <!-- Schema.org Service JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Service",
      "name": <?= json_encode($service['name']) ?>,
      "description": <?= json_encode($service['overview']) ?>,
      "provider": {
        "@type": "GeneralContractor",
        "name": "Tabeeb Contractor Pte Ltd",
        "telephone": "+65 8648 4883",
        "url": "https://www.tabeebgroup.com/",
        "address": {
          "@type": "PostalAddress",
          "streetAddress": "61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park",
          "addressLocality": "Singapore",
          "postalCode": "417943",
          "addressCountry": "SG"
        }
      },
      "areaServed": "Singapore",
      "serviceType": <?= json_encode($service['category_name']) ?>
    }
    </script>

    <style>
        .service-single-hero {
            padding: 130px 0 60px;
            background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
            color: #fff;
            position: relative;
        }
        .service-single-hero .badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(0, 180, 216, 0.18);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.35);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .service-single-hero h1 {
            color: #fff;
            font-size: clamp(2rem, 3.5vw, 2.75rem);
            line-height: 1.2;
            margin-bottom: 16px;
            max-width: 900px;
        }
        .service-single-hero p.lead {
            font-size: 1.15rem;
            color: #cbd5e1;
            max-width: 800px;
            line-height: 1.7;
            margin-bottom: 25px;
        }
        .service-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        /* Share Bar Sticky & Floating Component */
        .social-share-strip {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 24px;
            margin: -35px auto 40px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
            z-index: 10;
        }
        .share-prompt {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: var(--color-primary);
            font-size: 15px;
        }
        .share-buttons-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .share-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }
        .share-btn-wa {
            background: #25d366;
            color: #ffffff;
        }
        .share-btn-wa:hover {
            background: #1ebc59;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.35);
            color: #ffffff;
        }
        .share-btn-fb {
            background: #1877f2;
            color: #ffffff;
        }
        .share-btn-fb:hover {
            background: #0d65d9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(24, 119, 242, 0.35);
            color: #ffffff;
        }
        .share-btn-li {
            background: #0077b5;
            color: #ffffff;
        }
        .share-btn-li:hover {
            background: #006396;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 119, 181, 0.35);
            color: #ffffff;
        }
        .share-btn-copy {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .share-btn-copy:hover {
            background: #e2e8f0;
            color: var(--color-primary);
        }
        .share-btn-native {
            background: var(--color-primary);
            color: #fff;
        }
        .share-btn-native:hover {
            background: var(--color-primary-light);
            color: #fff;
        }

        /* Scope & Features Grid */
        .scope-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 30px;
        }
        .scope-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
            font-size: 15px;
            color: #334155;
        }
        .scope-check {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #10b981;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .benefit-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin: 25px 0 40px;
        }
        .benefit-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .benefit-box:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--color-accent);
        }
        .benefit-box h4 {
            font-size: 16px;
            margin-bottom: 8px;
            color: var(--color-primary);
        }
        .benefit-box p {
            font-size: 14px;
            color: var(--color-text-muted);
            line-height: 1.5;
            margin: 0;
        }

        /* 4 Step Process */
        .step-timeline {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .step-tile {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px 20px;
            position: relative;
        }
        .step-num {
            font-size: 32px;
            font-weight: 800;
            font-family: var(--font-heading);
            color: var(--color-accent);
            opacity: 0.6;
            margin-bottom: 10px;
            line-height: 1;
        }
        .step-tile h4 {
            font-size: 16px;
            margin-bottom: 8px;
            color: var(--color-primary);
        }
        .step-tile p {
            font-size: 13px;
            color: var(--color-text-muted);
            line-height: 1.5;
            margin: 0;
        }

        /* Sidebar Inquire Box */
        .sidebar-inquire-card {
            background: linear-gradient(145deg, #07274d, #0d386b);
            color: #fff;
            border-radius: 20px;
            padding: 32px 28px;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 100px;
        }
        .sidebar-inquire-card h3 {
            color: #fff;
            margin-bottom: 10px;
            font-size: 22px;
        }
        .sidebar-inquire-card p {
            color: #cbd5e1;
            font-size: 14px;
            margin-bottom: 22px;
            line-height: 1.5;
        }

        /* Toast copied alert */
        .toast-copied {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #0f172a;
            color: #fff;
            padding: 14px 22px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 99999;
        }
        .toast-copied.show {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>

    <!-- Header Navigation with Topbar -->
    <header class="header scrolled" id="header">
        <div class="topbar">
            <div class="container">
                <div class="topbar-left">
                    <span class="topbar-item"><span class="topbar-icon">📍</span> 61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park, Singapore 417943</span>
                    <span class="topbar-item"><span class="topbar-icon">🕒</span> Mon - Sat: 9:00 AM - 6:00 PM</span>
                </div>
                <div class="topbar-right">
                    <span class="topbar-item"><span class="topbar-icon">📞</span> <a href="tel:+6586484883">+65 8648 4883</a></span>
                    <a href="https://wa.me/6586484883?text=Hello%20Tabeeb%20Contractor,%20I%20am%20inquiring%20about%20<?= urlencode($service['name']) ?>." target="_blank" rel="noopener noreferrer" class="topbar-whatsapp">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg> WhatsApp Us
                    </a>
                </div>
            </div>
        </div>
        <div class="header-main">
            <div class="container">
                <div class="header-wrapper">
                    <a href="/" class="logo" id="header-logo" aria-label="Tabeeb Contractor Pte Ltd">
                        <img src="/images/logo-horizontal.png?v=3.3" alt="Tabeeb Contractor Pte Ltd" class="header-logo-img">
                    </a>
                    <nav>
                        <ul class="nav-list" id="nav-list">
                            <li><a href="/" class="nav-link">Home</a></li>
                            <li><a href="/services" class="nav-link active">Services</a></li>
                            <li><a href="/blog" class="nav-link">Blog</a></li>
                            <li><a href="/about" class="nav-link">About Us</a></li>
                            <li><a href="/contact" class="nav-link">Contact</a></li>
                            <li><a href="javascript:void(0)" class="btn btn-quote-header open-quote-modal-btn">Get a Quote</a></li>
                        </ul>
                    </nav>
                    <button class="burger" id="burger-menu" aria-label="Toggle Menu">
                        <div class="line1"></div>
                        <div class="line2"></div>
                        <div class="line3"></div>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Subpage Hero Header -->
    <section class="service-single-hero">
        <div class="container">
            <div class="breadcrumbs" style="color: #94a3b8; margin-bottom: 12px; font-size: 14px;">
                <a href="/" style="color: #38bdf8;">Home</a> &gt; 
                <a href="/services" style="color: #38bdf8;">Services</a> &gt; 
                <span><?= htmlspecialchars($service['short_title'] ?? $service['name']) ?></span>
            </div>
            <div class="badge-tag">
                <span><?= $service['icon'] ?></span> <?= htmlspecialchars($service['badge']) ?>
            </div>
            <h1><?= htmlspecialchars($service['name']) ?></h1>
            <p class="lead"><?= htmlspecialchars($service['hero_tagline'] ?? $service['overview']) ?></p>
            <div class="service-hero-actions">
                <a href="/contact?service=<?= urlencode($service['name']) ?>" class="btn btn-primary" style="background: var(--color-gold); border-color: var(--color-gold); color: #fff;">Get Free Onsite Quote</a>
                <a href="https://wa.me/6586484883?text=<?= $whatsappShareText ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #25d366; color: #fff; display: inline-flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    WhatsApp Specialist
                </a>
            </div>
        </div>
    </section>

    <!-- Social Media Sharing Bar (The requested social sharing functionality!) -->
    <div class="container">
        <div class="social-share-strip">
            <div class="share-prompt">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                <span>Share this Service:</span>
            </div>
            <div class="share-buttons-group">
                <a href="https://wa.me/?text=<?= $whatsappShareText ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-wa" title="Share to WhatsApp">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg> WhatsApp
                </a>
                <a href="<?= $facebookShareUrl ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-fb" title="Share to Facebook">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Facebook
                </a>
                <a href="<?= $linkedinShareUrl ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-li" title="Share to LinkedIn">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg> LinkedIn
                </a>
                <button type="button" class="share-btn share-btn-copy" id="copyShareLinkBtn" data-url="<?= $canonicalUrl ?>">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Copy Link
                </button>
                <button type="button" class="share-btn share-btn-native" id="nativeShareBtn" style="display:none;" data-title="<?= htmlspecialchars($ogTitle) ?>" data-text="<?= htmlspecialchars($ogDesc) ?>" data-url="<?= $canonicalUrl ?>">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg> Share
                </button>
            </div>
        </div>
    </div>

    <!-- Main Service Content Section -->
    <section class="section" style="padding-top: 10px;">
        <div class="container">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px; align-items: start;" class="service-layout-grid">
                
                <!-- Left Main Content Column -->
                <div class="service-main-col">
                    
                    <!-- Featured Showcase Image -->
                    <div style="border-radius: 18px; overflow: hidden; box-shadow: var(--shadow-md); margin-bottom: 35px; border: 1px solid #e2e8f0;">
                        <img src="/<?= ltrim($service['image'], '/') ?>" alt="<?= htmlspecialchars($service['name']) ?>" style="width: 100%; max-height: 480px; object-fit: cover;">
                    </div>

                    <!-- Overview -->
                    <h2 style="font-size: 26px; margin-bottom: 16px;">Service Overview</h2>
                    <p style="font-size: 16px; color: #334155; line-height: 1.8; margin-bottom: 30px;">
                        <?= nl2br(htmlspecialchars($service['overview'])) ?>
                    </p>

                    <!-- Scope of Work Checklist -->
                    <div class="scope-card">
                        <h3 style="font-size: 20px; margin-bottom: 20px; color: var(--color-primary); display: flex; align-items: center; gap: 10px;">
                            <span>📋</span> Scope of Work & Deliverables
                        </h3>
                        <?php if (!empty($service['scope_of_work'])): ?>
                            <?php foreach ($service['scope_of_work'] as $item): ?>
                                <div class="scope-item">
                                    <span class="scope-check">✓</span>
                                    <span><?= htmlspecialchars($item) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Why Choose Tabeeb for this trade -->
                    <h2 style="font-size: 24px; margin: 40px 0 10px;">Why Engage Tabeeb Contractor</h2>
                    <p style="color: var(--color-text-muted); font-size: 15px;">We are a direct builder with experienced, in-house craftsmen—delivering cost savings, rapid timelines, and strict compliance with Singapore statutory regulations.</p>

                    <div class="benefit-card-grid">
                        <?php if (!empty($service['key_benefits'])): ?>
                            <?php foreach ($service['key_benefits'] as $benefit): ?>
                                <div class="benefit-box">
                                    <h4><?= htmlspecialchars($benefit['title']) ?></h4>
                                    <p><?= htmlspecialchars($benefit['desc']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- 4-Step Working Process -->
                    <h2 style="font-size: 24px; margin: 40px 0 10px;">Execution & Handover Process</h2>
                    <p style="color: var(--color-text-muted); font-size: 15px;">Every project follows a transparent, monitored timeline from initial blueprint consultation to final handover.</p>
                    
                    <div class="step-timeline">
                        <?php if (!empty($service['process'])): ?>
                            <?php foreach ($service['process'] as $step): ?>
                                <div class="step-tile">
                                    <div class="step-num"><?= htmlspecialchars($step['step']) ?></div>
                                    <h4><?= htmlspecialchars($step['title']) ?></h4>
                                    <p><?= htmlspecialchars($step['desc']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- FAQ Section -->
                    <?php if (!empty($service['faq'])): ?>
                        <h2 style="font-size: 24px; margin: 45px 0 20px;">Frequently Asked Questions</h2>
                        <div class="faq-accordion" style="margin-bottom: 40px;">
                            <?php foreach ($service['faq'] as $index => $faq): ?>
                                <div class="faq-item <?= $index === 0 ? 'active' : '' ?>">
                                    <button class="faq-question" type="button">
                                        <span><?= htmlspecialchars($faq['q']) ?></span>
                                        <span class="faq-icon">+</span>
                                    </button>
                                    <div class="faq-answer">
                                        <p><?= htmlspecialchars($faq['a']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Bottom Share Bar -->
                    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-top: 30px;">
                        <div>
                            <strong style="color: var(--color-primary); font-size: 15px;">Know someone renovating or reinstating?</strong>
                            <p style="margin: 0; font-size: 13px; color: var(--color-text-muted);">Share this direct contractor link with them on WhatsApp or Facebook.</p>
                        </div>
                        <a href="https://wa.me/?text=<?= $whatsappShareText ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-wa">
                            Share on WhatsApp ➔
                        </a>
                    </div>

                </div>

                <!-- Right Sidebar Column -->
                <div class="service-sidebar-col">
                    <div class="sidebar-inquire-card">
                        <span style="background: rgba(194, 150, 63, 0.25); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.4); padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase;">Direct Contractor</span>
                        <h3 style="margin-top: 14px;">Inquire About This Service</h3>
                        <p>Speak directly with our project manager. We conduct site visits across all areas of Singapore.</p>
                        
                        <div style="margin-bottom: 24px; background: rgba(255,255,255,0.06); padding: 16px; border-radius: 12px;">
                            <div style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Service Name</div>
                            <div style="font-size: 15px; font-weight: 600; color: #38bdf8; margin-top: 4px;"><?= htmlspecialchars($service['name']) ?></div>
                        </div>

                        <a href="https://wa.me/6586484883?text=<?= $whatsappShareText ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #25d366; color: #fff; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 12px; padding: 14px 20px; font-weight: 600; border-radius: 10px;">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            Instant WhatsApp Quote
                        </a>

                        <a href="tel:+6586484883" class="btn" style="background: rgba(255,255,255,0.12); color: #fff; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid rgba(255,255,255,0.2); padding: 12px 20px; font-weight: 500; border-radius: 10px; margin-bottom: 20px;">
                            📞 Call +65 8648 4883
                        </a>

                        <div style="font-size: 13px; color: #94a3b8; line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 16px;">
                            ✓ Free on-site consultation<br>
                            ✓ Strict safety & workmanship standards<br>
                            ✓ Official written quotation
                        </div>
                    </div>

                    <!-- Other Services Navigation -->
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; margin-top: 25px;">
                        <h4 style="font-size: 17px; margin-bottom: 14px; color: var(--color-primary);">All 10 Core Services</h4>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <?php 
                            $otherServices = get_all_services();
                            foreach ($otherServices as $other):
                                $isActive = ($other['slug'] === $service['slug']);
                            ?>
                                <li style="margin-bottom: 10px;">
                                    <a href="/<?= htmlspecialchars($other['slug']) ?>/" style="display: flex; align-items: center; justify-content: space-between; font-size: 14px; padding: 8px 12px; border-radius: 8px; text-decoration: none; color: <?= $isActive ? '#07274d' : '#475569' ?>; background: <?= $isActive ? '#e0f2fe' : 'transparent' ?>; font-weight: <?= $isActive ? '600' : '400' ?>;">
                                        <span><?= $other['icon'] ?> <?= htmlspecialchars($other['short_title'] ?? $other['name']) ?></span>
                                        <span style="font-size: 12px; color: #94a3b8;">➔</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Bottom CTA Banner -->
    <section class="cta-banner" style="background-color: var(--color-primary-dark); margin-top: 60px;">
        <div class="container">
            <h2>Ready to Schedule Your Site Inspection?</h2>
            <p>Our licensed technicians and project coordinators are ready to evaluate your floor plan and provide transparent direct contractor quotations.</p>
            <a href="https://wa.me/6586484883?text=<?= $whatsappShareText ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="background: var(--color-gold); border-color: var(--color-gold); margin-top: 15px;">Chat on WhatsApp with Engineer</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col footer-brand-col">
                    <a href="/" class="footer-logo-link" aria-label="Tabeeb Contractor Pte Ltd">
                        <img src="/images/logo-white.png?v=3.3" alt="Tabeeb Contractor Pte Ltd" class="footer-logo-img" width="220">
                    </a>
                    <p>Tabeeb Contractor Pte Ltd is a premier direct contracting firm specializing in commercial office reinstatement, HDB wall hacking, architectural tiling, waterproofing, and structural alterations in Singapore.</p>
                </div>
                <div class="footer-col">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="/">Home</a></li>
                        <li><a href="/services">Our Services</a></li>
                        <li><a href="/blog">Renovation Blog</a></li>
                        <li><a href="/about">About Company</a></li>
                        <li><a href="/contact">Contact Us</a></li>
                        <li><a href="/admin">Admin Portal</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Direct Services</h3>
                    <ul>
                        <li><a href="/renovation-contractor-singapore/">Renovation Contractor</a></li>
                        <li><a href="/hdb-renovation/">HDB Renovation</a></li>
                        <li><a href="/tiling-works/">Tiles Flooring Works</a></li>
                        <li><a href="/vinyl-flooring/">Vinyl Flooring Works</a></li>
                        <li><a href="/hacking-demolition/">Hacking & Demolition</a></li>
                        <li><a href="/painting-works/">Painting & Plastering</a></li>
                        <li><a href="/waterproofing/">Waterproofing Works</a></li>
                        <li><a href="/plumbing/">Plumbing Works</a></li>
                        <li><a href="/electrical/">Electrical Works</a></li>
                        <li><a href="/ceiling-partition/">Ceiling & Partition</a></li>
                        <li><a href="/reinstatement/">Reinstatement Works</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Headquarters</h3>
                    <p style="color: #cbd5e1; font-size: 14px; margin-bottom: 12px;">
                        📍 61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park, Singapore 417943
                    </p>
                    <p style="color: #cbd5e1; font-size: 14px; margin-bottom: 8px;">
                        📞 Phone: <a href="tel:+6586484883" style="color: #38bdf8;">+65 8648 4883</a>
                    </p>
                    <p style="color: #cbd5e1; font-size: 14px;">
                        🕒 Mon - Sat: 9:00 AM - 6:00 PM
                    </p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Tabeeb Contractor Pte Ltd. All Rights Reserved. Reg No: 202324681W.</p>
            </div>
        </div>
    </footer>

    <!-- Toast Copied Notification -->
    <div class="toast-copied" id="copyToast">
        <svg width="20" height="20" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <span>Link copied! Ready to share on WhatsApp or Facebook.</span>
    </div>

    <!-- Scripts -->
    <script src="/app.js?v=3.4"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Copy Link Functionality
            const copyBtn = document.getElementById('copyShareLinkBtn');
            const copyToast = document.getElementById('copyToast');

            if (copyBtn && copyToast) {
                copyBtn.addEventListener('click', async () => {
                    const url = copyBtn.getAttribute('data-url');
                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(url);
                        } else {
                            const textArea = document.createElement('textarea');
                            textArea.value = url;
                            textArea.style.position = 'fixed';
                            textArea.style.left = '-999999px';
                            document.body.appendChild(textArea);
                            textArea.focus();
                            textArea.select();
                            document.execCommand('copy');
                            textArea.remove();
                        }
                        copyToast.classList.add('show');
                        setTimeout(() => copyToast.classList.remove('show'), 3500);
                    } catch (err) {
                        prompt('Copy this link to share:', url);
                    }
                });
            }

            // Native Web Share API (For Mobile devices: WhatsApp, Instagram Stories, Telegram)
            const nativeBtn = document.getElementById('nativeShareBtn');
            if (nativeBtn && navigator.share) {
                nativeBtn.style.display = 'inline-flex';
                nativeBtn.addEventListener('click', async () => {
                    try {
                        await navigator.share({
                            title: nativeBtn.getAttribute('data-title'),
                            text: nativeBtn.getAttribute('data-text'),
                            url: nativeBtn.getAttribute('data-url')
                        });
                    } catch (err) {
                        // User cancelled share
                    }
                });
            }
        });
    </script>
</body>
</html>
