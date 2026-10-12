<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../includes/data.php';
$o=getopt('',['username:','email:','name:']);
try{
 $name=trim($o['name']??'');$username=trim($o['username']??'');$email=trim($o['email']??'');
 if(!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/',$username)||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($name)<2)throw new InvalidArgumentException('Provide --username, --email and --name.');
 fwrite(STDERR,"New administrator password: ");
 $interactive=stream_isatty(STDIN);$restore=null;
 if($interactive){
    if(PHP_OS_FAMILY==='Windows'||!function_exists('exec'))throw new RuntimeException('Hidden terminal input unavailable. Use a protected stdin pipe.');
    exec('stty -g',$state,$status);
    if($status!==0||empty($state[0])||!preg_match('/^[0-9a-fA-F:]+$/',$state[0]))throw new RuntimeException('Cannot read terminal state; refusing visible password entry.');
    $saved=$state[0];$restored=false;
    $restore=function()use($saved,&$restored){if(!$restored){$restored=true;exec('stty '.escapeshellarg($saved));fwrite(STDERR,"\n");}};
    register_shutdown_function($restore);
    exec('stty -echo',$unused,$status);
    if($status!==0)throw new RuntimeException('Cannot disable terminal echo; refusing visible password entry.');
 }
 try{$password=rtrim(fgets(STDIN)?:'',"\r\n");}finally{if($restore)$restore();}
 if(strlen($password)<12||!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/[0-9]/',$password))throw new InvalidArgumentException('Use 12+ characters with uppercase, lowercase and a digit.');
 DB::transaction(function()use($name,$username,$email,$password){
    $role=DB::query("SELECT id FROM roles WHERE slug='super_admin'")->fetchColumn();if(!$role)throw new RuntimeException('Run migrations first.');
    db_exec('INSERT INTO users(name,username,email,password_hash,role_id,force_password_change) VALUES (?,?,?,?,?,1)',[$name,$username,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
    audit_log('user.provisioned_cli','users',null,null,['username'=>$username]);
 });echo "Administrator created; password rotation required at first sign-in.\n";
}catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
