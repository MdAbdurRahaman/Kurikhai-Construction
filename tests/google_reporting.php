<?php
if(getenv('APP_ENV')!=='testing' || (!str_contains((string)getenv('TEST_SQLITE_PATH'),'tabeeb-test-') && !str_starts_with((string)getenv('DB_NAME'),'tabeeb_test_')))exit(2);
$root=dirname(__DIR__);if(is_dir($root.'/application'))$root.='/application';require $root.'/includes/google_reports.php';
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);openssl_pkey_export($key,$pem);
$file=tempnam(sys_get_temp_dir(),'tabeeb-test-google-');chmod($file,0600);file_put_contents($file,json_encode(['type'=>'service_account','client_email'=>'fixture@example.invalid','private_key'=>$pem]));
putenv('GA4_PROPERTY_ID=123456789');putenv('GA4_SERVICE_ACCOUNT_FILE='.$file);
$calls=0;$GLOBALS['google_report_test_transport']=function($url,$payload,$token,$form)use(&$calls){
 $calls++;
 if($calls===1){if($url!=='https://oauth2.googleapis.com/token' || !$form || !isset($payload['assertion']))throw new RuntimeException('Invalid OAuth transport');return ['access_token'=>'controlled-fixture-token'];}
 if($url!=='https://analyticsdata.googleapis.com/v1beta/properties/123456789:batchRunReports' || $token!=='controlled-fixture-token' || count($payload['requests'])!==5)throw new RuntimeException('Invalid report batch');
 return ['reports'=>array_fill(0,5,['metricHeaders'=>[['name'=>'sessions']],'rows'=>[['metricValues'=>[['value'=>'12']]]]])];
};
try{
 $reports=ga4_fetch_reports('2026-10-01','2026-10-14');if($calls!==2 || count($reports['reports'])!==5)throw new RuntimeException('Batch evidence missing');
 $cache=ga4_cached_reports('2026-10-01','2026-10-14');if(!$cache || $cache['reports']['summary']['rows'][0]['metricValues'][0]['value']!=='12')throw new RuntimeException('Authenticated report cache failed');
 $GLOBALS['google_report_test_transport']=fn()=>throw new RuntimeException('Controlled provider failure');
 try{ga4_fetch_reports('2026-10-01','2026-10-14');throw new LogicException('Provider error was swallowed');}catch(RuntimeException $expected){}
 if(ga4_cached_reports('2026-10-01','2026-10-14')!==$cache)throw new RuntimeException('Failed provider overwrote existing report evidence');
 echo json_encode(['passed'=>4,'suite'=>'Signed OAuth, five-report batch, cache and provider failure; controlled responses, no Google requests'])."\n";
}finally{unlink($file);putenv('GA4_SERVICE_ACCOUNT_FILE');putenv('GA4_PROPERTY_ID');unset($GLOBALS['google_report_test_transport']);}
