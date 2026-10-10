<?php
/**
 * Authoritative Public Website Header Component - Tabeeb Contractor
 * 
 * Powered by BusinessTracker: single-source brand, contact facts, and operating hours.
 */

if (!function_exists('get_company_info')) {
    require_once __DIR__ . '/data.php';
}
if (!class_exists('BusinessTracker')) {
    require_once __DIR__ . '/business_tracker.php';
}

$company = get_company_info();
$currentRoute = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$activeNav = $activeNav ?? '';

if (empty($activeNav)) {
    if ($currentRoute === '/' || $currentRoute === '/index.php') $activeNav = 'home';
    elseif (str_starts_with($currentRoute, '/services')) $activeNav = 'services';
    elseif (str_starts_with($currentRoute, '/projects')) $activeNav = 'projects';
    elseif (str_starts_with($currentRoute, '/blog')) $activeNav = 'blog';
    elseif (str_starts_with($currentRoute, '/about')) $activeNav = 'about';
    elseif (str_starts_with($currentRoute, '/contact')) $activeNav = 'contact';
}
?>
<!-- Header Navigation with Topbar -->
<header class="header" id="header">
    <!-- Top Contact Bar -->
    <div class="topbar">
        <div class="container">
            <div class="topbar-left">
                <span class="topbar-item">
                    <span class="topbar-icon">📍</span>
                    <a href="<?= htmlspecialchars($company['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;">
                        <?= htmlspecialchars($company['address']) ?>
                    </a>
                </span>
                <?php if (!empty($company['opening_hours'])): ?>
                    <span class="topbar-item">
                        <span class="topbar-icon">🕒</span> <?= htmlspecialchars($company['opening_hours']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="topbar-right">
                <span class="topbar-item">
                    <span class="topbar-icon">📞</span>
                    <a href="tel:<?= htmlspecialchars($company['phone_raw']) ?>" id="nav-hotline-link"><?= htmlspecialchars($company['phone']) ?></a>
                </span>
                <a href="https://wa.me/<?= htmlspecialchars($company['phone_raw']) ?>?text=<?= urlencode('Hello Tabeeb Contractor, I would like to enquire about a renovation project.') ?>" target="_blank" rel="noopener noreferrer" class="topbar-whatsapp" id="nav-whatsapp-link">
                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg> WhatsApp Us
                </a>
            </div>
        </div>
    </div>
    
    <div class="header-main">
        <div class="container">
            <div class="header-wrapper">
                <a href="/" class="logo" id="header-logo" aria-label="<?= htmlspecialchars($company['brand_name']) ?>">
                    <img src="/images/logo-horizontal.png?v=3.4" alt="<?= htmlspecialchars($company['brand_name']) ?> - Building Better Spaces" class="header-logo-img" width="220" height="52">
                </a>
                <nav aria-label="Main Navigation">
                    <ul class="nav-list" id="nav-list">
                        <li><a href="/" class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>">Home</a></li>
                        <li><a href="/services" class="nav-link <?= $activeNav === 'services' ? 'active' : '' ?>">Services</a></li>
                        <li><a href="/projects" class="nav-link <?= $activeNav === 'projects' ? 'active' : '' ?>">Projects</a></li>
                        <li><a href="/blog" class="nav-link <?= $activeNav === 'blog' ? 'active' : '' ?>">Blog</a></li>
                        <li><a href="/about" class="nav-link <?= $activeNav === 'about' ? 'active' : '' ?>">About Us</a></li>
                        <li><a href="/contact" class="nav-link <?= $activeNav === 'contact' ? 'active' : '' ?>">Contact</a></li>
                        <li><a href="javascript:void(0)" class="btn btn-quote-header open-quote-modal-btn" id="nav-quote-cta">Get a Quote</a></li>
                    </ul>
                </nav>
                <button class="burger" id="burger-menu" aria-label="Toggle navigation menu">
                    <div class="line1"></div>
                    <div class="line2"></div>
                    <div class="line3"></div>
                </button>
            </div>
        </div>
    </div>
</header>
