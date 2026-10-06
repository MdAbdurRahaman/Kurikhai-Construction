<?php
require_once __DIR__ . '/includes/data.php';

$slug = $_GET['slug'] ?? '';
$post = get_post_by_slug($slug);

if (!$post) {
    header('Location: /blog');
    exit;
}

$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/blog/' . htmlspecialchars($post['slug']);
$imageUrl = (strpos($post['image'], 'http') === 0) ? $post['image'] : ($baseUrl . '/' . ltrim($post['image'], '/'));
$ogTitle = $post['title'] . ' | Tabeeb Contractor Singapore';
$ogDesc = !empty($post['excerpt']) ? $post['excerpt'] : substr(strip_tags($post['content']), 0, 160);

$whatsappShareText = urlencode($post['title'] . "\n" . $canonicalUrl);
$facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($canonicalUrl);
$linkedinShareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($canonicalUrl);
$twitterShareUrl = 'https://twitter.com/intent/tweet?text=' . urlencode($post['title']) . '&url=' . urlencode($canonicalUrl);

// Related posts
$allPosts = get_all_posts(true);
$relatedPosts = array_filter($allPosts, function($p) use ($post) {
    return $p['id'] !== $post['id'];
});
$relatedPosts = array_slice($relatedPosts, 0, 3);
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

    <!-- Open Graph / Facebook & WhatsApp Social Cards -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta property="og:image" content="<?= $imageUrl ?>">
    <meta property="og:image:secure_url" content="<?= $imageUrl ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="Tabeeb Contractor Singapore">
    <meta property="og:locale" content="en_SG">
    <meta property="article:published_time" content="<?= date('c', strtotime($post['created_at'])) ?>">
    <meta property="article:author" content="<?= htmlspecialchars($post['author'] ?? 'Tabeeb Contractor') ?>">
    <meta property="article:section" content="<?= htmlspecialchars($post['category'] ?? 'General') ?>">

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

    <!-- Schema.org BlogPosting JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BlogPosting",
      "headline": <?= json_encode($post['title']) ?>,
      "description": <?= json_encode($ogDesc) ?>,
      "image": <?= json_encode($imageUrl) ?>,
      "datePublished": <?= json_encode(date('c', strtotime($post['created_at']))) ?>,
      "dateModified": <?= json_encode(date('c', strtotime($post['updated_at'] ?? $post['created_at']))) ?>,
      "author": {
        "@type": "Organization",
        "name": <?= json_encode($post['author'] ?? 'Tabeeb Contractor Pte Ltd') ?>,
        "url": "https://www.tabeebgroup.com/"
      },
      "publisher": {
        "@type": "Organization",
        "name": "Tabeeb Contractor Pte Ltd",
        "logo": {
          "@type": "ImageObject",
          "url": "https://www.tabeebgroup.com/images/logo-horizontal.png"
        }
      },
      "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": <?= json_encode($canonicalUrl) ?>
      }
    }
    </script>

    <style>
        .article-hero {
            padding: 130px 0 45px;
            background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
            color: #fff;
        }
        .article-hero .cat-badge {
            display: inline-block;
            background: rgba(0, 180, 216, 0.2);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.4);
            padding: 5px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .article-hero h1 {
            color: #fff;
            font-size: clamp(2rem, 3.5vw, 2.75rem);
            line-height: 1.25;
            margin-bottom: 18px;
            max-width: 900px;
        }
        .article-meta-bar {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 14px;
            color: #cbd5e1;
            flex-wrap: wrap;
        }

        /* Social Share Strip */
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

        /* Article Body & Typography */
        .article-content {
            font-size: 17px;
            line-height: 1.85;
            color: #334155;
        }
        .article-content h2 {
            font-size: 26px;
            margin: 35px 0 16px;
            color: var(--color-primary);
        }
        .article-content h3 {
            font-size: 21px;
            margin: 28px 0 12px;
            color: var(--color-primary-light);
        }
        .article-content h4 {
            font-size: 18px;
            margin: 22px 0 10px;
            color: var(--color-primary);
        }
        .article-content p {
            margin-bottom: 22px;
        }
        .article-content ul, .article-content ol {
            margin: 0 0 24px 24px;
        }
        .article-content li {
            margin-bottom: 8px;
        }
        .article-content blockquote {
            border-left: 4px solid var(--color-gold);
            padding: 16px 20px;
            background: #fdfaf2;
            margin: 25px 0;
            border-radius: 0 12px 12px 0;
            font-style: italic;
            color: #1e293b;
        }

        /* Callout Boxes */
        .blog-callout {
            border-radius: 14px;
            padding: 20px 24px;
            margin: 28px 0;
            border: 1px solid;
        }
        .blog-callout.tip {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }
        .blog-callout.warning {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }
        .blog-callout h4 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 17px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .blog-callout.tip h4 {
            color: #15803d;
        }
        .blog-callout.warning h4 {
            color: #b91c1c;
        }
        .blog-callout p:last-child {
            margin-bottom: 0;
        }

        /* Sidebar Quote Box */
        .sidebar-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 30px;
        }

        /* Toast copied */
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
                    <a href="https://wa.me/6586484883?text=Hello%20Tabeeb%20Contractor,%20I%20am%20inquiring%20about%20your%20services." target="_blank" rel="noopener noreferrer" class="topbar-whatsapp">
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
                            <li><a href="/services" class="nav-link">Services</a></li>
                            <li><a href="/blog" class="nav-link active">Blog</a></li>
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

    <!-- Article Hero -->
    <article>
        <section class="article-hero">
            <div class="container">
                <div class="breadcrumbs" style="color: #94a3b8; margin-bottom: 12px; font-size: 14px;">
                    <a href="/" style="color: #38bdf8;">Home</a> &gt; 
                    <a href="/blog" style="color: #38bdf8;">Blog</a> &gt; 
                    <span><?= htmlspecialchars($post['title']) ?></span>
                </div>
                <span class="cat-badge"><?= htmlspecialchars($post['category']) ?></span>
                <h1><?= htmlspecialchars($post['title']) ?></h1>
                <div class="article-meta-bar">
                    <span>📅 Published: <?= format_date($post['created_at']) ?></span>
                    <span>•</span>
                    <span>⏱️ <?= htmlspecialchars($post['read_time'] ?? '5 min read') ?></span>
                    <span>•</span>
                    <span>✍️ By <?= htmlspecialchars($post['author'] ?? 'Tabeeb Technical Team') ?></span>
                </div>
            </div>
        </section>

        <!-- Social Share Strip -->
        <div class="container">
            <div class="social-share-strip">
                <div class="share-prompt">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    <span>Share this Guide:</span>
                </div>
                <div class="share-buttons-group">
                    <a href="https://wa.me/?text=<?= $whatsappShareText ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-wa" title="Share on WhatsApp">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg> WhatsApp
                    </a>
                    <a href="<?= $facebookShareUrl ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-fb" title="Share on Facebook">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> Facebook
                    </a>
                    <a href="<?= $linkedinShareUrl ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn-li" title="Share on LinkedIn">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg> LinkedIn
                    </a>
                    <button type="button" class="share-btn share-btn-copy" id="copyArticleLinkBtn" data-url="<?= $canonicalUrl ?>">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Copy Link
                    </button>
                    <button type="button" class="share-btn share-btn-native" id="nativeShareArticleBtn" style="display:none;" data-title="<?= htmlspecialchars($ogTitle) ?>" data-text="<?= htmlspecialchars($ogDesc) ?>" data-url="<?= $canonicalUrl ?>">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg> Share
                    </button>
                </div>
            </div>
        </div>

        <!-- Article Body Grid -->
        <section class="section" style="padding-top: 10px;">
            <div class="container">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 45px; align-items: start;" class="article-layout-grid">
                    
                    <!-- Main Article Column -->
                    <div class="article-main-col">
                        
                        <!-- Featured Image -->
                        <?php if (!empty($post['image'])): ?>
                            <div style="border-radius: 18px; overflow: hidden; margin-bottom: 35px; box-shadow: var(--shadow-md);">
                                <img src="/<?= ltrim($post['image'], '/') ?>" alt="<?= htmlspecialchars($post['title']) ?>" style="width: 100%; max-height: 440px; object-fit: cover;">
                            </div>
                        <?php endif; ?>

                        <!-- Content Body -->
                        <div class="article-content">
                            <?= $post['content'] ?>
                        </div>

                        <!-- Post Tags -->
                        <?php if (!empty($post['tags'])): ?>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin: 40px 0 20px; align-items: center;">
                                <strong style="font-size: 14px; color: #64748b;">Tags:</strong>
                                <?php foreach ($post['tags'] as $tag): ?>
                                    <span style="background: #f1f5f9; color: #475569; padding: 4px 12px; border-radius: 9999px; font-size: 13px; border: 1px solid #e2e8f0;"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Author Card -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; display: flex; gap: 20px; align-items: center; margin: 35px 0;">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: #07274d; color: #38bdf8; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; flex-shrink: 0;">
                                🏗️
                            </div>
                            <div>
                                <h4 style="margin: 0 0 6px; font-size: 17px; color: var(--color-primary);"><?= htmlspecialchars($post['author'] ?? 'Tabeeb Contractor Pte Ltd') ?></h4>
                                <p style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.5;">Direct commercial & residential contractor in Singapore. Licensed specialists in office reinstatement, HDB wall hacking, waterproofing, tiling, and turnkey construction.</p>
                            </div>
                        </div>

                    </div>

                    <!-- Sidebar Column -->
                    <div class="article-sidebar-col">
                        
                        <!-- Free Quote CTA Box -->
                        <div class="sidebar-box" style="background: linear-gradient(145deg, #07274d, #0d386b); color: #fff;">
                            <h3 style="color: #fff; font-size: 20px; margin-bottom: 10px;">Planning a Project?</h3>
                            <p style="color: #cbd5e1; font-size: 14px; line-height: 1.5; margin-bottom: 20px;">
                                Get direct contractor rates. Skip interior design markups and speak directly with our site supervisor.
                            </p>
                            <a href="https://wa.me/6586484883?text=<?= $whatsappShareText ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #25d366; color: #fff; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; border-radius: 10px; margin-bottom: 12px; padding: 12px 18px;">
                                WhatsApp Site Engineer
                            </a>
                            <a href="/contact" class="btn" style="background: rgba(255,255,255,0.15); color: #fff; width: 100%; display: block; text-align: center; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); font-size: 14px; padding: 10px 18px;">
                                Request Written Quote ➔
                            </a>
                        </div>

                        <!-- Related Articles -->
                        <?php if (!empty($relatedPosts)): ?>
                            <div class="sidebar-box">
                                <h4 style="font-size: 18px; margin-bottom: 16px; color: var(--color-primary);">Related Guides</h4>
                                <div style="display: flex; flex-direction: column; gap: 16px;">
                                    <?php foreach ($relatedPosts as $rel): 
                                        $relUrl = '/blog/' . htmlspecialchars($rel['slug']);
                                    ?>
                                        <div>
                                            <span style="font-size: 12px; color: #0284c7; font-weight: 600;"><?= htmlspecialchars($rel['category']) ?></span>
                                            <h5 style="margin: 4px 0; font-size: 14px; line-height: 1.4;">
                                                <a href="<?= $relUrl ?>" style="color: var(--color-primary);"><?= htmlspecialchars($rel['title']) ?></a>
                                            </h5>
                                            <span style="font-size: 12px; color: #94a3b8;"><?= format_date($rel['created_at']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Popular Services List -->
                        <div class="sidebar-box">
                            <h4 style="font-size: 18px; margin-bottom: 14px; color: var(--color-primary);">Direct Contractor Services</h4>
                            <ul style="list-style: none; padding: 0; margin: 0; font-size: 14px;">
                                <li style="margin-bottom: 8px;"><a href="/renovation-contractor-singapore/" style="color: #475569;">🏠 Renovation Contractor Singapore</a></li>
                                <li style="margin-bottom: 8px;"><a href="/hdb-renovation/" style="color: #475569;">🏢 HDB Renovation Works</a></li>
                                <li style="margin-bottom: 8px;"><a href="/hacking-demolition/" style="color: #475569;">🔨 HDB Wall Hacking & Demolition</a></li>
                                <li style="margin-bottom: 8px;"><a href="/reinstatement/" style="color: #475569;">🏢 Tenancy Reinstatement Works</a></li>
                                <li style="margin-bottom: 8px;"><a href="/tiling-works/" style="color: #475569;">🪵 Tiles & Vinyl Flooring</a></li>
                                <li style="margin-bottom: 8px;"><a href="/waterproofing/" style="color: #475569;">💧 Waterproofing Works</a></li>
                                <li style="margin-bottom: 8px;"><a href="/ceiling-partition/" style="color: #475569;">📐 Ceiling & Partition Works</a></li>
                            </ul>
                        </div>

                    </div>

                </div>
            </div>
        </section>
    </article>

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
                    <h3>Dedicated Services</h3>
                    <ul>
                        <li><a href="/renovation-contractor-singapore/">Renovation Contractor Singapore</a></li>
                        <li><a href="/hdb-renovation/">HDB Renovation</a></li>
                        <li><a href="/hacking-demolition/">Hacking & Demolition</a></li>
                        <li><a href="/tiling-works/">Tiles Flooring</a></li>
                        <li><a href="/vinyl-flooring/">Vinyl Flooring</a></li>
                        <li><a href="/waterproofing/">Waterproofing Works</a></li>
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

    <!-- Toast Notification -->
    <div class="toast-copied" id="copyArticleToast">
        <svg width="20" height="20" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        <span>Article link copied! Ready to share on WhatsApp or Facebook.</span>
    </div>

    <!-- Scripts -->
    <script src="/app.js?v=3.4"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const copyBtn = document.getElementById('copyArticleLinkBtn');
            const copyToast = document.getElementById('copyArticleToast');

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

            const nativeBtn = document.getElementById('nativeShareArticleBtn');
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
                        // User cancelled
                    }
                });
            }
        });
    </script>
</body>
</html>
