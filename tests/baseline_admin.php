<?php
// Exercise the actual baseline template with a disposable fixture and two roles.
if(getenv('APP_ENV')!=='testing'||!str_contains((string)getenv('TEST_SQLITE_PATH'),'tabeeb-test-'))exit(2);
$bundle=dirname(__DIR__);$root=is_dir($bundle.'/application')?$bundle.'/application':$bundle;
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['PHP_SELF']='/admin/baseline.php';
require $root.'/includes/data.php';require $root.'/includes/baseline.php';
$n=0;function verify_baseline($ok,$reason){global$n;if(!$ok)throw new RuntimeException($reason);$n++;}
verify_baseline(baseline_suggested_period()===['start'=>'','end'=>''],'Empty history invented a period');
$today=date('Y-m-d');$yesterday=date('Y-m-d',strtotime('-1 day'));$older=date('Y-m-d',strtotime('-7 days'));
$add=function($number,$date,$source='website_contact_page',$deleted=null){
    db_exec('INSERT INTO leads(lead_number,name,phone,service_requested,project_description,consent_timestamp,submission_timestamp,created_at,source,deleted_at) VALUES (?,?,?,?,?,?,?,?,?,?)',[$number,'Fixture','81234567','Tiling','Fixture only',$date,$date,$date,$source,$deleted]);
};
$add('baseline-today',$today.' 09:00:00');
verify_baseline(baseline_suggested_period()===['start'=>'','end'=>''],'Today-only history created a reversed or incomplete period');
$add('baseline-manual',$older.' 09:00:00','manual');$add('baseline-deleted',$older.' 09:00:00','website_contact_page',$today.' 09:00:00');
verify_baseline(baseline_suggested_period()===['start'=>'','end'=>''],'Manual or deleted history affected suggestions');
$add('baseline-old',$older.' 09:00:00');$add('baseline-yesterday',$yesterday.' 09:00:00');
verify_baseline(baseline_suggested_period()===['start'=>$older,'end'=>$yesterday],'Past evidence dates were not selected correctly');
if(($argv[1]??'')==='reader'){
    db_exec("INSERT INTO roles(id,name,slug) VALUES (77,'Baseline fixture reader','baseline_fixture_reader')");
    if(!DB::query("SELECT id FROM permissions WHERE slug='baseline.view'")->fetchColumn())db_exec("INSERT INTO permissions(name,slug,category) VALUES ('View baseline','baseline.view','Baseline')");
    db_exec("INSERT INTO role_permissions(role_id,permission_id) SELECT 77,id FROM permissions WHERE slug='baseline.view'");
    $_SESSION['tabeeb_admin_user']=['id'=>77,'name'=>'Fixture Reader','role_id'=>77,'role'=>'baseline_fixture_reader'];
    verify_baseline(has_permission('baseline.view')&&!has_permission('baseline.manage'),'Reader fixture permissions are incorrect');
}else{
    $_SESSION['tabeeb_admin_user']=['id'=>1,'name'=>'Fixture Editor','role_id'=>1,'role'=>'super_admin'];
}
ob_start();require $root.'/admin/baseline.php';$html=ob_get_clean();
verify_baseline(str_contains($html,'Marketing baseline'),'Baseline page failed to render');
$editing=str_contains($html,'name="action" value="capture"')&&str_contains($html,'name="action" value="update_metric"');
verify_baseline($editing===(($argv[1]??'')!=='reader'),'Editing form permission boundary failed');
if(($argv[1]??'')==='reader')verify_baseline(!str_contains($html,'name="window_start"'),'Reader received an editing control');
else verify_baseline(str_contains($html,'value="'.$older.'"')&&str_contains($html,'value="'.$yesterday.'"'),'Template did not render evidence dates');
echo json_encode(['passed'=>$n,'role'=>$argv[1]??'editor','suite'=>'actual baseline template and date suggestions'])."\n";
