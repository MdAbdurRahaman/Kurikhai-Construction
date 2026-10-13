<?php
require_once __DIR__.'/../includes/data.php';
require_once __DIR__.'/../includes/baseline.php';
require_login();
if(!has_permission('baseline.view')&&!has_permission('baseline.manage')){http_response_code(403);exit('Forbidden');}
$canEdit=has_permission('baseline.manage');$error='';$success='';
$defaultStart='';$defaultEnd='';
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canEdit||!verify_csrf_token($_POST['csrf_token']??null))throw new RuntimeException('Permission or security token invalid.');
    foreach($_POST as$v)if(!is_scalar($v))throw new InvalidArgumentException('Invalid form value.');
    if(($_POST['action']??'')==='capture'){
        $id=capture_baseline_snapshot(trim($_POST['label']??''),$_POST['window_start']??'',$_POST['window_end']??'',trim($_POST['evidence']??''),isset($_POST['coverage']), (int)(current_user()['id']??0));
        audit_log('baseline.snapshot_created','baseline_snapshots',(string)$id);header('Location: baseline.php?snapshot='.$id);exit;
    }
    if(($_POST['action']??'')==='update_metric'){
        $key=trim($_POST['metric_key']??'');$status=$_POST['collection_status']??'';$evidence=trim($_POST['evidence_reference']??'');
        if(!in_array($status,['Measured','Not available','Pending access','Not previously tracked'],true))throw new InvalidArgumentException('Invalid status.');
        if($status==='Measured'&&strlen($evidence)<10)throw new InvalidArgumentException('Dated source evidence is required for a measurement.');
        DB::transaction(function()use($key,$status,$evidence){
            if(!DB::query('SELECT id FROM marketing_baseline WHERE metric_key=?',[$key])->fetchColumn())throw new InvalidArgumentException('Metric not found.');
            db_exec('UPDATE marketing_baseline SET metric_value=?,collection_status=?,status_reason=?,evidence_reference=?,window_start=?,window_end=?,collected_at=NOW() WHERE metric_key=?',[trim($_POST['metric_value']??''),$status,trim($_POST['status_reason']??''),$evidence,$_POST['window_start'],$_POST['window_end'],$key]);
            audit_log('baseline.draft_updated','marketing_baseline',$key);
        });$success='Draft measurement saved. Existing snapshots are unchanged.';
    }
 }
 $snap=baseline_snapshot(isset($_GET['snapshot'])?(int)$_GET['snapshot']:null);
 $metrics=$snap?$snap['metrics']:baseline_metrics();
 if(isset($_GET['export'])){
    if(!$snap)throw new RuntimeException('Capture a snapshot before exporting.');$format=$_GET['export']==='csv'?'csv':'markdown';
    header('Content-Type: '.($format==='csv'?'text/csv':'text/markdown').'; charset=UTF-8');header('Content-Disposition: attachment; filename="tabeeb_snapshot_'.$snap['id'].($format==='csv'?'.csv':'.md').'"');
    audit_log('baseline.export','baseline_snapshots',(string)$snap['id']);echo baseline_report($snap,$format);exit;
 }
 $snapshots=DB::query('SELECT id,label,observed_at FROM baseline_snapshots ORDER BY id DESC')->fetchAll();
 if($canEdit){$period=baseline_suggested_period();$defaultStart=$period['start'];$defaultEnd=$period['end'];}
}catch(Throwable$e){$error=$e->getMessage();$metrics=$metrics??[];$snapshots=$snapshots??[];$snap=$snap??null;}
$pageTitle='Marketing baseline';$currentPage='baseline';require __DIR__.'/header.php';
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<h2>Marketing baseline</h2><p>Captured snapshots remain unchanged. Record later periods as new snapshots; unavailable history stays unavailable.</p>
<?php if($error):?><p role="alert"><?= $e($error) ?></p><?php endif;?>
<?php if($success):?><p><?= $e($success) ?></p><?php endif;?>
<p><?php foreach($snapshots as$s):?><a href="?snapshot=<?= (int)$s['id'] ?>"><?= $e($s['label']) ?> (<?= $e($s['observed_at']) ?>)</a> &nbsp;<?php endforeach;?></p>
<?php if($snap):?><p>Snapshot <?= (int)$snap['id'] ?> · <?= $e($snap['window_start']) ?>–<?= $e($snap['window_end']) ?> · observed <?= $e($snap['observed_at']) ?> (Singapore)</p><p><a href="?snapshot=<?= (int)$snap['id'] ?>&amp;export=csv">Download CSV</a> · <a href="?snapshot=<?= (int)$snap['id'] ?>&amp;export=markdown">Download report</a></p><?php else:?><p>No snapshot has been captured. These are draft measurements.</p><?php endif;?>
<div class="table-card"><table><thead><tr><th>Metric</th><th>Value</th><th>Status</th><th>Evidence / reason</th></tr></thead><tbody>
<?php foreach($metrics as$m):?><tr><td><?= $e($m['title']) ?></td><td><?= $e($m['metric_value']!==null&&$m['metric_value']!==''?$m['metric_value']:'Not available') ?></td><td><?= $e($m['collection_status']) ?></td><td><?= $e(($m['evidence_reference']??'').' '.$m['status_reason']) ?></td></tr><?php endforeach;?></tbody></table></div>
<?php if($canEdit):?>
<p>Suggested dates reflect saved website enquiries on complete past days. Confirm the reporting period and coverage against your source evidence.</p>
<h3>Record a draft measurement</h3><form method="post"><?= csrf_input_field() ?><input type="hidden" name="action" value="update_metric">
<label>Metric <select name="metric_key"><?php foreach(baseline_metrics()as$m):?><option value="<?= $e($m['metric_key']) ?>"><?= $e($m['title']) ?></option><?php endforeach;?></select></label>
<label>Value <input name="metric_value"></label><label>Status <select name="collection_status"><?php foreach(['Measured','Not available','Pending access','Not previously tracked']as$s):?><option><?= $e($s) ?></option><?php endforeach;?></select></label>
<label>Period start <input type="date" name="window_start" value="<?= $e($defaultStart) ?>" required></label><label>Period end <input type="date" name="window_end" value="<?= $e($defaultEnd) ?>" required></label><label>Reason <input name="status_reason" required></label><label>Dated source evidence <input name="evidence_reference"></label><button>Save draft</button></form>
<h3>Capture a new snapshot</h3><form method="post"><?= csrf_input_field() ?><input type="hidden" name="action" value="capture"><label>Label <input name="label" value="Initial baseline" required></label><label>Period start <input type="date" name="window_start" value="<?= $e($defaultStart) ?>" required></label><label>Period end <input type="date" name="window_end" value="<?= $e($defaultEnd) ?>" required></label><label>Dated evidence and coverage notes <input name="evidence" minlength="10" required></label><label><input type="checkbox" name="coverage"> I have confirmed complete website enquiry capture for this period; statuses will be recorded as at this observation.</label><button>Capture snapshot</button></form>
<?php endif;?>
<?php require __DIR__.'/footer.php';?>
