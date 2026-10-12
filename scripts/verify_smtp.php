<?php
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../includes/data.php';require __DIR__.'/../includes/mailer.php';$o=getopt('',['to:']);
try{
 if(APP_ENV!=='production')throw new RuntimeException('This verifies actual production SMTP; staging/test simulation cannot provide evidence.');
 $email=$o['to']??'';
 if(!DB::query("SELECT 1 FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=? AND u.status='active' AND r.slug IN ('super_admin','administrator')",[$email])->fetchColumn())throw new RuntimeException('Use your configured administrator email address.');
 $res=Mailer::sendTestEmail($email);if(!$res['success']||$res['simulated'])throw new RuntimeException($res['error']??'SMTP was not verified.');
 foreach(['smtp_verified_fingerprint'=>smtp_configuration_fingerprint(),'smtp_verified_at'=>date('Y-m-d H:i:s')]as$key=>$value)db_exec('INSERT INTO settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);
 audit_log('smtp.configuration_verified_cli','settings',null,null,['provider_accepted'=>true]);echo "SMTP provider accepted the administrator test. Check receipt in your mailbox; provider acceptance alone is not inbox delivery.\n";
}catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
