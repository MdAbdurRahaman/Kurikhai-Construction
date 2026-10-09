<?php
require_once __DIR__ . '/includes/data.php';

$posts = get_all_posts(true); // Only published posts
$baseUrl = get_base_url();
$canonicalUrl = $baseUrl . '/blog';

// Extract unique categories
$categories = [];
foreach ($posts as $p) {
    if (!empty($p['category']) && !in_array($p['category'], $categories)) {
        $categories[] = $p['category'];
    }
}

$pageTitle = "Renovation, Hacking & Construction Blog Singapore | Tabeeb Contractor";
$pageDesc = "Expert Singapore renovation guides, HDB hacking permits, commercial reinstatement checklists, waterproofing tips, and direct contractor advice.";
$ogImage = $baseUrl . '/images/hero-bg.jpg';
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

    <!-- Canonical URL -->
    <link rel="canonical" href="<?= $canonicalUrl ?>">

    <!-- Open Graph / Facebook & WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:image" content="<?= $ogImage ?>">
    <meta property="og:site_name" content="Tabeeb Contractor Singapore">
    <meta property="og:locale" content="en_SG">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= $canonicalUrl ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="twitter:image" content="<?= $ogImage ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/style.css?v=3.4">

    <style>
        .blog-hero {
            padding: 130px 0 60px;
            background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
            color: #fff;
            text-align: center;
        }
        .blog-hero h1 {
            color: #fff;
            font-size: clamp(2rem, 3.5vw, 2.75rem);
            margin-bottom: 16px;
        }
        .blog-hero p {
            font-size: 1.15rem;
            color: #cbd5e1;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Search & Filter Bar */
        .blog-toolbar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 24px;
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
        .category-pills {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cat-pill {
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .cat-pill:hover, .cat-pill.active {
            background: var(--color-primary);
            color: #ffffff;
            border-color: var(--color-primary);
        }
        .blog-search-box {
            position: relative;
            min-width: 260px;
        }
        .blog-search-box input {
            width: 100%;
            padding: 10px 16px 10px 38px;
            border: 1px solid #cbd5e1;
            border-radius: 9999px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s ease;
            font-family: inherit;
        }
        .blog-search-box input:focus {
            border-color: var(--color-accent);
        }
        .blog-search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        /* Blog Card Grid */
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }
        .blog-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            display: flex;
            flex-direction: column;
        }
        .blog-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 32px -8px rgba(0, 0, 0, 0.12);
            border-color: #38bdf8;
        }
        .blog-card-media {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: #07274d;
        }
        .blog-card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .blog-card:hover .blog-card-media img {
            transform: scale(1.05);
        }
        .blog-card-cat {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(7, 39, 77, 0.85);
            backdrop-filter: blur(4px);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.4);
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .blog-card-readtime {
            position: absolute;
            bottom: 12px;
            right: 14px;
            background: rgba(15, 23, 42, 0.75);
            color: #fff;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
        }
        .blog-card-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .blog-meta-row {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: var(--color-text-muted);
            margin-bottom: 12px;
        }
        .blog-card-title {
            font-size: 20px;
            line-height: 1.4;
            margin-bottom: 12px;
            color: var(--color-primary);
        }
        .blog-card-title a {
            color: inherit;
        }
        .blog-card-title a:hover {
            color: var(--color-accent);
        }
        .blog-card-excerpt {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 20px;
            flex-grow: 1;
        }
        .blog-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
            margin-top: auto;
        }
        .blog-read-link {
            font-size: 14px;
            font-weight: 600;
            color: var(--color-accent-hover);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .blog-read-link:hover {
            color: var(--color-primary);
        }
        .quick-share-btn {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .quick-share-btn:hover {
            background: #25d366;
            color: #fff;
            border-color: #25d366;
        }

        /* Empty state */
        .no-posts-found {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px dashed #cbd5e1;
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
                    <a href="/" class="logo" id="header-logo" aria-label="Tabeeb Contractor">
                        <img src="/images/logo-horizontal.png?v=3.3" alt="Tabeeb Contractor" class="header-logo-img">
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

    <!-- Blog Hero -->
    <section class="blog-hero">
        <div class="container">
            <div class="breadcrumbs" style="color: #94a3b8; margin-bottom: 12px; font-size: 14px;">
                <a href="/" style="color: #38bdf8;">Home</a> &gt; <span>Blog & Guides</span>
            </div>
            <h1>Construction & Renovation Guides Singapore</h1>
            <p>Practical insights on HDB renovation permits, commercial office reinstatement checklists, waterproofing repairs, and smart cost-saving contractor advice.</p>
        </div>
    </section>

    <!-- Search & Filter Toolbar -->
    <div class="container">
        <div class="blog-toolbar">
            <div class="category-pills">
                <button type="button" class="cat-pill active" data-filter="all">All Articles (<?= count($posts) ?>)</button>
                <?php foreach ($categories as $cat): ?>
                    <button type="button" class="cat-pill" data-filter="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="blog-search-box">
                <svg class="blog-search-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="blogSearchInput" placeholder="Search guides & topics...">
            </div>
        </div>
    </div>

    <!-- Blog Posts Grid -->
    <section class="section" style="padding-top: 10px;">
        <div class="container">
            <div class="blog-grid" id="blogGrid">
                <?php if (empty($posts)): ?>
                    <div class="no-posts-found">
                        <h3>No blog posts published yet</h3>
                        <p style="color: #64748b; margin-top: 8px;">Check back soon for new articles and contractor guides.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): 
                        $postUrl = '/blog/' . htmlspecialchars($post['slug']);
                        $postFullUrl = $baseUrl . $postUrl;
                        $postImg = (strpos($post['image'], 'http') === 0) ? $post['image'] : ('/' . ltrim($post['image'], '/'));
                        $waText = urlencode($post['title'] . "\n" . $postFullUrl);
                    ?>
                        <article class="blog-card" data-category="<?= htmlspecialchars($post['category']) ?>" data-title="<?= htmlspecialchars(strtolower($post['title'])) ?>">
                            <div class="blog-card-media">
                                <a href="<?= $postUrl ?>">
                                    <img src="<?= $postImg ?>" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
                                </a>
                                <span class="blog-card-cat"><?= htmlspecialchars($post['category']) ?></span>
                                <span class="blog-card-readtime"><?= htmlspecialchars($post['read_time'] ?? '5 min read') ?></span>
                            </div>
                            <div class="blog-card-body">
                                <div class="blog-meta-row">
                                    <span>📅 <?= format_date($post['created_at']) ?></span>
                                    <span>•</span>
                                    <span>✍️ <?= htmlspecialchars($post['author'] ?? 'Tabeeb Contractor') ?></span>
                                </div>
                                <h3 class="blog-card-title">
                                    <a href="<?= $postUrl ?>"><?= htmlspecialchars($post['title']) ?></a>
                                </h3>
                                <p class="blog-card-excerpt">
                                    <?= htmlspecialchars($post['excerpt']) ?>
                                </p>
                                <div class="blog-card-footer">
                                    <a href="<?= $postUrl ?>" class="blog-read-link">
                                        Read Guide <span>➔</span>
                                    </a>
                                    <a href="https://wa.me/?text=<?= $waText ?>" target="_blank" rel="noopener noreferrer" class="quick-share-btn" title="Share this article on WhatsApp">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Bottom Consultation CTA -->
    <section class="cta-banner" style="background-color: var(--color-primary-dark);">
        <div class="container">
            <h2>Have Questions About Your Upcoming Project?</h2>
            <p>Our team of experienced technical specialists can walk you through HDB permits, landlord reinstatement requirements, and provide transparent site quotations.</p>
            <a href="https://wa.me/6586484883?text=Hello%20Tabeeb%20Contractor,%20I%20have%20questions%20regarding%20a%20renovation%20project." target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="background: var(--color-gold); border-color: var(--color-gold); margin-top: 15px;">Ask Our Team on WhatsApp</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col footer-brand-col">
                    <a href="/" class="footer-logo-link" aria-label="Tabeeb Contractor">
                        <img src="/images/logo-white.png?v=3.3" alt="Tabeeb Contractor" class="footer-logo-img" width="220">
                    </a>
                    <p>Tabeeb Contractor is a direct contracting firm specializing in commercial office reinstatement, HDB wall hacking, architectural tiling, waterproofing, and structural alterations in Singapore.</p>
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
                <p>&copy; <?= date('Y') ?> Tabeeb Contractor. All Rights Reserved. UEN: 202506878W.</p>
                <div class="footer-legal-links" style="display: flex; gap: 16px;">
                    <a href="/privacy" style="color: #94a3b8; font-size: 13px; text-decoration: none;">Privacy Policy</a>
                    <a href="/terms" style="color: #94a3b8; font-size: 13px; text-decoration: none;">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="/app.js?v=3.4"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filterBtns = document.querySelectorAll('.cat-pill');
            const searchInput = document.getElementById('blogSearchInput');
            const cards = document.querySelectorAll('.blog-card');

            let currentCategory = 'all';
            let currentSearch = '';

            const applyFilters = () => {
                let visibleCount = 0;
                cards.forEach(card => {
                    const cat = card.getAttribute('data-category');
                    const title = card.getAttribute('data-title');
                    
                    const matchesCat = (currentCategory === 'all' || cat === currentCategory);
                    const matchesSearch = (!currentSearch || title.includes(currentSearch));

                    if (matchesCat && matchesSearch) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
            };

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentCategory = btn.getAttribute('data-filter');
                    applyFilters();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    currentSearch = e.target.value.toLowerCase().trim();
                    applyFilters();
                });
            }
        });
    </script>
</body>
</html>
