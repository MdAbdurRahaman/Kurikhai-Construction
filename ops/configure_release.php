<?php
if(PHP_SAPI!=='cli')exit(1);
$config=is_file(__DIR__.'/../application/config/config.php')?__DIR__.'/../application/config/config.php':__DIR__.'/../config/config.php';require$config;
$dest=$argv[1];$root=$argv[2];$target=$argv[3];if(APP_ENV!==$target)throw new RuntimeException('Wrong environment');
if($target==='staging'){
 $user=(string)app_env('STAGING_AUTH_USER');$hash=(string)app_env('STAGING_AUTH_HASH');if(!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/',$user)||!str_starts_with($hash,'$2y$'))throw new RuntimeException('Use a valid staging username and bcrypt password hash.');
 $auth=$root.'/shared/staging.htpasswd';file_put_contents($auth,$user.':'.$hash."\n");chmod($auth,0640);
 $rule="\nAuthType Basic\nAuthName \"Private staging\"\nAuthUserFile \"".$auth."\"\nRequire valid-user\n<IfModule mod_setenvif.c>\nSetEnvIfNoCase Authorization \"(.+)\" HTTP_AUTHORIZATION=$1\n</IfModule>\n<IfModule mod_headers.c>\nHeader always set X-Robots-Tag \"noindex, nofollow, noarchive\"\n</IfModule>\n";
 file_put_contents($dest.'/.htaccess',$rule,FILE_APPEND);
}
