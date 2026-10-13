<?php
if(PHP_SAPI!=='cli')exit(1);
$config=is_file(__DIR__.'/../application/config/config.php')?__DIR__.'/../application/config/config.php':__DIR__.'/../config/config.php';require$config;
$release=$argv[1]??'';if($release==='')exit(2);
try{
 foreach(['/','/contact','/services','/privacy','/terms','/sitemap.xml']as$route){
    $c=curl_init(APP_URL.$route);$headers=[];curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$headers){if(str_contains($line,':')){[$k,$v]=explode(':',$line,2);$headers[strtolower(trim($k))]=trim($v);}return strlen($line);}]);
    if(APP_ENV==='staging'){if(!getenv('SMOKE_USER')||!getenv('SMOKE_PASSWORD'))throw new RuntimeException('Supply private staging smoke credentials through environment variables.');curl_setopt($c,CURLOPT_USERPWD,getenv('SMOKE_USER').':'.getenv('SMOKE_PASSWORD'));}
    $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);$error=curl_error($c);curl_close($c);
    if($status!==200||$body===false||($headers['x-tabeeb-release']??'')!==$release)throw new RuntimeException('Route or release identity check failed: '.$route.' HTTP '.$status.' '.$error);
    if(APP_ENV==='staging'&&!str_contains($headers['x-robots-tag']??'','noindex'))throw new RuntimeException('Staging indexing restriction missing.');
    if($route==='/'&&!str_contains($body,'Tabeeb'))throw new RuntimeException('Brand content missing.');
    if($route==='/sitemap.xml'&&!str_contains($body,'<urlset'))throw new RuntimeException('Sitemap response invalid.');
 }
 echo "HTTP release checks passed. Confirm a real staging enquiry and Google/SMTP evidence separately.\n";
}catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
