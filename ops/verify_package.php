<?php
if(PHP_SAPI!=='cli')exit(1);
try{
 $root=dirname(__DIR__);$app=realpath($root.'/application');if(!$app)throw new RuntimeException('Missing application directory.');
 $manifest=json_decode(file_get_contents($root.'/MANIFEST.json'),true,512,JSON_THROW_ON_ERROR);$seen=[];
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app,FilesystemIterator::SKIP_DOTS))as$f){
  if($f->isLink())throw new RuntimeException('Package symlinks are not accepted.');if(!$f->isFile())continue;
  $rel=str_replace('\\','/',substr($f->getPathname(),strlen($app)+1));if(!isset($manifest[$rel])||!hash_equals($manifest[$rel],hash_file('sha256',$f->getPathname())))throw new RuntimeException('Manifest mismatch: '.$rel);$seen[$rel]=true;
 }
 if(count($seen)!==count($manifest))throw new RuntimeException('Package files are missing.');echo "Release package hashes verified.\n";
}catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
