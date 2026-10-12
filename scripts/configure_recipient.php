<?php
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../includes/data.php';$o=getopt('',['email:','name:']);
try{$email=trim($o['email']??'');if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Supply --email for the approved lead-notification recipient.');db_exec("INSERT INTO notification_recipients(name,email,is_active,recipient_type) VALUES (?,?,1,'to') ON DUPLICATE KEY UPDATE is_active=1",[trim($o['name']??'Lead coordinator'),$email]);audit_log('notification.recipient_provisioned_cli','notification_recipients',null,null,['email'=>$email]);echo "Recipient configured. No email was sent.\n";}catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
