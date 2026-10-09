<?php
/**
 * Data Access Layer & Business Entity Repository - Tabeeb Contractor
 * 
 * Provides unified data access backed by MySQL with JSON failover,
 * ensuring backwards compatibility while elevating data security.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/business_tracker.php';

define('POSTS_FILE', DATA_DIR . '/posts.json');
define('USERS_FILE', DATA_DIR . '/users.json');
define('SERVICES_FILE', DATA_DIR . '/services.json');

/**
 * Single-Source Company & Contact Information
 */
function get_company_info(): array {
    return [
        'brand_name'      => BusinessTracker::getPublicValue('brand_name', 'Tabeeb Contractor'),
        'legal_name'      => BusinessTracker::getPublicValue('registered_legal_name', 'Tabeeb Contractor'),
        'uen'             => BusinessTracker::getPublicValue('uen_number', '202506878W'),
        'address'         => BusinessTracker::getPublicValue('business_address', '61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park, Singapore 417943'),
        'phone'           => BusinessTracker::getPublicValue('main_phone', '+65 8648 4883'),
        'phone_raw'       => '6586484883',
        'whatsapp'        => BusinessTracker::getPublicValue('whatsapp_number', '+65 8648 4883'),
        'email'           => BusinessTracker::getPublicValue('business_email', 'info@tabeebgroup.com'),
        'opening_hours'   => 'Mon - Sat: 9:00 AM - 6:00 PM (Closed on Sundays & Public Holidays)',
        'google_maps_url' => BusinessTracker::getPublicValue('google_maps_url', 'https://www.google.com/maps/place/TABEEB+CONTRACTOR+PTE+LTD/@1.3363576,103.9091695,1128m/data=!3m2!1e3!4b1!4m6!3m5!1s0x31da17d8a42c489f:0x834162f16ce7e27b!8m2!3d1.3363576!4d103.9117444!16s%2Fg%2F11zxps0z_m?entry=ttu')
    ];
}

/**
 * Return strict canonical application URL (Preventing Host Header poisoning)
 */
function get_base_url(): string {
    return APP_URL;
}

/**
 * Clean slug generator for SEO URLs
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    if (function_exists('iconv')) {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    }
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'item-' . time() : $text;
}

/**
 * JSON File Read / Write Helpers (Fallback & Backups)
 */
