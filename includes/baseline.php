<?php
require_once __DIR__.'/db.php';
function baseline_metrics(): array {
    return DB::query('SELECT * FROM marketing_baseline ORDER BY id')->fetchAll();
}
function baseline_suggested_period(): array {
    $dates=DB::query("SELECT MIN(DATE(created_at)), MAX(DATE(created_at)) FROM leads WHERE deleted_at IS NULL AND source IN ('website_quote_form','website_contact_page','quick_quote_popup','website_direct','website') AND created_at < ?",[date('Y-m-d').' 00:00:00'])->fetch(PDO::FETCH_NUM);
    return ['start'=>(string)($dates[0]??''),'end'=>(string)($dates[1]??'')];
}
function baseline_snapshot(?int $id=null): ?array {
    $s=$id?DB::query('SELECT * FROM baseline_snapshots WHERE id=?',[$id])->fetch():DB::query('SELECT * FROM baseline_snapshots ORDER BY id LIMIT 1')->fetch();
    if(!$s)return null;$s['metrics']=json_decode($s['metrics_json'],true,512,JSON_THROW_ON_ERROR);return $s;
}
function capture_baseline_snapshot(string $label,string $start,string $end,string $evidence,bool $crmCoverage,int $actor): int {
    foreach([$start,$end]as$d) { $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$d);if(!$parsed||$parsed->format('Y-m-d')!==$d)throw new InvalidArgumentException('Invalid period date.'); }
    if($start>$end||$end>=date('Y-m-d')||strlen(trim($evidence))<10||strlen($label)<2)throw new InvalidArgumentException('Use complete past days, a label and dated source evidence.');
    return DB::transaction(function($pdo)use($label,$start,$end,$evidence,$crmCoverage,$actor){
        $metrics=baseline_metrics();
        foreach($metrics as &$m){
            if(str_starts_with($m['metric_key'],'leads_')) {
                $m['metric_value']=null;$m['collection_status']='Not available';$m['status_reason']='Historical website CRM coverage has not been confirmed.';
                if($crmCoverage){
                    $extra=match($m['metric_key']){'leads_qualified_total'=>" AND status='qualified'",'leads_won_total'=>" AND status='won'",default=>" AND status!='spam'"};
                    $m['metric_value']=(string)DB::query("SELECT COUNT(*) FROM leads WHERE deleted_at IS NULL AND source IN ('website_quote_form','website_contact_page','quick_quote_popup','website_direct','website') AND created_at >= ? AND created_at < ?".$extra,[$start.' 00:00:00',(new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d').' 00:00:00'])->fetchColumn();
                    $m['collection_status']='Measured';$m['status_reason']='Website sources; lead status and deletion state as at snapshot observation, not retrospectively reconstructed.';
                }
            }elseif(($m['window_start']??null)!==$start||($m['window_end']??null)!==$end){
                $m['metric_value']=null;$m['collection_status']='Not available';$m['status_reason']='No authenticated measurement for this period.';
            }
            $m['window_start']=$start;$m['window_end']=$end;$m['evidence_reference']=$evidence;
        }unset($m);
        db_exec('INSERT INTO baseline_snapshots(label,window_start,window_end,reporting_timezone,observed_at,metrics_json,evidence_reference,created_by) VALUES (?,?,?,?,?,?,?,?)',[$label,$start,$end,'Asia/Singapore',date('Y-m-d H:i:s'),json_encode($metrics,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$evidence,$actor?:null]);
        return (int)$pdo->lastInsertId();
    });
}
function baseline_report(array $snapshot,string $format): string {
    $metrics=$snapshot['metrics'];
    if($format==='csv'){
        $fp=fopen('php://temp','w+');fwrite($fp,"\xEF\xBB\xBF");
        fputcsv($fp,['Snapshot','Observed At','Metric','Value','Unit','Period Start','Period End','Timezone','Status','Reason','Evidence']);
        foreach($metrics as$m){$v=$m['metric_value'];$cells=[$snapshot['id']??'draft',$snapshot['observed_at']??'Not captured',$m['title'],($v!==null&&$v!=='')?(string)$v:'Not available',$m['unit'],$m['window_start'],$m['window_end'],'Asia/Singapore',$m['collection_status'],$m['status_reason'],$m['evidence_reference']??''];fputcsv($fp,array_map(fn($c)=>preg_match('/^[=+@\-]/',(string)$c)?"'".$c:(string)$c,$cells));}
        rewind($fp);return stream_get_contents($fp);
    }
    $escape=fn($v)=>str_replace(["|","\r","\n"],['\\|',' ',' '],(string)$v);
    $out='# Tabeeb baseline — '.$escape($snapshot['label'])."\n\nSnapshot: ".($snapshot['id']??'draft')."\nObserved: ".($snapshot['observed_at']??'Not captured')." Asia/Singapore\nPeriod: ".$snapshot['window_start'].' to '.$snapshot['window_end']."\n\n| Metric | Value | Status | Reason |\n|---|---|---|---|\n";
    foreach($metrics as$m){$v=$m['metric_value'];$out.='| '.$escape($m['title']).' | '.$escape(($v!==null&&$v!=='')?$v:'Not available').' | '.$escape($m['collection_status']).' | '.$escape($m['status_reason'])." |\n";}
    return $out."\nHTTP response, page metadata and Google property verification are not inferred by this report. Attach dated external checks.\n";
}
