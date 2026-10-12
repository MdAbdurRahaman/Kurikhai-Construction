<?php
// Actual application functions with a disposable SQLite fixture; no provider access.
putenv('APP_ENV=testing');putenv('DB_NAME=tabeeb_test_contracts');putenv('HMAC_SECRET=fixture-only-random-material-32-characters');
$path=sys_get_temp_dir().'/tabeeb-test-'.bin2hex(random_bytes(8)).'.sqlite';putenv('TEST_SQLITE_PATH='.$path);
$root=dirname(__DIR__);$py=PHP_OS_FAMILY==='Windows'?'python':'python3';$cmd=$py.' '.escapeshellarg(__DIR__.'/fixture.py').' '.escapeshellarg($path);exec($cmd,$output,$code);if($code)throw new RuntimeException('Fixture initialization failed: '.implode("\n",$output));
require $root.'/includes/data.php';require $root.'/includes/lead_request.php';require $root.'/includes/baseline.php';require $root.'/includes/mailer.php';require $root.'/includes/tracking.php';require $root.'/includes/migration_repairs.php';
$n=0;function check($v,$message){global$n;if(!$v)throw new RuntimeException($message);$n++;}
try{
 $base=['name'=>'A Person','phone'=>'+65 8123 4567','email'=>'a@example.invalid','service_requested'=>'Tiling Works','property_type'=>'HDB Flat','estimated_budget'=>'Under $5,000','project_description'=>'Same description','preferred_contact_method'=>'email','consent_status'=>1,'source'=>'website_contact_page'];$hash=lead_request_fingerprint($base);
 foreach(array_keys($base)as$key){$changed=$base;$changed[$key]=(string)$changed[$key].' changed';check($hash!==lead_request_fingerprint($changed),'Fingerprint omitted '.$key);}
 $upper=$base;$upper['email']='A@EXAMPLE.INVALID';check($hash===lead_request_fingerprint($upper),'Email normalization mismatch');
 $snap=['id'=>1,'label'=>'Fixture','observed_at'=>'2026-10-12 12:00:00','window_start'=>'2026-09-12','window_end'=>'2026-10-09','metrics'=>[['title'=>'Zero value','metric_value'=>'0','unit'=>'leads','window_start'=>'2026-09-12','window_end'=>'2026-10-09','collection_status'=>'Measured','status_reason'=>'Test','evidence_reference'=>'Fixture']]];
 check(str_contains(baseline_report($snap,'markdown'),'| 0 |'),'Markdown lost zero');check(str_contains(baseline_report($snap,'csv'),',0,'),'CSV lost zero');check(!str_contains(baseline_report($snap,'markdown'),'200 OK'),'Fabricated HTTP baseline');
 $id=capture_baseline_snapshot('Initial fixture','2026-09-12','2026-10-09','Fixture coverage unavailable',false,0);$before=baseline_snapshot($id);
 db_exec("UPDATE marketing_baseline SET metric_value='999',collection_status='Measured'");check($before['metrics']===baseline_snapshot($id)['metrics'],'Snapshot changed after draft mutation');
 $o=DB::query("INSERT INTO notification_outbox(recipient_email,recipient_name,subject,body_html,body_plain,idempotency_key,next_retry_at) VALUES ('fixture@example.invalid','Fixture','Fixture','Test','Test','future',?)",[date('Y-m-d H:i:s',time()+3600)]);
 $r=Mailer::processOutboxQueue(50);check($r['processed']===0,'Future queued message processed');
 db_exec("UPDATE notification_outbox SET next_retry_at=NOW()");$r=Mailer::processOutboxQueue(50);check($r['simulated']===1&&$r['sent']===0,'Testing dispatched real mail or failed simulation');
 db_exec("UPDATE notification_outbox SET status='processing',worker_token='active'");Mailer::requeuePendingOrFailed();check(DB::query('SELECT status FROM notification_outbox')->fetchColumn()==='processing','Recovery revoked active worker');
 $held=fopen($path.'.worker-lock','c');flock($held,LOCK_EX);$busy=Mailer::processOutboxQueue();check(!empty($busy['busy']),'Second worker was not excluded');flock($held,LOCK_UN);fclose($held);
 db_exec("UPDATE integration_verifications SET status='Verified with dated evidence',verified_at=NULL,evidence_summary='Verified via legacy file googlea9c88d1e38989379.html' WHERE service_type='gsc_prefix'");production_evidence_repairs();check(DB::query("SELECT status FROM integration_verifications WHERE service_type='gsc_prefix'")->fetchColumn()==='Verification pending','Legacy evidence claim persisted');
 db_exec("UPDATE integration_verifications SET status='Verified with dated evidence',verified_at=NOW(),evidence_summary='Real client verification evidence' WHERE service_type='gsc_prefix'");production_evidence_repairs();check(DB::query("SELECT status FROM integration_verifications WHERE service_type='gsc_prefix'")->fetchColumn()==='Verified with dated evidence','Genuine dated verification was overwritten');
 check(TrackingService::isSensitiveRoute(),'Testing enabled production analytics');
 $cmd=escapeshellarg(PHP_BINARY).' -c '.escapeshellarg((string)get_cfg_var('cfg_file_path')).' '.escapeshellarg(__DIR__.'/production_mail_block.php').' '.escapeshellarg($path);exec($cmd,$out,$code);check($code===0,'Production SMTP block contract failed');
 echo json_encode(['passed'=>$n,'suite'=>'actual PHP contracts; SQLite fixture; SMTP simulated'])."\n";
}finally{DB::setConnection(null);@unlink($path);@unlink($path.'.worker-lock');}
