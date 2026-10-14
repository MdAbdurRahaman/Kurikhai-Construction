<?php
if (getenv('APP_ENV')!=='testing' || (!str_contains((string)getenv('TEST_SQLITE_PATH'),'tabeeb-test-') && !str_starts_with((string)getenv('DB_NAME'),'tabeeb_test_'))) exit(2);
$root=dirname(__DIR__);if(is_dir($root.'/application'))$root.='/application';
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['PHP_SELF']='/admin/access-home.php';
require $root.'/includes/data.php';
$n=0;function access_check($ok,$reason){global $n;if(!$ok)throw new RuntimeException($reason);$n++;}
// Native CI is disposable but contains other fixture administrators. Isolate this test's names.
$username='access_fixture_'.bin2hex(random_bytes(5));
$superId=(int)db_val('SELECT id FROM roles WHERE slug=?',['super_admin']);
db_exec('INSERT INTO users(role_id,name,username,email,password_hash,status,session_version) VALUES (?,?,?,?,?,?,?)',[$superId,'Access owner',$username.'owner',$username.'owner@example.invalid','fixture','active',1]);
$owner=(int)db()->lastInsertId();
$_SESSION['tabeeb_admin_user']=['id'=>$owner,'role_id'=>$superId,'role'=>'super_admin','name'=>'Owner','session_version'=>1];
$data=['name'=>'Combined roles','username'=>$username,'email'=>$username.'@example.invalid','password'=>'Disposable-Password-123!','roles'=>['sales_rep','editor'],'portal_modules'=>['overview','leads','posts','write','share'],'permission_overrides'=>['leads.export'=>'allow','content.manage'=>'deny']];
$res=save_user($data);access_check($res['success'],'Creating multiple roles failed: '.($res['message']??''));$userId=$res['user_id'];
$settings=user_access_settings($userId);
access_check(count($settings['roles'])===2 && in_array('editor',$settings['roles'],true),'Multiple roles were not persisted');
$user=get_user_by_id($userId);
$login=function()use($userId){$u=get_user_by_id($userId);$_SESSION['tabeeb_admin_user']=['id'=>$userId,'name'=>$u['name'],'role'=>$u['role'],'role_id'=>(int)$u['role_id'],'session_version'=>(int)$u['session_version']];};
$login();
$rejected=save_user(['id'=>$userId,'roles'=>['super_admin'],'name'=>'Malicious self-edit','email'=>$username.'@example.invalid']);
access_check(!$rejected['success'] && !is_super_admin(),'Non-owner escalated their own roles');
access_check(has_permission('leads.view_assigned') && has_permission('leads.edit_assigned'),'Sales permissions lost');
access_check(has_permission('projects.manage'),'Editor role permissions were not combined');
access_check(!has_permission('content.manage'),'Explicit deny failed to override editor role');
access_check(has_permission('leads.export'),'Explicit allow ignored');
access_check(!portal_access('projects') && !portal_module_available('posts'),'Module and capability intersection failed');
access_check(!portal_module_available('users'),'Non-owner gained user administration');
$_SESSION['tabeeb_admin_user']=['id'=>$owner,'role_id'=>$superId,'role'=>'super_admin','name'=>'Owner'];
$data['id']=$userId;$data['permission_overrides']=['leads.export'=>'deny'];$data['portal_modules']=['overview','posts'];
$before=(int)$user['session_version'];$res=save_user($data);access_check($res['success'],'Access edit failed');
access_check((int)get_user_by_id($userId)['session_version']>$before,'Access edit did not revoke sessions');
$login();
access_check(has_permission('content.manage') && !has_permission('leads.export'),'Role inheritance or deny after edit failed');
access_check(!portal_access('leads') && portal_access('posts'),'Dashboard selection ignored');
$mode=$argv[1]??'unit';
if ($mode==='blocked') {
    $_SERVER['PHP_SELF']='/admin/leads.php';ob_start();
    register_shutdown_function(function(){ $html=ob_get_clean();if(http_response_code()!==403 || !str_contains($html,'disabled this dashboard section'))exit(1);echo json_encode(['passed'=>1,'suite'=>'Blocked direct page access'])."\n"; });
    require $root.'/admin/leads.php';exit(1);
}
if ($mode==='posts') {
    $_SERVER['PHP_SELF']='/admin/posts.php';ob_start();require $root.'/admin/posts.php';$html=ob_get_clean();
    access_check(!str_contains($html,'post-edit.php'),'Post listing exposed disabled editing links');
    echo json_encode(['passed'=>$n,'suite'=>'Disabled editing links in posts'])."\n";exit;
}
if ($mode==='dashboard') {
    $_SERVER['PHP_SELF']='/admin/index.php';ob_start();require $root.'/admin/index.php';$html=ob_get_clean();
    access_check(!str_contains($html,'href="leads.php"') && !str_contains($html,'href="services.php"') && !str_contains($html,'href="post-edit.php"'),'Disabled sections still exposed links');
    access_check(str_contains($html,'href="posts.php"'),'Enabled section link missing');
    access_check(!str_contains($html,'href="/blog"') && !str_contains($html,'href="/"'),'Disabled public shortcuts still exposed');
    echo json_encode(['passed'=>$n,'suite'=>'Dashboard access rendering'])."\n";exit;
}
$login();$oldVersion=(int)current_user()['session_version'];
$_SESSION['tabeeb_admin_user']=['id'=>$owner,'role_id'=>$superId,'role'=>'super_admin','name'=>'Owner'];
$invalid=$data;$invalid['roles']=['nonexistent'];$res=save_user($invalid);access_check(!$res['success'] && count(user_role_rows($userId))===2,'Invalid roles partially changed access');
$invalid=$data;$invalid['permission_overrides']=['unknown'=>'allow'];$res=save_user($invalid);access_check(!$res['success'],'Unknown capability accepted');
$data['roles']=['editor'];$data['portal_modules']=[];$res=save_user($data);access_check($res['success'],'Role removal failed');
$login();access_check(!has_permission('leads.view_assigned'),'Removed role retained permissions');
access_check(portal_landing_url()==='/admin/access-home.php','No-section account has no safe landing');
$_SESSION['tabeeb_admin_user']['session_version']=$oldVersion;access_check(!revalidate_active_session() && !is_logged_in(),'Revoked session survived access edit');
$_SESSION['tabeeb_admin_user']=['id'=>$owner,'role_id'=>$superId,'role'=>'super_admin','name'=>'Owner'];
// Existing sole owner must not be demoted by additional role selection.
$otherOwners=(int)db_val('SELECT COUNT(*) FROM users WHERE role_id=? AND status=? AND deleted_at IS NULL',[$superId,'active']);
if($otherOwners===1){$res=save_user(['id'=>$owner,'name'=>'Owner','email'=>$username.'owner@example.invalid','roles'=>['editor'],'status'=>'active']);access_check(!$res['success'],'Final owner was demoted');}
$data['roles']=['editor','super_admin'];$res=save_user($data);access_check($res['success'] && (int)get_user_by_id($userId)['role_id']===$superId,'Combined owner role not canonicalized');
$login();access_check(is_super_admin() && portal_access('users') && has_permission('leads.export'),'Owner was locked out by overrides');
echo json_encode(['passed'=>$n,'suite'=>'Multiple roles, overrides, session revocation and owner protection'])."\n";
