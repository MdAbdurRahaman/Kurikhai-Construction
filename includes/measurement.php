<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/security.php';
function analytics_setting(string $key,string $default=''): string {
    $value=DB::isConnected()?db_val('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1',[$key]):null;
    return $value===false || $value===null ? $default : (string)$value;
}
function analytics_public_path(string $path): string {
    $path=parse_url($path,PHP_URL_PATH) ?: '/';
    if (!preg_match('~^/[a-zA-Z0-9_/-]*$~',$path) || strlen($path)>200 || preg_match('~^/(admin|api|scripts|config|data|includes|ops|tests|vendor)(/|$)~i',$path)) throw new InvalidArgumentException('Invalid public page.');
    if (preg_match('/\d{7,}/',$path)) throw new InvalidArgumentException('Invalid public page.');
    return $path;
}
function analytics_label(mixed $value): string {
    if (!is_string($value)) return '';
    $value=strtolower(trim($value));
    return preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/',$value) && !preg_match('/\d{7,}/',$value) ? $value : '';
}
function normalize_analytics_event(array $p): array {
    $names=['page_view','form_start','form_error','generate_lead','contact_click','scroll_depth','engagement','web_vital','page_error'];
    if (!in_array($p['event_name']??null,$names,true)) throw new InvalidArgumentException('Unsupported event.');
    foreach (['event_id','session_id','visitor_id'] as $field) if (!is_string($p[$field]??null) || !preg_match('/^[a-f0-9]{32,64}$/',$p[$field])) throw new InvalidArgumentException('Invalid event identifier.');
    $device=in_array($p['device_type']??'', ['mobile','tablet','desktop'],true)?$p['device_type']:'desktop';
    $channels=['direct','organic_search','paid_search','organic_social','paid_social','email','referral','other'];
    $channel=in_array($p['channel']??'', $channels,true)?$p['channel']:'other';
    $detail=analytics_label($p['detail']??'');$value=null;
    if ($p['event_name']==='web_vital') {
        if (!in_array($detail,['lcp','cls','inp','ttfb','fcp'],true) || !is_numeric($p['metric_value']??null)) throw new InvalidArgumentException('Invalid performance metric.');
        $value=(float)$p['metric_value'];
        if (!is_finite($value) || $value<0 || $value>($detail==='cls'?100:120000)) throw new InvalidArgumentException('Invalid performance value.');
    } elseif ($p['event_name']==='scroll_depth') {
        if (!in_array($p['metric_value']??null,[25,50,75,90],true)) throw new InvalidArgumentException('Invalid scroll depth.');
        $value=$p['metric_value'];
    } elseif ($p['event_name']==='engagement') {
        $value=filter_var($p['metric_value']??null,FILTER_VALIDATE_INT);
        if ($value===false || $value<1 || $value>60) throw new InvalidArgumentException('Invalid engagement time.');
    } elseif ($p['event_name']==='contact_click' && !in_array($detail,['phone','whatsapp','email','maps'],true)) throw new InvalidArgumentException('Invalid contact type.');
    elseif ($p['event_name']==='form_error') $detail=in_array($detail,['validation','network','server'],true)?$detail:'server';
    elseif ($p['event_name']==='page_error') $detail='not_found';
    else $detail='';
    if (!is_string($p['page_path']??null)) throw new InvalidArgumentException('Invalid public page.');
    return ['event_id'=>$p['event_id'],'session_hash'=>hash_hmac('sha256',$p['session_id'],HMAC_SECRET),'visitor_hash'=>hash_hmac('sha256',$p['visitor_id'],HMAC_SECRET),'event_name'=>$p['event_name'],'page_path'=>analytics_public_path($p['page_path']??'/'),'channel'=>$channel,'source_label'=>analytics_label($p['source']??''),'medium_label'=>analytics_label($p['medium']??''),'campaign_label'=>analytics_label($p['campaign']??''),'device_type'=>$device,'detail'=>$detail,'metric_value'=>$value];
}
function store_analytics_event(array $event): void {
    $columns=array_keys($event);$params=array_values($event);
    $sql='INSERT INTO site_measurement_events('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') $sql.=' ON CONFLICT(event_id) DO UPDATE SET metric_value=excluded.metric_value';
    else $sql.=' ON DUPLICATE KEY UPDATE metric_value=VALUES(metric_value)';
    db_exec($sql,$params);
}
function analytics_period(): array {
    $end=$_GET['end']??date('Y-m-d');$start=$_GET['start']??date('Y-m-d',strtotime('-29 days'));
    foreach ([$start,$end] as $date) if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || date('Y-m-d',strtotime($date))!==$date) throw new InvalidArgumentException('Use valid dates.');
    if ($start>$end || $end>date('Y-m-d') || strtotime($end)-strtotime($start)>365*86400) throw new InvalidArgumentException('Select a date range of at most one year, ending today or earlier.');
    return [$start,$end];
}
function analytics_percentile(array $values,float $p=.75): ?float {
    if (!$values) return null;sort($values,SORT_NUMERIC);$index=max(0,(int)ceil(count($values)*$p)-1);return (float)$values[$index];
}

function purge_site_measurements(): int {
    $cutoff=date('Y-m-d H:i:s',time()-90*86400);
    return db_exec('DELETE FROM site_measurement_events WHERE event_id IN (SELECT event_id FROM (SELECT event_id FROM site_measurement_events WHERE created_at<? LIMIT 500) expired_events)',[$cutoff]);
}
