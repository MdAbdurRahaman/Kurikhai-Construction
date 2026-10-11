<?php
/**
 * Database Migration & Data Seeding Utility - Tabeeb Contractor
 * 
 * Safely executes versioned migrations and imports legacy data idempotently.
 * Strictly CLI-only. Supports --dry-run flag.
 * 
 * Usage:
 *   php scripts/migrate.php [--dry-run]
 */

// 1. Strict CLI enforcement - fail immediately if accessed via web server
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "403 Forbidden: Migration utility may only be executed via CLI.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/business_tracker.php';

$isDryRun = in_array('--dry-run', $argv ?? [], true);

function log_cli(string $msg): void {
    echo $msg . "\n";
}

log_cli("==========================================================");
log_cli("  Tabeeb Contractor Portal - Database Migration Utility  ");
log_cli("==========================================================");
if ($isDryRun) {
    log_cli("🔍 MODE: DRY-RUN (No database modifications will be committed)");
}

// 2. Verify Database Connectivity
if (!DB::isConnected()) {
    log_cli("❌ Error: Unable to connect to MySQL database (" . DB_HOST . ":" . DB_PORT . "/" . DB_NAME . ").");
    log_cli("Details: " . (DB::getLastError() ?? 'Unknown error'));
    log_cli("Please verify DB_HOST, DB_NAME, DB_USER, DB_PASS in .env or config/config.php.");
    exit(1);
}
log_cli("✅ Connected to MySQL database (" . DB_NAME . ") successfully.");

$pdo = DB::getConnection();

// 3. Ensure schema_migrations table exists
if (!$isDryRun) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `version` VARCHAR(100) PRIMARY KEY,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// 4. Run Base Schema if not yet applied
$baseSchemaFile = dirname(__DIR__) . '/config/schema.sql';
if (file_exists($baseSchemaFile)) {
    $baseMigrated = (bool)db_val("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads'");
    if (!$baseMigrated) {
        log_cli("📦 Applying base schema from config/schema.sql...");
        if (!$isDryRun) {
            $pdo->exec(file_get_contents($baseSchemaFile));
            $pdo->exec("INSERT IGNORE INTO `schema_migrations` (`version`) VALUES ('001_base_schema')");
        }
        log_cli("✅ Base schema applied successfully.");
    } else {
        log_cli("ℹ️ Base schema already applied. Skipping.");
    }
}

// 5. Run Versioned Migrations in config/migrations/
$migrationsDir = dirname(__DIR__) . '/config/migrations';
if (is_dir($migrationsDir)) {
    $migrationFiles = glob($migrationsDir . '/*.sql');
    sort($migrationFiles);

    foreach ($migrationFiles as $migFile) {
        $migName = basename($migFile, '.sql');
        $alreadyRun = (bool)db_val("SELECT 1 FROM `schema_migrations` WHERE `version` = ? LIMIT 1", [$migName]);
        if (!$alreadyRun) {
            log_cli("🚀 Applying versioned migration: {$migName}...");
            $sql = file_get_contents($migFile);
            if (!$isDryRun) {
                // Execute multi-query SQL migration
                $pdo->exec($sql);
                $stmt = $pdo->prepare("INSERT INTO `schema_migrations` (`version`) VALUES (?)");
                $stmt->execute([$migName]);
            }
            log_cli("✅ Migration {$migName} completed successfully.");
        } else {
            log_cli("ℹ️ Migration {$migName} already recorded. Skipping.");
        }
    }
}

// 6. Safe Backup of Legacy JSON Files
log_cli("💾 Creating private timestamped safety backup of legacy JSON stores...");
$backupTime = date('Ymd_His');
foreach (['users.json', 'posts.json', 'services.json', 'leads_queue.json'] as $f) {
    $src = DATA_DIR . '/' . $f;
    if (file_exists($src)) {
        $dest = DATA_DIR . '/backup_' . $backupTime . '_' . $f;
        if (!$isDryRun) {
            @copy($src, $dest);
        }
        log_cli("   Backed up {$f} -> " . basename($dest));
    }
}

