<?php
/**
 * Dynamic XML Sitemap Generator - Tabeeb Contractor
 * Generates standards-compliant XML sitemap including verified services and blog posts.
 */

header('Content-Type: application/xml; charset=utf-8');

require_once __DIR__ . '/includes/data.php';

$baseUrl = get_base_url();
$services = get_all_services(true);
$posts = get_all_posts(true);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <!-- Core Static Pages -->
  <url>
    <loc><?= $baseUrl ?>/</loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
    <image:image>
      <image:loc><?= $baseUrl ?>/images/hero-bg.jpg</image:loc>
      <image:title>Tabeeb Contractor – Renovation Contractor Singapore</image:title>
    </image:image>
  </url>

  <url>
    <loc><?= $baseUrl ?>/services</loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>

  <url>
    <loc><?= $baseUrl ?>/about</loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>

  <url>
    <loc><?= $baseUrl ?>/contact</loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>

  <url>
    <loc><?= $baseUrl ?>/blog</loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>

  <!-- Dedicated SEO Service Landing Pages -->
  <?php foreach ($services as $srv): ?>
    <url>
      <loc><?= $baseUrl ?>/<?= htmlspecialchars($srv['slug']) ?>/</loc>
      <lastmod><?= !empty($srv['updated_at']) ? date('Y-m-d', strtotime($srv['updated_at'])) : date('Y-m-d') ?></lastmod>
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

  <!-- Published Blog Guides -->
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
