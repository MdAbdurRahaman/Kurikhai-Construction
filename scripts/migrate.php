<?php
/**
 * Database Migration & Data Seeding Utility - Tabeeb Contractor
 * 
 * Safely creates tables from config/schema.sql and imports legacy JSON records
 * (users.json, posts.json, services.json) into MySQL/MariaDB idempotently.
 * 
 * Run from terminal:
 *   php scripts/migrate.php
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/business_tracker.php';

$isCli = (php_sapi_name() === 'cli');

function log_msg(string $msg): void {
    global $isCli;
    echo $isCli ? "{$msg}\n" : "<p style='font-family: monospace;'>" . htmlspecialchars($msg) . "</p>\n";
}

log_msg("=== Starting Tabeeb Contractor Portal Migration ===");

// 1. Verify Database Connectivity
if (!DB::isConnected()) {
    log_msg("❌ Error: Unable to connect to database (" . DB_HOST . ":" . DB_PORT . "/" . DB_NAME . ").");
    log_msg("Details: " . (DB::getLastError() ?? 'Unknown error'));
    log_msg("Please verify database credentials in config/config.php or .env.");
    exit(1);
}
log_msg("✅ Connected to MySQL database successfully.");

// 2. Execute Schema Migration SQL
$schemaFile = dirname(__DIR__) . '/config/schema.sql';
if (!file_exists($schemaFile)) {
    log_msg("❌ Error: Schema file {$schemaFile} not found.");
    exit(1);
}

log_msg("📦 Executing DDL statements from config/schema.sql...");
$schemaSql = file_get_contents($schemaFile);
$pdo = DB::getConnection();

try {
    $pdo->exec($schemaSql);
    log_msg("✅ Database tables and indexes created/verified successfully.");
} catch (PDOException $e) {
    log_msg("❌ Schema execution error: " . $e->getMessage());
    exit(1);
}

// 3. Backup Legacy JSON Files
log_msg("💾 Creating safety backups of legacy JSON files in data/...");
$backupTime = date('Ymd_His');
foreach (['users.json', 'posts.json', 'services.json'] as $f) {
    $src = DATA_DIR . '/' . $f;
    if (file_exists($src)) {
        $dest = DATA_DIR . '/backup_' . $backupTime . '_' . $f;
        @copy($src, $dest);
    }
}
log_msg("✅ JSON backups created.");

// 4. Migrate Services (data/services.json -> services table)
log_msg("🏢 Migrating services...");
$servicesJsonFile = DATA_DIR . '/services.json';
if (file_exists($servicesJsonFile)) {
    $services = json_decode(file_get_contents($servicesJsonFile), true) ?: [];
    $countSrv = 0;
    foreach ($services as $srv) {
        $featuresJson = !empty($srv['features']) ? json_encode($srv['features'], JSON_UNESCAPED_UNICODE) : null;
        $processJson = !empty($srv['process']) ? json_encode($srv['process'], JSON_UNESCAPED_UNICODE) : null;
        $faqsJson = !empty($srv['faqs']) ? json_encode($srv['faqs'], JSON_UNESCAPED_UNICODE) : null;

        $stmt = $pdo->prepare(
            "INSERT INTO `services` 
             (`id`, `slug`, `name`, `category_name`, `overview`, `features_json`, `process_json`, `faqs_json`, `og_title`, `og_description`, `image`, `display_order`, `is_published`) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE 
             `name` = VALUES(`name`), `overview` = VALUES(`overview`), `features_json` = VALUES(`features_json`),
             `process_json` = VALUES(`process_json`), `faqs_json` = VALUES(`faqs_json`), `og_title` = VALUES(`og_title`),
             `og_description` = VALUES(`og_description`), `image` = VALUES(`image`)"
        );
        $stmt->execute([
            $srv['id'] ?? $srv['slug'],
            $srv['slug'],
            $srv['name'],
            $srv['category_name'] ?? 'Contracting',
            $srv['overview'] ?? '',
            $featuresJson,
            $processJson,
            $faqsJson,
            $srv['og_title'] ?? null,
            $srv['og_description'] ?? null,
            $srv['image'] ?? 'images/services/renovation.jpg',
            $countSrv++
        ]);
    }
    log_msg("✅ Migrated {$countSrv} services successfully.");
}

// 5. Migrate Blog Posts (data/posts.json -> posts table)
log_msg("📝 Migrating blog posts...");
$postsJsonFile = DATA_DIR . '/posts.json';
if (file_exists($postsJsonFile)) {
    $posts = json_decode(file_get_contents($postsJsonFile), true) ?: [];
    $countPosts = 0;
    foreach ($posts as $p) {
        $tagsJson = !empty($p['tags']) ? json_encode($p['tags'], JSON_UNESCAPED_UNICODE) : null;
        $stmt = $pdo->prepare(
            "INSERT INTO `posts` 
             (`id`, `slug`, `title`, `category`, `author`, `excerpt`, `content`, `read_time`, `tags_json`, `image`, `status`, `published_at`, `created_at`, `updated_at`) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
             `title` = VALUES(`title`), `excerpt` = VALUES(`excerpt`), `content` = VALUES(`content`),
             `tags_json` = VALUES(`tags_json`), `image` = VALUES(`image`), `status` = VALUES(`status`)"
        );
        $stmt->execute([
            $p['id'],
            $p['slug'],
            $p['title'],
            $p['category'] ?? 'General',
            $p['author'] ?? 'Tabeeb Technical Team',
            $p['excerpt'] ?? '',
            $p['content'] ?? '',
            $p['read_time'] ?? '5 min read',
            $tagsJson,
            $p['image'] ?? 'images/page-header-bg.jpg',
            $p['status'] ?? 'draft',
            ($p['status'] === 'published') ? ($p['created_at'] ?? date('Y-m-d H:i:s')) : null,
            $p['created_at'] ?? date('Y-m-d H:i:s'),
            $p['updated_at'] ?? date('Y-m-d H:i:s')
        ]);
        $countPosts++;
    }
    log_msg("✅ Migrated {$countPosts} blog posts successfully.");
}

// 6. Migrate Users (data/users.json -> users table)
log_msg("👥 Migrating administrative users...");
$usersJsonFile = DATA_DIR . '/users.json';
if (file_exists($usersJsonFile)) {
    $users = json_decode(file_get_contents($usersJsonFile), true) ?: [];
    $countUsers = 0;
    foreach ($users as $u) {
        $roleSlug = $u['role'] ?? 'editor';
        $roleId = (int)db_val("SELECT `id` FROM `roles` WHERE `slug` = ? LIMIT 1", [$roleSlug]) ?: 2;
        $exists = db_val("SELECT `id` FROM `users` WHERE `username` = ? LIMIT 1", [$u['username']]);

        if (!$exists) {
            $stmt = $pdo->prepare(
                "INSERT INTO `users` 
                 (`name`, `username`, `email`, `password_hash`, `role_id`, `status`, `force_password_change`, `created_at`) 
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?)"
            );
            $stmt->execute([
                $u['name'],
                $u['username'],
                $u['email'],
                $u['password'],
                $roleId,
                $u['status'] ?? 'active',
                $u['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $countUsers++;
        }
    }
    log_msg("✅ Imported {$countUsers} new user accounts (with force_password_change = 1 flag).");
}

// 7. Seed Business Information Tracker
log_msg("📋 Seeding Business Information Tracker items...");
BusinessTracker::seedInitialItems();
log_msg("✅ Business Information Tracker items seeded.");

// 8. Seed Notification Recipient (info@tabeebgroup.com)
$rcptExists = db_val("SELECT `id` FROM `notification_recipients` WHERE `email` = 'info@tabeebgroup.com' LIMIT 1");
if (!$rcptExists) {
    db_exec(
        "INSERT INTO `notification_recipients` (`name`, `email`, `is_active`, `notification_type`, `recipient_type`) 
         VALUES ('General Enquiries', 'info@tabeebgroup.com', 1, 'all_leads', 'to')"
    );
    log_msg("✅ Registered info@tabeebgroup.com as active notification recipient.");
}

log_msg("=== Migration Completed Successfully ===");