// 7. Non-Destructive Import of Services (Never overwrite existing MySQL edits)
log_cli("🏢 Checking services import...");
$servicesJsonFile = DATA_DIR . '/services.json';
if (file_exists($servicesJsonFile)) {
    $services = json_decode(file_get_contents($servicesJsonFile), true) ?: [];
    $importedSrv = 0;
    foreach ($services as $srv) {
        $srvId = $srv['id'] ?? $srv['slug'];
        $exists = (bool)db_val("SELECT 1 FROM `services` WHERE `id` = ? OR `slug` = ? LIMIT 1", [$srvId, $srv['slug']]);
        if (!$exists) {
            $featuresJson = !empty($srv['features']) ? json_encode($srv['features'], JSON_UNESCAPED_UNICODE) : null;
            $processJson = !empty($srv['process']) ? json_encode($srv['process'], JSON_UNESCAPED_UNICODE) : null;
            $faqsJson = !empty($srv['faqs']) ? json_encode($srv['faqs'], JSON_UNESCAPED_UNICODE) : null;

            if (!$isDryRun) {
                $stmt = $pdo->prepare(
                    "INSERT INTO `services` 
                     (`id`, `slug`, `name`, `category_name`, `overview`, `features_json`, `process_json`, `faqs_json`, `og_title`, `og_description`, `image`, `display_order`, `is_published`) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
                );
                $stmt->execute([
                    $srvId, $srv['slug'], $srv['name'], $srv['category_name'] ?? 'Contracting',
                    $srv['overview'] ?? '', $featuresJson, $processJson, $faqsJson,
                    $srv['og_title'] ?? null, $srv['og_description'] ?? null,
                    $srv['image'] ?? 'images/services/renovation.jpg', $importedSrv
                ]);
            }
            $importedSrv++;
        }
    }
    log_cli("✅ Imported {$importedSrv} new services without overwriting existing records.");
}

