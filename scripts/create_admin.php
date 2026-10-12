<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../includes/data.php';
$o=getopt('',['username:','email:','name:']);
try{
 $name=trim($o['name']??'');$username=trim($o['username']??'');$email=trim($o['email']??'');
 if(!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/',$username)||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($name)<2)throw new InvalidArgumentException('Provide --username, --email and --name.');
 fwrite(STDERR,"New administrator password (stdin; use a protected pipe or hidden shell prompt): ");$password=rtrim(fgets(STDIN)?:'',"\r\n");
 if(strlen($password)<12||!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/[0-9]/',$password))throw new InvalidArgumentException('Use 12+ characters with uppercase, lowercase and a digit.');
 DB::transaction(function()use($name,$username,$email,$password){
    $role=DB::query("SELECT id FROM roles WHERE slug='super_admin'")->fetchColumn();if(!$role)throw new RuntimeException('Run migrations first.');
    db_exec('INSERT INTO users(name,username,email,password_hash,role_id,force_password_change) VALUES (?,?,?,?,?,1)',[$name,$username,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
    audit_log('user.provisioned_cli','users',null,null,['username'=>$username]);
 });echo "Administrator created; password rotation required at first sign-in.\n";
}catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
