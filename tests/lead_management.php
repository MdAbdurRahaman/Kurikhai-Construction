<?php
if (getenv('APP_ENV') !== 'testing' || !str_contains((string)getenv('TEST_SQLITE_PATH'), 'tabeeb-test-')) exit(2);
require dirname(__DIR__) . '/includes/data.php';
require dirname(__DIR__) . '/includes/lead_management.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/admin/leads.php';
$checks = 0;
function check($ok, $message) { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function denied(callable $call) { try { $call(); } catch (RuntimeException | InvalidArgumentException $e) { return true; } return false; }
function actor(string $role) {
    $r = db_one('SELECT id, name FROM roles WHERE slug=?', [$role]);
    $_SESSION['tabeeb_admin_user'] = ['id'=>99,'name'=>'Fixture','role_id'=>(int)$r['id'],'role'=>$role,'role_name'=>$r['name']];
}
db_exec("INSERT INTO users(id,role_id,name,username,email,password_hash,status) VALUES (99,1,'Fixture','bulkfixture','bulk@example.invalid','fixture','active')");
foreach ([['OWN',99],['OTHER',null]] as [$number,$assignee]) db_exec('INSERT INTO leads(lead_number,name,phone,service_requested,project_description,consent_timestamp,submission_timestamp,assigned_user_id) VALUES (?,?,?,?,?,?,?,?)',[$number,$number,'81234567','Tiling','Test',date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$assignee]);
$own=(int)db_val("SELECT id FROM leads WHERE lead_number='OWN'");
$other=(int)db_val("SELECT id FROM leads WHERE lead_number='OTHER'");
$mode = $argv[1] ?? 'unit';
if ($mode === 'trash-denied') {
    actor('sales_rep'); $_GET = ['trash' => '1'];
    ob_start();
    register_shutdown_function(function () { $html=ob_get_clean(); check(http_response_code() === 403, 'Staff directly accessed recycle bin'); check(!str_contains($html, 'OTHER'), 'Recycle data leaked'); echo "Recycle route access denied correctly\n"; });
    require dirname(__DIR__).'/admin/leads.php'; exit(1);
}
if ($mode === 'csrf') {
    actor('super_admin'); $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['ids'=>[$own], 'bulk_action'=>'trash', 'csrf_token'=>'invalid'];
    db()->sqliteCreateFunction('CONCAT', fn(...$parts)=>implode('', $parts));
    ob_start(); require dirname(__DIR__).'/admin/leads.php'; $html=ob_get_clean();
    check(http_response_code() === 403 && str_contains($html,'Invalid security token'), 'Invalid CSRF request accepted');
    check(db_val('SELECT deleted_at FROM leads WHERE id=?',[$own]) === null,'CSRF changed data');
    echo "CSRF rejected without mutation\n"; exit;
}
actor('sales_rep');
check(denied(fn()=>lead_bulk_action([$other],'status','won',false)), 'Assigned-only user edited someone else lead');
check(denied(fn()=>lead_bulk_action([$own,$other],'status','won',false)), 'Mixed selection was not rejected');
check(db_val('SELECT status FROM leads WHERE id=?',[$own])==='new','Mixed selection partially changed data');
check(lead_bulk_action([$own],'status','contacted',false)===1,'Assigned edit failed');
check(denied(fn()=>lead_bulk_action([$own],'restore','',true)),'Staff accessed recycle bin');
check(denied(fn()=>lead_bulk_action([$own],'assign','0',false)),'Staff bypassed assign permission');
check(denied(fn()=>lead_bulk_action([$own],'trash','',false)),'Staff bypassed delete permission');
actor('super_admin');
check(lead_bulk_action([$own,$other],'priority','high',false)===2,'Bulk priority failed');
check(lead_bulk_action([$own],'trash','',false)===1,'Recycle failed');
check((bool)db_val('SELECT deleted_at FROM leads WHERE id=?',[$own]),'Recycled row was not retained');
check(denied(fn()=>lead_bulk_action([$own],'purge','',false)),'Purge bypassed recycle state');
check(lead_bulk_action([$own],'restore','',true)===1,'Restore failed');
check(db_val('SELECT deleted_at FROM leads WHERE id=?',[$own])===null,'Restore did not clear deletion');
lead_bulk_action([$own],'trash','',false);
check(lead_bulk_action([$own],'purge','',true)===1,'Permanent deletion failed');
check(!(bool)db_val('SELECT id FROM leads WHERE id=?',[$own]),'Purged row remains');
check((bool)db_val('SELECT id FROM leads WHERE id=?',[$other]),'Unselected lead was removed');
check(denied(fn()=>lead_bulk_action([$other],'status',"invalid",false)),'Invalid status accepted');
check(denied(fn()=>lead_bulk_action(['1bad'],'trash','',false)), 'Malformed ID accepted');
// Render a filtered page to exercise filters and the export join without exposing recycled leads.
db()->sqliteCreateFunction('CONCAT',fn(...$parts)=>implode('',$parts));
$_GET=['col'=>['customer'=>'OTHER','assignee'=>'Unassigned'],'values'=>['priority'=>['high','urgent']],'sort'=>'customer','direction'=>'asc'];
ob_start();require dirname(__DIR__).'/admin/leads.php';$html=ob_get_clean();
check(str_contains($html,'OTHER') && !str_contains($html,'>OWN<'),'Column filters failed');
check(str_contains($html,'Lead Recycle Bin'),'Super admin recycle navigation missing');
$leadHtml = $html;
actor('sales_rep');
$_GET=[];
ob_start();require dirname(__DIR__).'/admin/header.php';$html=ob_get_clean();
check(!str_contains($html,'Lead Recycle Bin'),'Recycle bin visible to staff');
echo json_encode(['passed'=>$checks,'suite'=>'Lead bulk operations and column filters'])."\n";
