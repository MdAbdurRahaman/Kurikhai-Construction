<?php
if(getenv('APP_ENV')!=='testing' || (!str_contains((string)getenv('TEST_SQLITE_PATH'),'tabeeb-test-') && !str_starts_with((string)getenv('DB_NAME'),'tabeeb_test_')))exit(2);
$root=dirname(__DIR__);if(is_dir($root.'/application'))$root.='/application';
require $root.'/includes/measurement.php';
$n=0;function measure_check($ok,$reason){global$n;if(!$ok)throw new RuntimeException($reason);$n++;}
$p=['event_id'=>str_repeat('a',32),'session_id'=>str_repeat('b',32),'visitor_id'=>str_repeat('c',32),'event_name'=>'page_view','page_path'=>'/contact?email=private@example.invalid','channel'=>'email','device_type'=>'mobile','source'=>'mail','campaign'=>'october-renovation','name'=>'Private Customer','phone'=>'81234567'];
$event=normalize_analytics_event($p);measure_check($event['page_path']==='/contact' && !isset($event['name']) && !isset($event['phone']),'PII reached storage payload');
measure_check($event['session_hash']!==$p['session_id'] && strlen($event['session_hash'])===64,'Raw identifier stored');
store_analytics_event($event);store_analytics_event($event);measure_check((int)db_val('SELECT COUNT(*) FROM site_measurement_events WHERE event_id=?',[$p['event_id']])===1,'Replay duplicated event');
measure_check(analytics_label('private@example.invalid')==='' && analytics_label('81234567')==='','PII label accepted');
foreach(['/admin/users','/api/leads','/data/private','/contact/81234567'] as $path){try{analytics_public_path($path);throw new RuntimeException('Sensitive path accepted');}catch(InvalidArgumentException $e){$n++;}}
$v=$p;$v['event_name']='web_vital';$v['detail']='lcp';$v['metric_value']=2000;$v['event_id']=str_repeat('d',32);store_analytics_event(normalize_analytics_event($v));$v['metric_value']=2500;store_analytics_event(normalize_analytics_event($v));measure_check((float)db_val('SELECT metric_value FROM site_measurement_events WHERE event_id=?',[$v['event_id']])===2500.0,'Final Web Vital update not stored');
$v['metric_value']=-1;try{normalize_analytics_event($v);throw new RuntimeException('Negative performance metric accepted');}catch(InvalidArgumentException $e){$n++;}
measure_check(analytics_percentile([1,2,3,4])===3.0 && analytics_percentile([])===null,'P75 calculation failed');
$expired=$event;$expired['event_id']=str_repeat('e',32);store_analytics_event($expired);db_exec('UPDATE site_measurement_events SET created_at=? WHERE event_id=?',[date('Y-m-d H:i:s',time()-91*86400),$expired['event_id']]);purge_site_measurements();measure_check(!db_val('SELECT 1 FROM site_measurement_events WHERE event_id=?',[$expired['event_id']]),'Expired raw measurement not purged');
require $root.'/includes/data.php';$_SESSION['tabeeb_admin_user']=['id'=>1,'role'=>'super_admin','role_id'=>1,'name'=>'Fixture'];$_SERVER['REQUEST_METHOD']='GET';$_SERVER['PHP_SELF']='/admin/analytics.php';
ob_start();require $root.'/admin/analytics.php';$html=ob_get_clean();measure_check(str_contains($html,'Not connected') && str_contains($html,'Popular pages') && !str_contains($html,'private@example.invalid'),'Analytics page evidence/privacy failed');
echo json_encode(['passed'=>$n,'suite'=>'Measurement validation, replay safety, Web Vitals and real analytics template'])."\n";