// 8. Non-Destructive Import of Blog Posts (Never overwrite existing MySQL edits)
log_cli("📝 Checking blog posts import...");
$postsJsonFile = DATA_DIR . '/posts.json';
if (file_exists($postsJsonFile)) {
    $posts = json_decode(file_get_contents($postsJsonFile), true) ?: [];
    $importedPosts = 0;
    foreach ($posts as $p) {
        $exists = (bool)db_val("SELECT 1 FROM `posts` WHERE `id` = ? OR `slug` = ? LIMIT 1", [$p['id'], $p['slug']]);
        if (!$exists) {
            $tagsJson = !empty($p['tags']) ? json_encode($p['tags'], JSON_UNESCAPED_UNICODE) : null;
            if (!$isDryRun) {
                $stmt = $pdo->prepare(
                    "INSERT INTO `posts` 
                     (`id`, `slug`, `title`, `category`, `author`, `excerpt`, `content`, `read_time`, `tags_json`, `image`, `status`, `published_at`, `created_at`, `updated_at`) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $p['id'], $p['slug'], $p['title'], $p['category'] ?? 'General',
                    $p['author'] ?? 'Tabeeb Technical Team', $p['excerpt'] ?? '',
                    $p['content'] ?? '', $p['read_time'] ?? '5 min read',
                    $tagsJson, $p['image'] ?? 'images/page-header-bg.jpg',
                    $p['status'] ?? 'draft',
                    ($p['status'] === 'published') ? ($p['created_at'] ?? date('Y-m-d H:i:s')) : null,
                    $p['created_at'] ?? date('Y-m-d H:i:s'),
                    $p['updated_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
            $importedPosts++;
        }
    }
    log_cli("✅ Imported {$importedPosts} new blog posts without overwriting existing records.");
}

// 9. Non-Destructive Import of Administrative Users with Forced Password Change
log_cli("👥 Checking administrative users...");
$usersJsonFile = DATA_DIR . '/users.json';
if (file_exists($usersJsonFile)) {
    $users = json_decode(file_get_contents($usersJsonFile), true) ?: [];
    $importedUsers = 0;
    foreach ($users as $u) {
        $roleSlug = $u['role'] ?? 'editor';
        $roleId = (int)db_val("SELECT `id` FROM `roles` WHERE `slug` = ? LIMIT 1", [$roleSlug]) ?: 2;
        $exists = (bool)db_val("SELECT 1 FROM `users` WHERE `username` = ? LIMIT 1", [$u['username']]);

        if (!$exists) {
            if (!$isDryRun) {
                $stmt = $pdo->prepare(
                    "INSERT INTO `users` 
                     (`name`, `username`, `email`, `password_hash`, `role_id`, `status`, `force_password_change`, `created_at`) 
                     VALUES (?, ?, ?, ?, ?, ?, 1, ?)"
                );
                $stmt->execute([
                    $u['name'], $u['username'], $u['email'],
                    $u['password'], $roleId, $u['status'] ?? 'active',
                    $u['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
            $importedUsers++;
        }
    }
    log_cli("✅ Imported {$importedUsers} user accounts (force_password_change = 1 enforced).");
}

// 10. Non-Destructive Import of Projects & Client Media (Phase F System)
log_cli("🏗️ Checking project case studies...");
$projectsJsonFile = DATA_DIR . '/projects.json';
if (file_exists($projectsJsonFile)) {
    $projects = json_decode(file_get_contents($projectsJsonFile), true) ?: [];
    $importedProjects = 0;
    foreach ($projects as $proj) {
        $exists = (bool)db_val("SELECT 1 FROM `projects` WHERE `id` = ? OR `slug` = ? LIMIT 1", [$proj['id'], $proj['slug']]);
        if (!$exists) {
            if (!$isDryRun) {
                $stmt = $pdo->prepare(
                    "INSERT INTO `projects` 
                     (`id`, `slug`, `title`, `location_label`, `public_location`, `service_id`, `factual_summary`, `scope_details`, 
                      `duration_label`, `materials_used`, `source_file`, `featured_image`, `video_path`, `video_poster`, 
                      `publication_state`, `approval_status`, `og_title`, `og_description`, `display_order`, `created_at`, `updated_at`) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $proj['id'], $proj['slug'], $proj['title'], $proj['location_label'], $proj['public_location'], $proj['service_id'] ?? null,
                    $proj['factual_summary'], $proj['scope_details'], $proj['duration_label'] ?? null, $proj['materials_used'] ?? null,
                    $proj['source_file'] ?? null, $proj['featured_image'], $proj['video_path'] ?? null, $proj['video_poster'] ?? null,
                    $proj['publication_state'] ?? 'draft', $proj['approval_status'] ?? 'pending',
                    $proj['og_title'] ?? $proj['title'], $proj['og_description'] ?? $proj['factual_summary'],
                    (int)($proj['display_order'] ?? 1), $proj['created_at'] ?? date('Y-m-d H:i:s'), $proj['updated_at'] ?? date('Y-m-d H:i:s')
                ]);

                if (!empty($proj['media']) && is_array($proj['media'])) {
                    $mediaStmt = $pdo->prepare(
                        "INSERT INTO `project_media` 
                         (`project_id`, `media_stage`, `media_type`, `file_path`, `caption`, `alt_text`, `display_order`, `created_at`) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    foreach ($proj['media'] as $m) {
                        $mediaStmt->execute([
                            $proj['id'],
                            $m['media_stage'] ?? 'gallery',
                            $m['media_type'] ?? 'image',
                            $m['file_path'],
                            $m['caption'] ?? '',
                            $m['alt_text'] ?? '',
                            (int)($m['display_order'] ?? 0),
                            $proj['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
            $importedProjects++;
        }
    }
    log_cli("✅ Imported {$importedProjects} project case studies and associated media.");
}

// 11. Seed Initial Business Information Tracker Items
log_cli("📋 Checking Business Information Tracker...");
if (!$isDryRun) {
    BusinessTracker::seedInitialItems();
}
log_cli("✅ Business Information Tracker checked.");

// 12. Notification Routing Note (Do not auto-enable public mailbox without admin setup)
$rcptCount = (int)db_val("SELECT COUNT(*) FROM `notification_recipients`");
if ($rcptCount === 0) {
    log_cli("ℹ️ Routing Notice: No notification recipients are currently enabled.");
    log_cli("   Configure recipients under Admin Portal -> Notification Routing (/admin/notifications.php).");
}

log_cli("==========================================================");
log_cli("✅ Migration process completed successfully.");
log_cli("==========================================================");