function read_json_file(string $file, array $default = []): array {
    if (!file_exists($file)) {
        return $default;
    }
    $content = @file_get_contents($file);
    if ($content === false || empty(trim($content))) {
        return $default;
    }
    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

function write_json_file(string $file, array $data): bool {
    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0755, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $tmpFile = $file . '.tmp.' . uniqid();
    if (file_put_contents($tmpFile, $json, LOCK_EX) !== false) {
        if (@rename($tmpFile, $file)) {
            @chmod($file, 0644);
            return true;
        }
    }
    @unlink($tmpFile);
    return false;
}

/* ==========================================================================
   BLOG POSTS OPERATIONS
   ========================================================================== */

function get_all_posts(bool $publishedOnly = true): array {
    if (DB::isConnected()) {
        $sql = "SELECT * FROM `posts`";
        if ($publishedOnly) {
            $sql .= " WHERE `status` = 'published' AND `published_at` <= NOW()";
        }
        $sql .= " ORDER BY `created_at` DESC";
        $rows = db_all($sql);
        foreach ($rows as &$r) {
            if (!empty($r['tags_json'])) {
                $r['tags'] = is_string($r['tags_json']) ? json_decode($r['tags_json'], true) : $r['tags_json'];
            } else {
                $r['tags'] = [];
            }
        }
        return $rows;
    }

    $posts = read_json_file(POSTS_FILE, []);
    if ($publishedOnly) {
        $posts = array_filter($posts, fn($p) => ($p['status'] ?? '') === 'published');
    }
    usort($posts, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return array_values($posts);
}

function get_post_by_slug(string $slug, bool $publishedOnly = true): ?array {
    if (DB::isConnected()) {
        $sql = "SELECT * FROM `posts` WHERE `slug` = ?";
        if ($publishedOnly) {
            $sql .= " AND `status` = 'published'";
        }
        $sql .= " LIMIT 1";
        $post = db_one($sql, [$slug]);
        if ($post) {
            $post['tags'] = !empty($post['tags_json']) ? json_decode($post['tags_json'], true) : [];
            // Increment view count
            db_exec("UPDATE `posts` SET `views_count` = `views_count` + 1 WHERE `id` = ?", [$post['id']]);
            return $post;
        }
        return null;
    }

    $posts = read_json_file(POSTS_FILE, []);
    foreach ($posts as $p) {
        if ($p['slug'] === $slug) {
            if ($publishedOnly && ($p['status'] ?? '') !== 'published') {
                return null;
            }
            return $p;
        }
    }
    return null;
}

function get_post_by_id(string $id): ?array {
    if (DB::isConnected()) {
        $post = db_one("SELECT * FROM `posts` WHERE `id` = ? LIMIT 1", [$id]);
        if ($post) {
            $post['tags'] = !empty($post['tags_json']) ? json_decode($post['tags_json'], true) : [];
            return $post;
        }
    }
    $posts = read_json_file(POSTS_FILE, []);
    foreach ($posts as $p) {
        if ($p['id'] === $id) return $p;
    }
    return null;
}

function save_post(array $data): ?array {
    // Sanitize HTML content to prevent Stored XSS
    $cleanContent = sanitize_html_content($data['content'] ?? '');
    $now = date('Y-m-d H:i:s');
    $tags = is_array($data['tags'] ?? null) ? $data['tags'] : parse_tags($data['tags'] ?? '');
    $tagsJson = json_encode($tags);

    if (empty($data['id'])) {
        // Create new post
        $id = 'post_' . time() . '_' . substr(md5(uniqid()), 0, 4);
        $slug = !empty($data['slug']) ? slugify($data['slug']) : slugify($data['title']);

        $post = [
            'id'           => $id,
            'title'        => trim($data['title']),
            'slug'         => $slug,
            'category'     => trim($data['category'] ?? 'General'),
            'author'       => trim($data['author'] ?? 'Tabeeb Technical Team'),
            'status'       => in_array($data['status'] ?? '', ['published', 'draft', 'archived']) ? $data['status'] : 'draft',
            'published_at' => ($data['status'] === 'published') ? $now : null,
            'created_at'   => $now,
            'updated_at'   => $now,
            'image'        => trim($data['image'] ?? 'images/page-header-bg.jpg'),
            'read_time'    => !empty($data['read_time']) ? trim($data['read_time']) : estimate_read_time($cleanContent),
            'excerpt'      => trim($data['excerpt'] ?? ''),
            'tags'         => $tags,
            'content'      => $cleanContent
        ];

        if (DB::isConnected()) {
            db_exec(
                "INSERT INTO `posts` 
                 (`id`, `slug`, `title`, `category`, `author`, `excerpt`, `content`, `read_time`, `tags_json`, `image`, `status`, `published_at`, `created_at`, `updated_at`) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $post['id'], $post['slug'], $post['title'], $post['category'], $post['author'],
                    $post['excerpt'], $post['content'], $post['read_time'], $tagsJson, $post['image'],
                    $post['status'], $post['published_at'], $post['created_at'], $post['updated_at']
                ]
            );
        }

        // Backup to JSON
        $posts = read_json_file(POSTS_FILE, []);
        array_unshift($posts, $post);
        write_json_file(POSTS_FILE, $posts);

        audit_log('post.created', 'post', $id, null, ['title' => $post['title']]);
        return $post;
    } else {
        // Update existing post
        $id = $data['id'];
        $existing = get_post_by_id($id);
        if (!$existing) return null;

        $slug = !empty($data['slug']) ? slugify($data['slug']) : $existing['slug'];
        $pubAt = ($data['status'] === 'published' && empty($existing['published_at'])) ? $now : ($existing['published_at'] ?? $now);

        if (DB::isConnected()) {
            db_exec(
                "UPDATE `posts` SET 
                 `slug` = ?, `title` = ?, `category` = ?, `author` = ?, `excerpt` = ?, 
                 `content` = ?, `read_time` = ?, `tags_json` = ?, `image` = ?, `status` = ?, 
                 `published_at` = ?, `updated_at` = ? 
                 WHERE `id` = ?",
                [
                    $slug, trim($data['title']), trim($data['category'] ?? $existing['category']),
                    trim($data['author'] ?? $existing['author']), trim($data['excerpt'] ?? ''),
                    $cleanContent, trim($data['read_time'] ?? $existing['read_time']), $tagsJson,
                    trim($data['image'] ?? $existing['image']), $data['status'] ?? $existing['status'],
                    $pubAt, $now, $id
                ]
            );
        }

        // Update JSON file
        $posts = read_json_file(POSTS_FILE, []);
        foreach ($posts as &$p) {
            if ($p['id'] === $id) {
                $p['title'] = trim($data['title']);
                $p['slug'] = $slug;
                $p['category'] = trim($data['category'] ?? $p['category']);
                $p['author'] = trim($data['author'] ?? $p['author']);
                $p['status'] = $data['status'] ?? $p['status'];
                $p['excerpt'] = trim($data['excerpt'] ?? '');
                $p['content'] = $cleanContent;
                $p['tags'] = $tags;
                if (!empty($data['image'])) $p['image'] = trim($data['image']);
                $p['updated_at'] = $now;
                break;
            }
        }
        write_json_file(POSTS_FILE, $posts);

        audit_log('post.updated', 'post', $id, $existing, ['title' => $data['title']]);
        return get_post_by_id($id);
    }
}

function delete_post(string $id): bool {
    $existing = get_post_by_id($id);
    if (!$existing) return false;

    if (DB::isConnected()) {
        db_exec("DELETE FROM `posts` WHERE `id` = ?", [$id]);
    }

    $posts = read_json_file(POSTS_FILE, []);
    $filtered = array_filter($posts, fn($p) => $p['id'] !== $id);
    write_json_file(POSTS_FILE, array_values($filtered));

    audit_log('post.deleted', 'post', $id, $existing, null);
    return true;
}

function estimate_read_time(string $content): string {
    $words = str_word_count(strip_tags($content));
    $minutes = max(1, ceil($words / 200));
    return $minutes . ' min read';
}

function parse_tags($tagsString): array {
    if (is_array($tagsString)) return $tagsString;
    $tags = array_map('trim', explode(',', (string)$tagsString));
    return array_values(array_filter($tags));
}

function format_date(string $datetimeStr): string {
    $ts = strtotime($datetimeStr);
    return $ts ? date('M j, Y', $ts) : $datetimeStr;
}

/* ==========================================================================
   SERVICES OPERATIONS
   ========================================================================== */

function get_all_services(bool $publishedOnly = true): array {
    if (DB::isConnected()) {
        $sql = "SELECT * FROM `services`";
        if ($publishedOnly) {
            $sql .= " WHERE `is_published` = 1";
        }
        $sql .= " ORDER BY `display_order` ASC, `name` ASC";
        $services = db_all($sql);
        if (!empty($services)) {
            foreach ($services as &$s) {
                $s['features'] = !empty($s['features_json']) ? json_decode($s['features_json'], true) : [];
                $s['process'] = !empty($s['process_json']) ? json_decode($s['process_json'], true) : [];
                $s['faqs'] = !empty($s['faqs_json']) ? json_decode($s['faqs_json'], true) : [];
            }
            return $services;
        }
    }
    return read_json_file(SERVICES_FILE, []);
}

function get_service_by_slug(string $slug): ?array {
    $aliasMap = [
        'demolition-hacking'              => 'hacking-demolition',
        'commercial-office-reinstatement' => 'reinstatement',
        'flooring-cement-screed'          => 'tiling-works',
        'false-ceiling-drywall-partition' => 'ceiling-partition',
        'painting-plastering'             => 'painting-works',
        'plumbing-sanitary'               => 'plumbing',
        'electrical-lighting'             => 'electrical',
        'waterproofing-pu-injection'      => 'waterproofing',
        'plastering'                      => 'painting-works',
        'metal-fabrication-grilles'       => 'renovation-contractor-singapore',
        'home-extensions-alterations'     => 'renovation-contractor-singapore'
    ];
    $targetSlug = $aliasMap[$slug] ?? $slug;

    if (DB::isConnected()) {
        $service = db_one("SELECT * FROM `services` WHERE (`slug` = ? OR `id` = ?) AND `is_published` = 1 LIMIT 1", [$targetSlug, $targetSlug]);
        if ($service) {
            $service['features'] = !empty($service['features_json']) ? json_decode($service['features_json'], true) : [];
            $service['process'] = !empty($service['process_json']) ? json_decode($service['process_json'], true) : [];
            $service['faqs'] = !empty($service['faqs_json']) ? json_decode($service['faqs_json'], true) : [];
            return $service;
        }
    }

    $services = read_json_file(SERVICES_FILE, []);
    foreach ($services as $s) {
        if (isset($s['slug']) && ($s['slug'] === $targetSlug || $s['id'] === $targetSlug)) {
            return $s;
        }
    }
    return null;
}

/* ==========================================================================
   USER MANAGEMENT OPERATIONS
   ========================================================================== */

function get_all_users(): array {
    if (DB::isConnected()) {
        return db_all(
            "SELECT u.id, u.name, u.username, u.email, r.slug AS role, r.name AS role_name, 
                    u.status, u.created_at, u.last_login_at AS last_login 
             FROM `users` u 
             JOIN `roles` r ON u.role_id = r.id 
             WHERE u.deleted_at IS NULL 
             ORDER BY u.id ASC"
        );
    }
    return array_map(function($u) {
        return [
            'id'         => $u['id'],
            'name'       => $u['name'],
            'username'   => $u['username'],
            'email'      => $u['email'],
            'role'       => $u['role'],
            'status'     => $u['status'] ?? 'active',
            'created_at' => $u['created_at'],
            'last_login' => $u['last_login'] ?? null
        ];
    }, read_json_file(USERS_FILE, []));
}

function get_user_by_id($id): ?array {
    if (DB::isConnected()) {
        return db_one(
            "SELECT u.*, r.slug AS role, r.name AS role_name 
             FROM `users` u 
             JOIN `roles` r ON u.role_id = r.id 
             WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1",
            [$id]
        );
    }
    foreach (read_json_file(USERS_FILE, []) as $u) {
        if ($u['id'] == $id) return $u;
    }
    return null;
}

function save_user(array $data): array {
    $now = date('Y-m-d H:i:s');
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

    if (empty($data['id'])) {
        // Create user
        $username = strtolower(trim($data['username']));
        $email = strtolower(trim($data['email'] ?? ($username . '@tabeebgroup.com')));

        if (DB::isConnected()) {
            $exists = db_val("SELECT 1 FROM `users` WHERE (LOWER(username) = ? OR LOWER(email) = ?) AND deleted_at IS NULL", [$username, $email]);
            if ($exists) {
                return ['success' => false, 'message' => 'Username or Email is already registered.'];
            }

            $roleSlug = $data['role'] ?? 'editor';
            $roleId = (int)db_val("SELECT `id` FROM `roles` WHERE `slug` = ? LIMIT 1", [$roleSlug]) ?: 2;
            $passHash = password_hash($data['password'], $algo);

            db_exec(
                "INSERT INTO `users` (`name`, `username`, `email`, `password_hash`, `role_id`, `status`, `created_at`) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [trim($data['name']), $username, $email, $passHash, $roleId, $data['status'] ?? 'active', $now]
            );
            $newId = db()->lastInsertId();
            audit_log('user.created', 'user', (string)$newId, null, ['username' => $username, 'role' => $roleSlug]);
            return ['success' => true, 'user_id' => $newId];
        }
    } else {
        // Edit user
        $id = $data['id'];
        $existing = get_user_by_id($id);
        if (!$existing) return ['success' => false, 'message' => 'User not found.'];

        if (DB::isConnected()) {
            $roleSlug = $data['role'] ?? $existing['role'];
            $roleId = (int)db_val("SELECT `id` FROM `roles` WHERE `slug` = ? LIMIT 1", [$roleSlug]) ?: $existing['role_id'];

            if (!empty($data['password'])) {
                $passHash = password_hash($data['password'], $algo);
                db_exec(
                    "UPDATE `users` SET `name` = ?, `email` = ?, `role_id` = ?, `status` = ?, `password_hash` = ?, `updated_at` = ? WHERE `id` = ?",
                    [trim($data['name']), strtolower(trim($data['email'])), $roleId, $data['status'] ?? 'active', $passHash, $now, $id]
                );
            } else {
                db_exec(
                    "UPDATE `users` SET `name` = ?, `email` = ?, `role_id` = ?, `status` = ?, `updated_at` = ? WHERE `id` = ?",
                    [trim($data['name']), strtolower(trim($data['email'])), $roleId, $data['status'] ?? 'active', $now, $id]
                );
            }
            audit_log('user.updated', 'user', (string)$id, $existing, ['name' => $data['name']]);
            return ['success' => true];
        }
    }

    return ['success' => false, 'message' => 'Database operation unavailable.'];
}

function delete_user($id): array {
    $current = current_user();
    if ($current && $current['id'] == $id) {
        return ['success' => false, 'message' => 'You cannot delete your own account while logged in.'];
    }

    if (!can_deactivate_or_delete_user((int)$id)) {
        return ['success' => false, 'message' => 'Cannot delete the final active Super Administrator.'];
    }

    if (DB::isConnected()) {
        db_exec("UPDATE `users` SET `deleted_at` = NOW(), `status` = 'inactive' WHERE `id` = ?", [$id]);
        audit_log('user.deleted', 'user', (string)$id, null, null);
        return ['success' => true];
    }

    return ['success' => false, 'message' => 'Database operation unavailable.'];
}

// Global CSRF helper wrappers
function csrf_token(): string {
    return get_csrf_token();
}

function verify_csrf(?string $token): bool {
    return verify_csrf_token($token);
}
