<?php
require_once dirname(__DIR__).'/includes/data.php';require_permission('analytics.view');
require_once dirname(__DIR__).'/includes/measurement.php';require_once dirname(__DIR__).'/includes/tracking.php';require_once dirname(__DIR__).'/includes/google_reports.php';
$error='';try{[$start,$end]=analytics_period();}catch(Throwable $e){$error=$e->getMessage();$start=date('Y-m-d',strtotime('-29 days'));$end=date('Y-m-d');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!has_permission('settings.manage') || !verify_csrf_token($_POST['csrf_token']??null)){$error='Permission or security token invalid.';}
 else {try{if(!check_rate_limit('google_report_refresh',3,60)['allowed'])throw new RuntimeException('Please wait a minute before refreshing Google reports again.');ga4_fetch_reports($start,$end);audit_log('analytics.google_refreshed','analytics',null,null,['start'=>$start,'end'=>$end]);}catch(Throwable $e){$error=$e->getMessage();}}
}
$params=[$start.' 00:00:00',$end.' 23:59:59'];$window='created_at>=? AND created_at<=?';
$events=db_all("SELECT event_name,COUNT(*) AS total FROM site_measurement_events WHERE $window GROUP BY event_name",$params);$counts=array_column($events,'total','event_name');
$summary=db_one("SELECT COUNT(DISTINCT session_hash) AS sessions,COUNT(DISTINCT visitor_hash) AS visitors FROM site_measurement_events WHERE $window AND event_name='page_view'",$params);
$converted=(int)db_val("SELECT COUNT(DISTINCT session_hash) FROM site_measurement_events WHERE $window AND event_name='generate_lead' AND session_hash IN (SELECT session_hash FROM site_measurement_events WHERE created_at>=? AND created_at<=? AND event_name='page_view')",array_merge($params,$params));
$engagementSeconds=(float)db_val("SELECT COALESCE(SUM(metric_value),0) FROM site_measurement_events WHERE $window AND event_name='engagement'",$params);
$startedSessions=(int)db_val("SELECT COUNT(DISTINCT session_hash) FROM site_measurement_events WHERE $window AND event_name='form_start'",$params);
$completedSessions=(int)db_val("SELECT COUNT(DISTINCT session_hash) FROM site_measurement_events WHERE $window AND event_name='generate_lead' AND session_hash IN (SELECT session_hash FROM site_measurement_events WHERE created_at>=? AND created_at<=? AND event_name='form_start')",array_merge($params,$params));
$sessions=(int)($summary['sessions']??0);$rate=$sessions?round(100*$converted/$sessions,2):null;
$pages=db_all("SELECT page_path,COUNT(*) AS views FROM site_measurement_events WHERE $window AND event_name='page_view' GROUP BY page_path ORDER BY views DESC LIMIT 25",$params);
$channels=db_all("SELECT channel,COUNT(DISTINCT session_hash) AS sessions FROM site_measurement_events WHERE $window AND event_name='page_view' GROUP BY channel ORDER BY sessions DESC",$params);
$devices=db_all("SELECT device_type,COUNT(DISTINCT session_hash) AS sessions FROM site_measurement_events WHERE $window AND event_name='page_view' GROUP BY device_type ORDER BY sessions DESC",$params);
$clicks=db_all("SELECT detail,COUNT(*) AS clicks FROM site_measurement_events WHERE $window AND event_name='contact_click' GROUP BY detail",$params);
$campaigns=db_all("SELECT source_label,medium_label,campaign_label,COUNT(DISTINCT session_hash) AS sessions FROM site_measurement_events WHERE $window AND event_name='page_view' AND campaign_label<>'' GROUP BY source_label,medium_label,campaign_label ORDER BY sessions DESC LIMIT 25",$params);
$daily=db_all("SELECT DATE(created_at) AS day,COUNT(*) AS page_views,COUNT(DISTINCT session_hash) AS sessions FROM site_measurement_events WHERE $window AND event_name='page_view' GROUP BY DATE(created_at) ORDER BY day",$params);
$vitals=[];foreach(['lcp'=>2500,'inp'=>200,'cls'=>.1,'ttfb'=>800,'fcp'=>1800] as $metric=>$good){
 $rows=db_all("SELECT metric_value FROM site_measurement_events WHERE $window AND event_name='web_vital' AND detail=? ORDER BY created_at DESC LIMIT 5000",array_merge($params,[$metric]));
 $vitals[]=['Metric'=>strtoupper($metric),'P75'=>($rows?number_format(analytics_percentile(array_column($rows,'metric_value')),$metric==='cls'?3:0).($metric==='cls'?'':' ms'):'Not measured'),'Samples'=>count($rows),'Good threshold'=>$good.($metric==='cls'?'':' ms')];
}
$crm=[];if(portal_module_available('leads') && has_permission('leads.view'))$crm=db_all("SELECT status,COUNT(*) AS leads,COALESCE(SUM(estimated_deal_value),0) AS pipeline_value FROM leads WHERE $window AND deleted_at IS NULL GROUP BY status",$params);
$google=ga4_cached_reports($start,$end);$tagConfig=TrackingService::getConfig();$reportConfig=ga4_report_configuration();
$pageTitle='Traffic, Conversions & Performance';require __DIR__.'/header.php';
function analytics_table(string $title,array $rows):void{echo '<div class="table-card" style="margin:20px 0;padding:20px;"><h3>'.htmlspecialchars($title).'</h3>';if(!$rows){echo '<p>No recorded data for this period.</p></div>';return;}echo '<div class="table-wrapper"><table class="adm-table"><thead><tr>';foreach(array_keys($rows[0])as$key)echo '<th>'.htmlspecialchars(ucwords(str_replace('_',' ',$key))).'</th>';echo '</tr></thead><tbody>';foreach($rows as$row){echo '<tr>';foreach($row as$value)echo '<td>'.htmlspecialchars((string)$value).'</td>';echo '</tr>';}echo '</tbody></table></div></div>';}
?>
<?php if($error):?><p role="alert" style="color:#b91c1c"><?=htmlspecialchars($error)?></p><?php endif;?>
<div class="table-card" style="padding:20px;">
<form method="GET"><label>From <input type="date" name="start" value="<?=$start?>" required></label> <label>To <input type="date" name="end" value="<?=$end?>" required></label> <button class="btn-adm btn-adm-primary">Apply period</button></form>
<p style="margin-top:12px;">First-party figures cover visitors who allow analytics, using Singapore server dates. They are not GA4 figures. Sessions are browser-tab sessions with a 30-minute inactivity window; visitors are anonymous browser identifiers, not identified people. Records older than 90 days expire. Contact clicks are enquiries of interest, not confirmed leads. Visible engagement is sampled in 15-second intervals; partial intervals are not counted.</p>
</div>
<div class="stat-grid" style="margin-top:20px;">
<?php foreach(['Measured visitors'=>(int)($summary['visitors']??0),'Measured sessions'=>$sessions,'Page views'=>(int)($counts['page_view']??0),'Browser-reported submissions'=>(int)($counts['generate_lead']??0),'Session conversion rate'=>$rate===null?'Not measured':$rate.'%','Form completion rate'=>$startedSessions?round(100*$completedSessions/$startedSessions,2).'%':'Not measured','Sampled visible time per session'=>$sessions?round($engagementSeconds/$sessions).' sec':'Not measured']as$label=>$value):?><div class="stat-card"><div><div class="stat-val"><?=htmlspecialchars((string)$value)?></div><div class="stat-label"><?=htmlspecialchars($label)?></div></div></div><?php endforeach;?>
</div>
<?php
analytics_table('Daily traffic',$daily);analytics_table('Acquisition channels',$channels);analytics_table('Popular pages',$pages);analytics_table('Device audience',$devices);analytics_table('Campaign attribution',$campaigns);analytics_table('Contact intent',$clicks);analytics_table('Events & form funnel',$events);analytics_table('Performance: real-user Core Web Vitals',$vitals);
?>
<p>Core Web Vitals are sampled from supported browsers. P75 is the 75th percentile of the most recent 5,000 samples per metric in this period. Missing INP means insufficient interaction samples. These figures are not PageSpeed Insights laboratory scores or Google Search Console field data.</p>
<?php analytics_table('Confirmed CRM enquiries by current status',$crm); ?>
<div class="table-card" style="padding:20px;margin-top:20px;">
<h3>Google Analytics 4 connection</h3>
<p>Tag configuration: <?=$tagConfig['is_configured']?'Configured — receipt still needs Google verification':'Not configured'?>. Reporting API: <?=$reportConfig['configured']?'Credential and property configured — refresh to verify access':'Not connected'?>.</p>
<p><a href="https://analytics.google.com/" target="_blank" rel="noopener">Open Google Analytics</a> · <a href="https://tagmanager.google.com/" target="_blank" rel="noopener">Open Tag Manager</a> · <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Open Search Console</a> · <a href="https://pagespeed.web.dev/" target="_blank" rel="noopener">Open PageSpeed Insights</a></p>
<?php if(has_permission('settings.manage')):?><form method="POST"><?=csrf_input_field()?><button class="btn-adm btn-adm-primary" style="margin-top:12px;" <?=$reportConfig['configured']?'':'disabled'?>>Refresh authenticated GA4 reports</button></form><?php endif;?>
</div>
<?php if($google):?><p>Google reports fetched: <?=htmlspecialchars($google['fetched_at'])?>. GA4 uses the property timezone and its own attribution and consent rules.</p><?php foreach($google['reports']as$name=>$report){$rows=[];foreach($report['rows']??[]as$row){$values=[];foreach($report['dimensionHeaders']??[]as$i=>$h)$values[$h['name']]=$row['dimensionValues'][$i]['value'];foreach($report['metricHeaders']??[]as$i=>$h)$values[$h['name']]=$row['metricValues'][$i]['value'];$rows[]=$values;}analytics_table('GA4: '.ucfirst($name),$rows);}endif;?>
<?php require __DIR__.'/footer.php'; ?>
