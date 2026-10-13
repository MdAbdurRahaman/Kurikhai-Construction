<?php
if (getenv('APP_ENV') !== 'testing' || !str_contains((string)getenv('TEST_SQLITE_PATH'), 'tabeeb-test-')) exit(2);
$root = dirname(__DIR__);
if (is_dir($root.'/application')) $root .= '/application';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/admin/'.($argv[1] ?? 'index').'.php';
require $root.'/includes/data.php';
$role = $argv[2] ?? 'sales_rep';
$roleRow = db_one('SELECT id,name FROM roles WHERE slug=?', [$role]);
$_SESSION['tabeeb_admin_user'] = ['id'=>99,'name'=>'Sales Fixture','username'=>'fixture','role_id'=>(int)$roleRow['id'],'role'=>$role,'role_name'=>$roleRow['name']];
$add = function ($number,$assignee,$deleted=null) {
    db_exec('INSERT INTO leads(lead_number,name,phone,service_requested,project_description,consent_timestamp,submission_timestamp,assigned_user_id,deleted_at) VALUES (?,?,?,?,?,?,?,?,?)', [$number,$number,'81234567','Tiling','Fixture',date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$assignee,$deleted]);
};
db_exec("INSERT INTO users(id,role_id,name,username,email,password_hash,status) VALUES (99,4,'Sales Fixture','salesfixture','sales@example.invalid','fixture','active')");
$add('OWN-ENQUIRY',99);$add('OTHER-ENQUIRY',100);$add('UNASSIGNED-ENQUIRY',null);$add('DELETED-ENQUIRY',99,date('Y-m-d H:i:s'));
if (($argv[3]??'')==='deny') {
    if (($argv[1]??'')==='leads') $_GET['export']='csv';
    if (($argv[1]??'')==='lead-detail') $_GET['id']=(int)db_val("SELECT id FROM leads WHERE lead_number='OTHER-ENQUIRY'");
    ob_start();
    register_shutdown_function(function(){
        $html=ob_get_clean();
        if(http_response_code()!==403 || str_contains($html,'OTHER-ENQUIRY')) {fwrite(STDERR,'Permission boundary failed');exit(1);}
        echo json_encode(['passed'=>1,'suite'=>'Direct request denied'])."\n";
    });
    require $root.'/admin/'.($argv[1]??'leads').'.php';
    exit(1);
}
if (($argv[1]??'')==='lead-detail') $_GET['id'] = (int)db_val("SELECT id FROM leads WHERE lead_number='OWN-ENQUIRY'");
ob_start();require $root.'/admin/'.($argv[1]??'index').'.php';$html=ob_get_clean();
$n=0;
$check=function($ok,$message)use(&$n){if(!$ok)throw new RuntimeException($message);$n++;};
if ($role==='sales_rep') {
    $check(!str_contains($html,'href="post-edit.php"'),'Sales received blog editing link');
    $check(!str_contains($html,'href="business-info.php"'),'Sales received business editing link');
    $check(!str_contains($html,'href="services.php"'),'Sales received content editing link');
    $check(!str_contains($html,'export=csv'),'Sales received export link');
    $check(str_contains($html,'OWN-ENQUIRY')&&!str_contains($html,'OTHER-ENQUIRY')&&!str_contains($html,'UNASSIGNED-ENQUIRY')&&!str_contains($html,'DELETED-ENQUIRY'),'Sales lead scope failed');
    $check(!str_contains($html,'name="assigned_user_id"'),'Sales received assignment control');
    $check(str_contains($html,'margin-left: auto;">1</span>'),'Badge did not respect assignment and deletion');
} else {
    $check(str_contains($html,'href="post-edit.php"'),'Admin lost content action');
    $check(str_contains($html,'Sales Representative'),'Role name missing');
    if (($argv[1]??'')==='users') $check(!str_contains($html,'>Content Editor</span>'),'Hardcoded editor badge remains');
}
echo json_encode(['passed'=>$n,'page'=>$argv[1],'role'=>$role])."\n";
