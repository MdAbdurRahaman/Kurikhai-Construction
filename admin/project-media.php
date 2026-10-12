<?php
require_once __DIR__.'/../includes/data.php';require_login();require_permission('projects.manage');
$project=(string)($_GET['project']??'');$p=get_project_by_id($project);if(!$p){http_response_code(404);exit('Project not found.');}$error='';$msg='';
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verify_csrf_token($_POST['csrf_token']??null))throw new RuntimeException('Invalid security token.');
    foreach($_POST as$v)if(!is_scalar($v))throw new InvalidArgumentException('Invalid form value.');
    $stage=(string)($_POST['stage']??'gallery');if(!in_array($stage,['before','in_progress','completed','gallery','video'],true))throw new InvalidArgumentException('Invalid media stage.');
    $caption=mb_substr(trim($_POST['caption']??''),0,255);$alt=mb_substr(trim($_POST['alt']??''),0,255);if($alt==='')throw new InvalidArgumentException('Describe the actual image.');
    $id=(int)($_POST['media_id']??0);$order=(int)($_POST['order']??0);
    DB::transaction(function()use($id,$project,$stage,$caption,$alt,$order){
        if($id){if(!DB::query('SELECT id FROM project_media WHERE id=? AND project_id=?',[$id,$project])->fetchColumn())throw new RuntimeException('Media does not belong to this project.');db_exec('UPDATE project_media SET media_stage=?,caption=?,alt_text=?,display_order=? WHERE id=? AND project_id=?',[$stage,$caption,$alt,$order,$id,$project]);}
        else{
            $path=trim($_POST['path']??'');if(!preg_match('~^/images/(?!.*(?:\.\.|\\\\))[a-zA-Z0-9/_ .-]+\.(webp|png|jpe?g|mp4)$~i',$path)||!is_file(ROOT_DIR.$path))throw new InvalidArgumentException('Use an existing project image/video path.');
            db_exec('INSERT INTO project_media(project_id,media_stage,media_type,file_path,caption,alt_text,display_order) VALUES (?,?,?,?,?,?,?)',[$project,$stage,str_ends_with(strtolower($path),'.mp4')?'video':'image',$path,$caption,$alt,$order]);
        }
        db_exec("UPDATE projects SET approval_status='pending',publication_state='draft',approved_at=NULL,approved_by=NULL WHERE id=?",[$project]);audit_log('project.media_updated','projects',$project);
    });$msg='Media saved. The project is now a draft pending approval.';
 }
}catch(Throwable$e){$error=$e->getMessage();}
$pageTitle='Project media';$currentPage='project-edit';require __DIR__.'/header.php';$e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
?>
<h2><?= $e($p['title']) ?> — media</h2><p><a href="project-edit.php?id=<?= urlencode($project) ?>">Return to project</a></p><p><?= $e($error?:$msg) ?></p>
<?php $media=get_project_media($project);$media[]=['id'=>0,'file_path'=>'','media_stage'=>'gallery','caption'=>'','alt_text'=>'','display_order'=>count($media)+1];foreach($media as$m):?>
<form method="post" class="adm-card" style="padding:16px;margin-bottom:16px"><?= csrf_input_field() ?><input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>"><p><?= $m['id']?$e($m['file_path']):'Add existing approved media asset' ?></p>
<?php if(!$m['id']):?><label>Asset path <input name="path" required placeholder="/images/projects/photo.webp"></label><?php endif;?>
<label>Stage <select name="stage"><?php foreach(['before','in_progress','completed','gallery','video']as$s):?><option value="<?= $e($s) ?>" <?= $s===$m['media_stage']?'selected':'' ?>><?= $e($s) ?></option><?php endforeach;?></select></label>
<label>Caption <input name="caption" value="<?= $e($m['caption']) ?>"></label><label>Image description <input name="alt" value="<?= $e($m['alt_text']) ?>" required></label><label>Order <input type="number" name="order" value="<?= (int)$m['display_order'] ?>"></label><button>Save media</button></form>
<?php endforeach;require __DIR__.'/footer.php';?>
