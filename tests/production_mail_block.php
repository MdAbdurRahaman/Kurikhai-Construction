<?php
// Inject a disposable database to exercise the actual production configuration-block branch.
putenv('APP_ENV=production');putenv('APP_URL=https://tabeebgroup.com');putenv('SMTP_PASS=');
$root=dirname(__DIR__);$app=is_dir($root.'/application')?$root.'/application':$root;require $app.'/includes/mailer.php';
$path=$argv[1];if(!str_contains(basename($path),'tabeeb-test-'))exit(2);
$pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));$pdo->sqliteCreateFunction('GET_LOCK',fn($n,$t)=>1);$pdo->sqliteCreateFunction('RELEASE_LOCK',fn($n)=>1);DB::setConnection($pdo);
db_exec("UPDATE notification_outbox SET status='queued',attempts=0,next_retry_at=NOW(),worker_token=NULL");
$res=Mailer::processOutboxQueue(50);$row=DB::query('SELECT attempts,next_retry_at FROM notification_outbox LIMIT 1')->fetch();
if($res['blocked']!==1||(int)$row['attempts']!==0||$row['next_retry_at']<=date('Y-m-d H:i:s'))throw new RuntimeException('Production blocked queue consumed retry budget or ignored delay.');
echo "Production SMTP blocking passed; no delivery attempted.\n";
