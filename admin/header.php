<?php
require_once dirname(__DIR__) . '/includes/data.php';
require_login();

$currentUser = current_user();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$baseUrl = get_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin Dashboard' ?> | Tabeeb Contractor Admin</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="64x64" href="/images/favicon.png">

    <style>
        :root {
            --admin-navy-dark: #041933;
            --admin-navy: #07274d;
            --admin-navy-light: #0d386b;
            --admin-accent: #00b4d8;
            --admin-gold: #c2963f;
            --admin-bg: #f8fafc;
            --admin-surface: #ffffff;
            --admin-border: #e2e8f0;
            --admin-text: #0f172a;
            --admin-text-muted: #64748b;
            --admin-radius: 12px;
            --admin-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --admin-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.06), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--admin-bg);
            color: var(--admin-text);
            display: flex;
            min-height: 100vh;
            font-size: 14px;
        }

        /* Sidebar Styling */
        .admin-sidebar {
            width: 260px;
            background-color: var(--admin-navy);
            color: #fff;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            min-height: 100vh;
            position: sticky;
            top: 0;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            z-index: 100;
        }

        .sidebar-brand {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-brand img {
            max-height: 38px;
            width: auto;
        }

        .sidebar-brand-text {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 16px;
            color: #fff;
            line-height: 1.2;
        }

        .sidebar-brand-sub {
            font-size: 11px;
            color: var(--admin-accent);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .sidebar-nav {
            padding: 20px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex-grow: 1;
        }

        .nav-category {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 0.6px;
            padding: 12px 12px 6px;
            margin-top: 8px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .sidebar-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
        }

        .sidebar-link.active {
            color: #fff;
            background: var(--admin-navy-light);
            border-left: 3px solid var(--admin-accent);
            font-weight: 600;
        }

        .sidebar-icon {
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .sidebar-user {
            padding: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0, 0, 0, 0.15);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--admin-gold);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            line-height: 1.2;
        }

        .user-role {
            font-size: 11px;
            color: var(--admin-accent);
            text-transform: capitalize;
        }

        .logout-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: color 0.15s ease;
        }

        .logout-btn:hover {
            color: #ef4444;
        }

        /* Main Content Container */
        .admin-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow-x: hidden;
        }

        .admin-topbar {
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid var(--admin-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .topbar-title {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--admin-navy);
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-body {
            padding: 32px;
            flex-grow: 1;
        }

        /* Buttons & Badges */
        .btn-adm {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }

        .btn-adm-primary {
            background: var(--admin-navy);
            color: #fff;
        }
        .btn-adm-primary:hover {
            background: var(--admin-navy-light);
            color: #fff;
        }

        .btn-adm-accent {
            background: var(--admin-accent);
            color: #fff;
        }
        .btn-adm-accent:hover {
            background: #0096c7;
            color: #fff;
        }

        .btn-adm-gold {
            background: var(--admin-gold);
            color: #fff;
        }
        .btn-adm-gold:hover {
            background: #a67d2e;
            color: #fff;
        }

        .btn-adm-outline {
            background: #ffffff;
            color: #334155;
            border-color: var(--admin-border);
        }
        .btn-adm-outline:hover {
            background: #f1f5f9;
            color: var(--admin-navy);
        }

        .btn-adm-danger {
            background: #ef4444;
            color: #fff;
        }
        .btn-adm-danger:hover {
            background: #dc2626;
        }

        .badge-adm {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-adm-success {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-adm-warning {
            background: #fef3c7;
            color: #b45309;
        }

        .badge-adm-blue {
            background: #e0f2fe;
            color: #0369a1;
        }

        /* Stat Cards */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius);
            padding: 22px;
            box-shadow: var(--admin-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-val {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--admin-navy);
            line-height: 1.1;
        }

        .stat-label {
            font-size: 13px;
            color: var(--admin-text-muted);
            margin-top: 4px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        /* Table Card */
        .table-card {
            background: #ffffff;
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius);
            box-shadow: var(--admin-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--admin-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .table-header h3 {
            font-size: 17px;
            font-family: 'Outfit', sans-serif;
            color: var(--admin-navy);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .adm-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .adm-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            padding: 12px 20px;
            border-bottom: 1px solid var(--admin-border);
        }

        .adm-table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--admin-border);
            vertical-align: middle;
        }

        .adm-table tr:last-child td {
            border-bottom: none;
        }

        .adm-table tr:hover td {
            background: #fcfcfd;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
            color: #334155;
        }

        .form-control-adm {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            color: var(--admin-text);
            background: #fff;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .form-control-adm:focus {
            border-color: var(--admin-accent);
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.15);
        }

        /* Alert Toast */
        .toast-adm {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--admin-navy-dark);
            color: #fff;
            padding: 14px 22px;
            border-radius: 10px;
            box-shadow: var(--admin-shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 99999;
            font-size: 13px;
            font-weight: 500;
        }
        .toast-adm.show {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>

    <!-- Left Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <img src="/images/favicon.png" alt="Logo">
            <div>
                <div class="sidebar-brand-text">Tabeeb Portal</div>
                <div class="sidebar-brand-sub">Contractor Admin</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-category">Main Dashboard</div>
            <a href="index.php" class="sidebar-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                <span class="sidebar-icon">📊</span>
                <span>Overview & Analytics</span>
            </a>

            <div class="nav-category">Blog Management</div>
            <a href="posts.php" class="sidebar-link <?= ($currentPage === 'posts' || $currentPage === 'post-edit') ? 'active' : '' ?>">
                <span class="sidebar-icon">📝</span>
                <span>All Blog Posts</span>
            </a>
            <a href="post-edit.php" class="sidebar-link <?= ($currentPage === 'post-edit' && empty($_GET['id'])) ? 'active' : '' ?>">
                <span class="sidebar-icon">✍️</span>
                <span>Write New Post</span>
            </a>

            <div class="nav-category">Social Share Hub</div>
            <a href="services.php" class="sidebar-link <?= $currentPage === 'services' ? 'active' : '' ?>">
                <span class="sidebar-icon">🔗</span>
                <span>Service Share Links</span>
            </a>

            <div class="nav-category">Access Control</div>
            <a href="users.php" class="sidebar-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                <span class="sidebar-icon">👥</span>
                <span>User Management</span>
            </a>

            <div class="nav-category">Quick View</div>
            <a href="/blog" target="_blank" class="sidebar-link">
                <span class="sidebar-icon">🌐</span>
                <span>Live Blog ↗</span>
            </a>
            <a href="/" target="_blank" class="sidebar-link">
                <span class="sidebar-icon">🏠</span>
                <span>Main Website ↗</span>
            </a>
        </nav>

        <div class="sidebar-user">
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
                </div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($currentUser['name'] ?? 'Administrator') ?></div>
                    <div class="user-role"><?= htmlspecialchars($currentUser['role'] ?? 'Editor') ?></div>
                </div>
            </div>
            <a href="logout.php" class="logout-btn" title="Sign Out">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </a>
        </div>
    </aside>

    <!-- Main Container -->
    <main class="admin-main">
        <header class="admin-topbar">
            <h1 class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></h1>
            <div class="topbar-actions">
                <a href="post-edit.php" class="btn-adm btn-adm-primary">
                    <span>+ New Blog Post</span>
                </a>
                <a href="/blog" target="_blank" class="btn-adm btn-adm-outline">
                    <span>View Public Blog ↗</span>
                </a>
            </div>
        </header>

        <div class="admin-body">
