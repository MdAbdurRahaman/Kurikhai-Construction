<?php
/** Per-user roles, capability overrides and portal page access. */
function portal_modules(): array {
    return [
        'overview'=>['label'=>'Overview & Analytics','pages'=>['index.php'],'permission'=>null],
        'analytics'=>['label'=>'Traffic & Performance','pages'=>['analytics.php'],'permission'=>'analytics.view'],
        'leads'=>['label'=>'Leads & Inquiries','pages'=>['leads.php','lead-detail.php'],'permission'=>'leads.view_assigned'],
        'baseline'=>['label'=>'SEO Baseline','pages'=>['baseline.php'],'permission'=>'baseline.view'],
        'business'=>['label'=>'Business Info Tracker','pages'=>['business-info.php'],'permission'=>'business_info.manage'],
        'review'=>['label'=>'Content Factual Review','pages'=>['content-review.php'],'permission'=>'content.manage'],
        'projects'=>['label'=>'Projects & Media','pages'=>['projects.php','project-edit.php','project-media.php'],'permission'=>'projects.manage'],
        'posts'=>['label'=>'All Blog Posts','pages'=>['posts.php'],'permission'=>'content.manage'],
        'write'=>['label'=>'Write / Edit Blog Posts','pages'=>['post-edit.php'],'permission'=>'content.manage'],
        'services'=>['label'=>'Service Share Hub','pages'=>['services.php'],'permission'=>'content.manage'],
        'users'=>['label'=>'User Accounts (Super Admin only)','pages'=>['users.php'],'permission'=>'users.manage'],
        'notifications'=>['label'=>'Notification Routing','pages'=>['notifications.php'],'permission'=>'notifications.manage'],
        'settings'=>['label'=>'Website Settings','pages'=>['settings.php'],'permission'=>'settings.manage'],
        'audit'=>['label'=>'Security Audit Trail','pages'=>['audit-logs.php'],'permission'=>'audit.view'],
        'share'=>['label'=>'Dashboard Share Preview','pages'=>[],'permission'=>null],
        'public_blog'=>['label'=>'Live Blog shortcut','pages'=>[],'permission'=>null],
        'public_site'=>['label'=>'Main Website shortcut','pages'=>[],'permission'=>null],
    ];
}
function access_controls_ready(): bool {
    static $ready;
    if ($ready !== null) return $ready;
    if (!DB::isConnected()) return false;
    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        return $ready = (int)db_val("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('user_roles','user_permission_overrides','user_portal_access')") === 3;
    }
    return $ready = (int)db_val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('user_roles','user_permission_overrides','user_portal_access')") === 3;
}
function user_role_rows(int $userId): array {
    $sql = 'SELECT r.id,r.slug,r.name FROM roles r JOIN users u ON u.role_id=r.id WHERE u.id=?';
    if (access_controls_ready()) $sql .= ' UNION SELECT r.id,r.slug,r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?';
    return db_all($sql, access_controls_ready() ? [$userId,$userId] : [$userId]);
}
function portal_access(string $module): bool {
    if (!is_logged_in() || !isset(portal_modules()[$module])) return false;
    if (is_super_admin()) return true;
    if (!DB::isConnected()) return false;
    if (!access_controls_ready()) return true; // Legacy database before additive migration.
    $row = db_one('SELECT allowed FROM user_portal_access WHERE user_id=? AND module_slug=?', [(int)current_user()['id'],$module]);
    return !$row || (int)$row['allowed'] === 1;
}
function portal_module_available(string $module): bool {
    if (!portal_access($module)) return false;
    if ($module === 'users') return is_super_admin();
    if ($module === 'leads') return has_permission('leads.view') || has_permission('leads.view_assigned');
    $permission = portal_modules()[$module]['permission'];
    return !$permission || has_permission($permission);
}
function portal_landing_url(): string {
    foreach (portal_modules() as $slug=>$module) {
        if ($module['pages'] && portal_module_available($slug)) return '/admin/'.$module['pages'][0];
    }
    return '/admin/access-home.php';
}
function user_access_settings(int $userId): array {
    $roles = user_role_rows($userId);
    $modules = [];
    foreach (portal_modules() as $slug=>$module) $modules[$slug] = true;
    $permissions = [];
    if (access_controls_ready()) {
        foreach (db_all('SELECT module_slug,allowed FROM user_portal_access WHERE user_id=?',[$userId]) as $row) $modules[$row['module_slug']] = (int)$row['allowed'] === 1;
        foreach (db_all('SELECT p.slug,o.allowed FROM user_permission_overrides o JOIN permissions p ON p.id=o.permission_id WHERE o.user_id=?',[$userId]) as $row) $permissions[$row['slug']] = (int)$row['allowed'] === 1 ? 'allow' : 'deny';
    }
    return ['roles'=>array_column($roles,'slug'),'role_names'=>array_column($roles,'name'),'modules'=>$modules,'permissions'=>$permissions];
}
function normalize_user_access(array $data, ?array $existing): array {
    $slugs = $data['roles'] ?? [$data['role'] ?? ($existing['role'] ?? 'editor')];
    if (!is_array($slugs) || !$slugs || count($slugs)>6) throw new InvalidArgumentException('Select at least one valid role.');
    $roles = [];
    foreach (array_unique($slugs) as $slug) {
        if (!is_string($slug)) throw new InvalidArgumentException('Invalid role selection.');
        $row = db_one('SELECT id,slug FROM roles WHERE slug=?',[$slug]);
        if (!$row) throw new InvalidArgumentException('Unknown role selection.');
        $roles[$slug] = (int)$row['id'];
    }
    $primary = isset($roles['super_admin']) ? $roles['super_admin'] : ($roles[$existing['role'] ?? ''] ?? reset($roles));
    $overrides = $data['permission_overrides'] ?? [];
    if (!is_array($overrides)) throw new InvalidArgumentException('Invalid permissions.');
    foreach ($overrides as $slug=>$mode) {
        if (!in_array($mode,['inherit','allow','deny'],true) || !db_val('SELECT 1 FROM permissions WHERE slug=?',[$slug])) throw new InvalidArgumentException('Invalid permission selection.');
    }
    $modules = $data['portal_modules'] ?? null;
    if ($modules !== null && (!is_array($modules) || array_diff($modules,array_keys(portal_modules())))) throw new InvalidArgumentException('Invalid dashboard selection.');
    return ['primary'=>$primary,'roles'=>$roles,'modules'=>$modules,'permissions'=>$overrides];
}
function persist_user_access(int $userId, array $access): void {
    if (!access_controls_ready()) throw new RuntimeException('Apply the access-control database migration first.');
    db_exec('DELETE FROM user_roles WHERE user_id=?',[$userId]);
    foreach ($access['roles'] as $id) db_exec('INSERT INTO user_roles(user_id,role_id) VALUES (?,?)',[$userId,$id]);
    if ($access['modules'] !== null) {
        db_exec('DELETE FROM user_portal_access WHERE user_id=?',[$userId]);
        foreach (portal_modules() as $slug=>$module) db_exec('INSERT INTO user_portal_access(user_id,module_slug,allowed) VALUES (?,?,?)',[$userId,$slug,in_array($slug,$access['modules'],true)?1:0]);
    }
    db_exec('DELETE FROM user_permission_overrides WHERE user_id=?',[$userId]);
    foreach ($access['permissions'] as $slug=>$mode) if ($mode !== 'inherit') {
        $permissionId = (int)db_val('SELECT id FROM permissions WHERE slug=?',[$slug]);
        db_exec('INSERT INTO user_permission_overrides(user_id,permission_id,allowed) VALUES (?,?,?)',[$userId,$permissionId,$mode==='allow'?1:0]);
    }
}
