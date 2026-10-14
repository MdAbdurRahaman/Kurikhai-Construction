<?php
require_once __DIR__.'/measurement.php';
function ga4_report_configuration(): array {
    $property=analytics_setting('ga4_property_id',(string)(getenv('GA4_PROPERTY_ID')?:''));
    $file=(string)(getenv('GA4_SERVICE_ACCOUNT_FILE')?:'');$real=$file?realpath($file):false;
    $private=$real && !str_starts_with($real,ROOT_DIR.DIRECTORY_SEPARATOR) && (!($_SERVER['DOCUMENT_ROOT']??'') || !str_starts_with($real,rtrim((string)$_SERVER['DOCUMENT_ROOT'],'/').'/'));
    return ['property'=>$property,'file'=>$private?$real:'','configured'=>preg_match('/^[0-9]{4,15}$/',$property) && $private && is_readable($real)];
}
function google_report_http(string $url,array $payload,?string $token=null,bool $form=false): array {
    if (APP_ENV==='testing' && str_starts_with(DB_NAME,'tabeeb_test_') && isset($GLOBALS['google_report_test_transport'])) return ($GLOBALS['google_report_test_transport'])($url,$payload,$token,$form);
    $ch=curl_init($url);
    $headers=['Content-Type: '.($form?'application/x-www-form-urlencoded':'application/json')];if($token)$headers[]='Authorization: Bearer '.$token;
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>($form?http_build_query($payload):json_encode($payload)),CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
    $body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if (!is_string($body) || $code<200 || $code>=300) throw new RuntimeException('Google report request failed. Check API access, property ID and service-account permissions.');
    $json=json_decode($body,true,64,JSON_THROW_ON_ERROR);if(!is_array($json))throw new RuntimeException('Invalid Google report response.');return $json;
}
function ga4_fetch_reports(string $start,string $end): array {
    $config=ga4_report_configuration();if(!$config['configured'])throw new RuntimeException('GA4 reporting is not connected.');
    if(filesize($config['file'])>65536)throw new RuntimeException('Invalid reporting credential file.');
    $credentials=json_decode(file_get_contents($config['file']),true,32,JSON_THROW_ON_ERROR);
    if(($credentials['type']??'')!=='service_account' || empty($credentials['client_email']) || empty($credentials['private_key']))throw new RuntimeException('Invalid reporting credential file.');
    $b64=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');$now=time();
    $jwt=$b64(json_encode(['alg'=>'RS256','typ'=>'JWT'])).'.'.$b64(json_encode(['iss'=>$credentials['client_email'],'scope'=>'https://www.googleapis.com/auth/analytics.readonly','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600]));
    if(!openssl_sign($jwt,$signature,$credentials['private_key'],OPENSSL_ALGO_SHA256))throw new RuntimeException('Reporting credential could not sign authentication.');
    $token=google_report_http('https://oauth2.googleapis.com/token',['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt.'.'.$b64($signature)],null,true);
    if(empty($token['access_token']))throw new RuntimeException('Google reporting authentication failed.');
    $url='https://analyticsdata.googleapis.com/v1beta/properties/'.$config['property'].':batchRunReports';
    $request=function(array $dims,array $metrics,array $extra=[])use($start,$end){return array_merge(['dateRanges'=>[['startDate'=>$start,'endDate'=>$end]],'dimensions'=>array_map(fn($n)=>['name'=>$n],$dims),'metrics'=>array_map(fn($n)=>['name'=>$n],$metrics),'limit'=>50],$extra);};
    $requests=['summary'=>$request([],['activeUsers','newUsers','sessions','screenPageViews','engagedSessions','userEngagementDuration']),'channels'=>$request(['sessionDefaultChannelGroup'],['sessions','activeUsers']),'pages'=>$request(['pagePath'],['screenPageViews'],['orderBys'=>[['metric'=>['metricName'=>'screenPageViews'],'desc'=>true]]]),'audience'=>$request(['deviceCategory','country'],['activeUsers']),'events'=>$request(['eventName'],['eventCount'],['dimensionFilter'=>['inListFilter'=>['values'=>['generate_lead','contact_click','form_start','form_error']],'fieldName'=>'eventName']])];
    $response=google_report_http($url,['requests'=>array_values($requests)],$token['access_token']);
    if (count($response['reports']??[])!==5) throw new RuntimeException('Google returned incomplete reports.');
    $reports=array_combine(array_keys($requests),$response['reports']);
    $key=hash('sha256',$config['property'].'|'.$start.'|'.$end);
    $json=json_encode($reports,JSON_THROW_ON_ERROR);$at=date('Y-m-d H:i:s');
    $sql='INSERT INTO analytics_report_cache(report_key,report_json,fetched_at) VALUES (?,?,?)';
    $sql.=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?' ON CONFLICT(report_key) DO UPDATE SET report_json=excluded.report_json,fetched_at=excluded.fetched_at':' ON DUPLICATE KEY UPDATE report_json=VALUES(report_json),fetched_at=VALUES(fetched_at)';
    db_exec($sql,[$key,$json,$at]);return ['reports'=>$reports,'fetched_at'=>$at];
}
function ga4_cached_reports(string $start,string $end): ?array {
    $config=ga4_report_configuration();$key=hash('sha256',$config['property'].'|'.$start.'|'.$end);
    $row=db_one('SELECT report_json,fetched_at FROM analytics_report_cache WHERE report_key=?',[$key]);
    return $row?['reports'=>json_decode($row['report_json'],true),'fetched_at'=>$row['fetched_at']]:null;
}
