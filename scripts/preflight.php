<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
try{
 require __DIR__.'/../includes/db.php';require __DIR__.'/../includes/mailer.php';
 $o=getopt('',['expect:','schema','release-check']);
 if(isset($o['expect'])&&$o['expect']!==APP_ENV)throw new RuntimeException('Environment does not match requested release target.');
 if(PHP_VERSION_ID<80200)throw new RuntimeException('PHP 8.2+ required.');
 foreach(['pdo_mysql','mbstring','dom','gd','openssl','fileinfo','curl']as$extension)if(!extension_loaded($extension))throw new RuntimeException('Missing required PHP extension: '.$extension);
 if(!is_file(ROOT_DIR.'/vendor/autoload.php'))throw new RuntimeException('Composer dependencies missing.');
 if(!DB::isConnected())throw new RuntimeException('Database connection unavailable.');
 $pdo=DB::getConnection();$mode=(string)$pdo->query('SELECT @@sql_mode')->fetchColumn();
 if(!str_contains($mode,'STRICT_TRANS_TABLES')&&!str_contains($mode,'STRICT_ALL_TABLES'))throw new RuntimeException('Strict SQL mode required.');
 if(isset($o['schema'])){
    $unique=DB::query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND NON_UNIQUE=0 GROUP BY INDEX_NAME HAVING COUNT(*)=1 AND MAX(COLUMN_NAME)='idempotency_key'")->fetchColumn();if(!$unique)throw new RuntimeException('Lead request uniqueness is not enforced.');
    foreach(['leads'=>['idempotency_key','payload_hash'],'notification_outbox'=>['worker_token'],'baseline_snapshots'=>['metrics_json']]as$t=>$cols)foreach($cols as$c)if(!DB::query('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$t,$c])->fetchColumn())throw new RuntimeException('Missing schema: '.$t.'.'.$c);
    if(!DB::query("SELECT 1 FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='super_admin' AND u.status='active' AND u.deleted_at IS NULL")->fetchColumn())throw new RuntimeException('Provision an active Super Admin before activation.');
 }
 if(APP_ENV==='production' && !in_array(SMTP_SECURE,['ssl','tls'],true))throw new RuntimeException('Use encrypted SMTP.');
 if(APP_ENV==='staging'&&(!app_env('STAGING_AUTH_USER')||!app_env('STAGING_AUTH_HASH')))throw new RuntimeException('Staging access credentials required.');
 if(isset($o['release-check'])&&APP_ENV==='production'){
    if(!app_env('SMTP_PASS') || preg_match('/replace|placeholder|change.?me/i',(string)app_env('SMTP_PASS')))throw new RuntimeException('Configure and test production SMTP before activation.');
    if(!hash_equals(smtp_configuration_fingerprint(),(string)DB::query("SELECT setting_value FROM settings WHERE setting_key='smtp_verified_fingerprint'")->fetchColumn()))throw new RuntimeException('Verify the current SMTP configuration with scripts/verify_smtp.php before activation.');
    if(!DB::query('SELECT 1 FROM notification_recipients WHERE is_active=1')->fetchColumn())throw new RuntimeException('Configure a notification recipient before activation.');
 }
 echo json_encode(['ok'=>true,'environment'=>APP_ENV,'schema_checked'=>isset($o['schema']),'php'=>PHP_VERSION],JSON_PRETTY_PRINT)."\n";
}catch(Throwable$e){fwrite(STDERR,'Preflight failed: '.$e->getMessage()."\n");exit(1);}
