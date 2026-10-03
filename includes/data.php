<?php
/**
 * Data Storage & Authentication Helper - Tabeeb Contractor
 * Provides zero-config, portable data persistence and admin authentication
 */

if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie parameters if HTTPS is active
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 days
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

define('DATA_DIR', dirname(__DIR__) . '/data');
define('POSTS_FILE', DATA_DIR . '/posts.json');
define('USERS_FILE', DATA_DIR . '/users.json');
define('SERVICES_FILE', DATA_DIR . '/services.json');

/**
 * Determine dynamic base URL for absolute canonical and Open Graph URLs
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'tabeebgroup.com';
    
    // Auto normalize if port is standard
    return rtrim($protocol . $host, '/');
}

/**
 * Clean slug generator for SEO URLs
 */
function slugify($text) {
    // replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // transliterate
    if (function_exists('iconv')) {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    }
    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // trim
    $text = trim($text, '-');
    // remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // lowercase
    $text = strtolower($text);

    return empty($text) ? 'item-' . time() : $text;
}

/**
 * Safe JSON file reader
 */
function read_json_file($file, $default = []) {
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

/**
 * Safe atomic JSON file writer with file locking
 */
function write_json_file($file, $data) {
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

function get_all_posts($only_published = false) {
    $posts = read_json_file(POSTS_FILE, []);
    
    // Sort by created_at DESC
    usort($posts, function($a, $b) {
        return strtotime($b['created_at'] ?? 'now') - strtotime($a['created_at'] ?? 'now');
    });

    if ($only_published) {
        $posts = array_filter($posts, function($p) {
            return isset($p['status']) && $p['status'] === 'published';
        });
    }

    return array_values($posts);
}

function get_post_by_slug($slug) {
    $posts = read_json_file(POSTS_FILE, []);
    foreach ($posts as $post) {
        if (isset($post['slug']) && $post['slug'] === $slug) {
            return $post;
        }
    }
    return null;
}

function get_post_by_id($id) {
    $posts = read_json_file(POSTS_FILE, []);
    foreach ($posts as $post) {
        if (isset($post['id']) && $post['id'] === $id) {
            return $post;
        }
    }
    return null;
}

function save_post($data) {
    $posts = read_json_file(POSTS_FILE, []);
    $now = date('Y-m-d H:i:s');
    
    if (empty($data['id'])) {
        // Create new post
        $id = 'post_' . time() . '_' . rand(100, 999);
        $slug = !empty($data['slug']) ? slugify($data['slug']) : slugify($data['title'] ?? 'post');
        
        // Ensure slug is unique
        $baseSlug = $slug;
        $counter = 1;
        while (get_post_by_slug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $newPost = [
            'id' => $id,
            'slug' => $slug,
            'title' => trim($data['title'] ?? 'Untitled Post'),
            'category' => trim($data['category'] ?? 'General'),
            'author' => trim($data['author'] ?? 'Tabeeb Contractor'),
            'created_at' => $now,
            'updated_at' => $now,
            'status' => in_array($data['status'] ?? '', ['published', 'draft']) ? $data['status'] : 'draft',
            'read_time' => !empty($data['read_time']) ? trim($data['read_time']) : estimate_read_time($data['content'] ?? ''),
            'image' => trim($data['image'] ?? 'images/page-header-bg.jpg'),
            'excerpt' => trim($data['excerpt'] ?? ''),
            'tags' => is_array($data['tags'] ?? null) ? $data['tags'] : parse_tags($data['tags'] ?? ''),
            'content' => $data['content'] ?? ''
        ];

        array_unshift($posts, $newPost);
        write_json_file(POSTS_FILE, $posts);
        return $newPost;
    } else {
        // Update existing post
        $updated = null;
        foreach ($posts as &$post) {
            if ($post['id'] === $data['id']) {
                $targetSlug = !empty($data['slug']) ? slugify($data['slug']) : slugify($data['title']);
                
                // If slug changed, ensure new slug is unique
                if ($targetSlug !== $post['slug']) {
                    $baseSlug = $targetSlug;
                    $counter = 1;
                    $existing = get_post_by_slug($targetSlug);
                    while ($existing && $existing['id'] !== $data['id']) {
                        $targetSlug = $baseSlug . '-' . $counter;
                        $existing = get_post_by_slug($targetSlug);
                        $counter++;
                    }
                }

                $post['slug'] = $targetSlug;
                $post['title'] = trim($data['title']);
                $post['category'] = trim($data['category'] ?? 'General');
                if (!empty($data['author'])) $post['author'] = trim($data['author']);
                $post['updated_at'] = $now;
                if (isset($data['status'])) $post['status'] = $data['status'];
                $post['read_time'] = !empty($data['read_time']) ? trim($data['read_time']) : estimate_read_time($data['content'] ?? '');
                if (!empty($data['image'])) $post['image'] = trim($data['image']);
                $post['excerpt'] = trim($data['excerpt'] ?? '');
                $post['tags'] = is_array($data['tags'] ?? null) ? $data['tags'] : parse_tags($data['tags'] ?? '');
                $post['content'] = $data['content'] ?? '';
                
                $updated = $post;
                break;
            }
        }
        unset($post);

        if ($updated) {
            write_json_file(POSTS_FILE, $posts);
        }
        return $updated;
    }
}

function delete_post($id) {
    $posts = read_json_file(POSTS_FILE, []);
    $filtered = array_filter($posts, function($p) use ($id) {
        return $p['id'] !== $id;
    });
    if (count($filtered) !== count($posts)) {
        write_json_file(POSTS_FILE, array_values($filtered));
        return true;
    }
    return false;
}

function estimate_read_time($content) {
    $words = str_word_count(strip_tags($content));
    $minutes = max(1, ceil($words / 200));
    return $minutes . ' min read';
}

function parse_tags($tagsString) {
    if (is_array($tagsString)) return $tagsString;
    $tags = array_map('trim', explode(',', (string)$tagsString));
    return array_values(array_filter($tags));
}

/* ==========================================================================
   SERVICES OPERATIONS
   ========================================================================== */

function get_all_services() {
    return read_json_file(SERVICES_FILE, []);
}

function get_service_by_slug($slug) {
    $services = read_json_file(SERVICES_FILE, []);
    foreach ($services as $service) {
        if (isset($service['slug']) && ($service['slug'] === $slug || $service['id'] === $slug)) {
            return $service;
        }
    }
    return null;
}

/* ==========================================================================
   USER MANAGEMENT OPERATIONS
   ========================================================================== */

function get_all_users() {
    $users = read_json_file(USERS_FILE, []);
    // Don't leak raw hashes in listings
    return array_map(function($u) {
        return [
            'id' => $u['id'],
            'name' => $u['name'],
            'username' => $u['username'],
            'email' => $u['email'],
            'role' => $u['role'],
            'status' => $u['status'] ?? 'active',
            'created_at' => $u['created_at'],
            'last_login' => $u['last_login'] ?? null
        ];
    }, $users);
}

function get_user_by_id($id) {
    $users = read_json_file(USERS_FILE, []);
    foreach ($users as $u) {
        if ($u['id'] === $id) {
            return $u;
        }
    }
    return null;
}

function get_user_by_username($username) {
    $users = read_json_file(USERS_FILE, []);
    $uTarget = strtolower(trim($username));
    foreach ($users as $u) {
        if (strtolower($u['username']) === $uTarget || strtolower($u['email']) === $uTarget) {
            return $u;
        }
    }
    return null;
}

function save_user($data) {
    $users = read_json_file(USERS_FILE, []);
    $now = date('Y-m-d H:i:s');
    
    if (empty($data['id'])) {
        // Create new user
        $username = strtolower(trim($data['username']));
        
        // Check uniqueness
        if (get_user_by_username($username)) {
            return ['success' => false, 'message' => 'Username or Email is already registered.'];
        }

        $id = 'usr_' . time() . '_' . rand(100, 999);
        $hashed = password_hash($data['password'], PASSWORD_DEFAULT);

        $newUser = [
            'id' => $id,
            'name' => trim($data['name']),
            'username' => $username,
            'email' => strtolower(trim($data['email'] ?? ($username . '@tabeebgroup.com'))),
            'password' => $hashed,
            'role' => in_array($data['role'] ?? '', ['super_admin', 'editor']) ? $data['role'] : 'editor',
            'status' => in_array($data['status'] ?? '', ['active', 'inactive']) ? $data['status'] : 'active',
            'created_at' => $now,
            'last_login' => null
        ];

        $users[] = $newUser;
        write_json_file(USERS_FILE, $users);
        return ['success' => true, 'user' => $newUser];
    } else {
        // Edit user
        $updated = null;
        foreach ($users as &$u) {
            if ($u['id'] === $data['id']) {
                $u['name'] = trim($data['name']);
                if (!empty($data['email'])) {
                    $u['email'] = strtolower(trim($data['email']));
                }
                if (!empty($data['role']) && in_array($data['role'], ['super_admin', 'editor'])) {
                    $u['role'] = $data['role'];
                }
                if (!empty($data['status']) && in_array($data['status'], ['active', 'inactive'])) {
                    $u['status'] = $data['status'];
                }
                // Optional password change
                if (!empty($data['password'])) {
                    $u['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                $updated = $u;
                break;
            }
        }
        unset($u);

        if ($updated) {
            write_json_file(USERS_FILE, $users);
            return ['success' => true, 'user' => $updated];
        }
        return ['success' => false, 'message' => 'User not found.'];
    }
}

function delete_user($id) {
    $current = current_user();
    if ($current && $current['id'] === $id) {
        return ['success' => false, 'message' => 'You cannot delete your own account while logged in.'];
    }

    $users = read_json_file(USERS_FILE, []);
    $filtered = array_filter($users, function($u) use ($id) {
        return $u['id'] !== $id;
    });

    if (count($filtered) === 0) {
        return ['success' => false, 'message' => 'Cannot delete the final administrator account.'];
    }

    if (count($filtered) !== count($users)) {
        write_json_file(USERS_FILE, array_values($filtered));
        return ['success' => true];
    }

    return ['success' => false, 'message' => 'User not found.'];
}

function verify_user_credentials($username, $password) {
    $user = get_user_by_username($username);
    if (!$user) {
        return false;
    }

    if (isset($user['status']) && $user['status'] === 'inactive') {
        return false;
    }

    if (password_verify($password, $user['password'])) {
        // Update last login
        $users = read_json_file(USERS_FILE, []);
        foreach ($users as &$u) {
            if ($u['id'] === $user['id']) {
                $u['last_login'] = date('Y-m-d H:i:s');
                break;
            }
        }
        write_json_file(USERS_FILE, $users);
        
        return $user;
    }

    return false;
}

/* ==========================================================================
   SESSION & AUTH CHECKERS
   ========================================================================== */

function is_logged_in() {
    return !empty($_SESSION['tabeeb_admin_user']);
}

function current_user() {
    return $_SESSION['tabeeb_admin_user'] ?? null;
}

function is_super_admin() {
    $user = current_user();
    return $user && ($user['role'] ?? '') === 'super_admin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_super_admin() {
    require_login();
    if (!is_super_admin()) {
        header('Location: index.php?error=unauthorized');
        exit;
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token) {
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function format_date($datetimeStr) {
    $timestamp = strtotime($datetimeStr);
    return date('M j, Y', $timestamp);
}
