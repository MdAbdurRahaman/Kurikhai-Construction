<?php
// PHP development-server router used only by tests. Apache uses .htaccess in deployment.
$p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$root=dirname(__DIR__);
if(preg_match('~^/(config|scripts|data|ops|tests)/~',$p)){http_response_code(403);exit;}
if(str_starts_with($p,'/images/projects/')){$_GET['path']=$p;require$root.'/media.php';return true;}
$routes=['/'=>'index.php','/contact'=>'contact.php','/services'=>'services.php','/privacy'=>'privacy.php','/terms'=>'terms.php','/about'=>'about.php','/sitemap.xml'=>'sitemap.php','/api/leads'=>'api/leads.php'];
if(isset($routes[$p])){require$root.'/'.$routes[$p];return true;}
if(preg_match('~^/projects/([a-z0-9_-]+)$~',$p,$m)){$_GET['slug']=$m[1];require$root.'/project.php';return true;}
if(is_file($root.$p))return false;
$_GET['slug']=ltrim($p,'/');require$root.'/service.php';return true;
