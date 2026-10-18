<?php
if(PHP_SAPI!=='cli')exit(1);
$config=is_file(__DIR__.'/../application/config/config.php')?__DIR__.'/../application/config/config.php':__DIR__.'/../config/config.php';require$config;
$release=$argv[1]??'';if($release==='')exit(2);
try{
 foreach(['/','/contact','/services','/privacy','/terms','/sitemap.xml']as$route){
    // A just-switched document root may briefly be served by a stale worker or cache.
    // Keep identity validation strict; only accept the requested immutable release.
    $delays = [1, 2, 4, 8];
    for ($attempt = 0; $attempt <= count($delays); $attempt++) {
        $probe = http_build_query(['__release_probe' => $release . '-' . bin2hex(random_bytes(6))]);
        $c=curl_init(APP_URL.$route.'?'.$probe);$headers=[];
        curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_HTTPHEADER=>['Cache-Control: no-cache, no-store, max-age=0','Pragma: no-cache'],
            CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$headers){if(str_starts_with($line,'HTTP/'))$headers=[];elseif(str_contains($line,':')){[$k,$v]=explode(':',$line,2);$headers[strtolower(trim($k))]=trim($v);}return strlen($line);}]);
        if(APP_ENV==='staging'){if(!getenv('SMOKE_USER')||!getenv('SMOKE_PASSWORD'))throw new RuntimeException('Supply private staging smoke credentials through environment variables.');curl_setopt($c,CURLOPT_USERPWD,getenv('SMOKE_USER').':'.getenv('SMOKE_PASSWORD'));}
        $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);$error=curl_error($c);curl_close($c);
        $observed = $headers['x-tabeeb-release'] ?? '';
        if($status===200 && $body!==false && hash_equals($release,$observed)) break;
        if($attempt === count($delays)) {
            // Release identifiers are public; do not print response bodies or authentication headers.
            $safeObserved = preg_match('/^[A-Za-z0-9_-]{1,100}$/',$observed) ? $observed : ($observed === '' ? '(missing)' : '(invalid)');
            throw new RuntimeException('Route or release identity check failed: '.$route.' HTTP '.$status.' expected='.$release.' observed='.$safeObserved.' after '.($attempt+1).' attempts. '.$error);
        }
        fwrite(STDERR,'Waiting for active release response: '.$route.' HTTP '.$status.'; retry '.($attempt+1).'/'.count($delays)."\n");
        sleep($delays[$attempt]);
    }
    if(APP_ENV==='staging'&&!str_contains($headers['x-robots-tag']??'','noindex'))throw new RuntimeException('Staging indexing restriction missing.');
    if($route==='/'&&!str_contains($body,'Tabeeb'))throw new RuntimeException('Brand content missing.');
    if($route==='/sitemap.xml'&&!str_contains($body,'<urlset'))throw new RuntimeException('Sitemap response invalid.');
 }
 echo "HTTP release checks passed. Confirm a real staging enquiry and Google/SMTP evidence separately.\n";
}catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
