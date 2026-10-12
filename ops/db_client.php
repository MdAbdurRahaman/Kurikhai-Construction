<?php
if(PHP_SAPI!=='cli')exit(1);
$config=is_file(__DIR__.'/../application/config/config.php')?__DIR__.'/../application/config/config.php':__DIR__.'/../config/config.php';require$config;
if(in_array('--name',$argv,true)){echo DB_NAME;exit;}
$quote=fn($v)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$v).'"';
echo "[client]\nhost=".$quote(DB_HOST)."\nport=".(int)DB_PORT."\nuser=".$quote(DB_USER)."\npassword=".$quote(DB_PASS)."\n";
