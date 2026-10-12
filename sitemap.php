<?php
/**
 * Dynamic XML Sitemap Generator - Tabeeb Contractor
 * Generates standards-compliant XML sitemap including verified services, projects, and blog posts.
 * Strictly adheres to canonical non-trailing-slash rules and authentic lastmod timestamps (no dynamic today dates).
 */

header('Content-Type: application/xml; charset=utf-8');

require_once __DIR__ . '/includes/data.php';

$baseUrl = rtrim(get_base_url(), '/');
$services = get_all_services(true);
$posts = get_all_posts(true);
$projects = get_all_projects(true);

// Static page authentic modification dates
$homeMod = date('Y-m-d', filemtime(__DIR__ . '/index.php'));
$servicesMod = date('Y-m-d', filemtime(__DIR__ . '/services.php'));
$aboutMod = date('Y-m-d', filemtime(__DIR__ . '/about.php'));
$contactMod = date('Y-m-d', filemtime(__DIR__ . '/contact.php'));
$projectsMod = file_exists(__DIR__ . '/projects.php') ? date('Y-m-d', filemtime(__DIR__ . '/projects.php')) : $servicesMod;
$blogMod = date('Y-m-d', filemtime(__DIR__ . '/blog.php'));
$privacyMod = file_exists(__DIR__ . '/privacy.php') ? date('Y-m-d', filemtime(__DIR__ . '/privacy.php')) : '2026-10-09';
$termsMod = file_exists(__DIR__ . '/terms.php') ? date('Y-m-d', filemtime(__DIR__ . '/terms.php')) : '2026-10-09';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <!-- Core Navigable Pages (Non-trailing slash canonicals) -->
  <url>
    <loc><?= $baseUrl ?>/</loc>
    <lastmod><?= $homeMod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
    <image:image>
      <image:loc><?= $baseUrl ?>/images/logo-horizontal.png</image:loc>
      <image:title>Tabeeb Contractor – Renovation Contractor Singapore</image:title>
    </image:image>
  </url>

  <url>
    <loc><?= $baseUrl ?>/services</loc>
    <lastmod><?= $servicesMod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>

  <url>
    <loc><?= $baseUrl ?>/projects</loc>
    <lastmod><?= $projectsMod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>

  <url>
    <loc><?= $baseUrl ?>/about</loc>
    <lastmod><?= $aboutMod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>

  <url>
    <loc><?= $baseUrl ?>/contact</loc>
    <lastmod><?= $contactMod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>

  <url>
    <loc><?= $baseUrl ?>/blog</loc>
    <lastmod><?= $blogMod ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>

  <url>
    <loc><?= $baseUrl ?>/privacy</loc>
    <lastmod><?= $privacyMod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

  <url>
    <loc><?= $baseUrl ?>/terms</loc>
    <lastmod><?= $termsMod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

  <!-- Dedicated High-Intent Specialist Service Pages (Non-trailing slash) -->
  <?php foreach ($services as $srv): ?>
    <url>
      <loc><?= $baseUrl ?>/<?= htmlspecialchars($srv['slug']) ?></loc>
      <lastmod><?= !empty($srv['updated_at']) ? date('Y-m-d', strtotime($srv['updated_at'])) : $servicesMod ?></lastmod>
      <changefreq>weekly</changefreq>
      <priority>0.9</priority>
      <?php if (!empty($srv['image'])): ?>
      <image:image>
        <image:loc><?= $baseUrl ?>/<?= ltrim(htmlspecialchars($srv['image']), '/') ?></image:loc>
        <image:title><?= htmlspecialchars($srv['name']) ?> Contractor Singapore</image:title>
      </image:image>
      <?php endif; ?>
    </url>
  <?php endforeach; ?>

  <!-- Verified Project Case Studies -->
  <?php foreach ($projects as $proj): ?>
    <url>
      <loc><?= $baseUrl ?>/projects/<?= htmlspecialchars($proj['slug']) ?></loc>
      <lastmod><?= !empty($proj['updated_at']) ? date('Y-m-d', strtotime($proj['updated_at'])) : date('Y-m-d', strtotime($proj['created_at'])) ?></lastmod>
      <changefreq>monthly</changefreq>
      <priority>0.8</priority>
      <?php if (!empty($proj['featured_image'])): ?>
      <image:image>
        <image:loc><?= $baseUrl ?>/<?= ltrim(htmlspecialchars($proj['featured_image']), '/') ?></image:loc>
        <image:title><?= htmlspecialchars($proj['title']) ?> – Tabeeb Contractor</image:title>
      </image:image>
      <?php endif; ?>
    </url>
  <?php endforeach; ?>

  <!-- Published Renovation & Permit Guides -->
  <?php foreach ($posts as $post): ?>
    <url>
      <loc><?= $baseUrl ?>/blog/<?= htmlspecialchars($post['slug']) ?></loc>
      <lastmod><?= !empty($post['updated_at']) ? date('Y-m-d', strtotime($post['updated_at'])) : date('Y-m-d', strtotime($post['created_at'])) ?></lastmod>
      <changefreq>monthly</changefreq>
      <priority>0.7</priority>
      <?php if (!empty($post['image'])): ?>
      <image:image>
        <image:loc><?= $baseUrl ?>/<?= ltrim(htmlspecialchars($post['image']), '/') ?></image:loc>
        <image:title><?= htmlspecialchars($post['title']) ?></image:title>
      </image:image>
      <?php endif; ?>
    </url>
  <?php endforeach; ?>

</urlset>
