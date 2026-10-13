<?php
require_once __DIR__.'/includes/data.php';
$path=(string)($_GET['path']??'');
if(!preg_match('~^/images/projects/[a-zA-Z0-9/_ .-]+\.(webp|png|jpe?g|mp4)$~i',$path)||str_contains($path,'..')){http_response_code(404);exit;}
$full=realpath(__DIR__.$path);$base=realpath(__DIR__.'/images/projects');
if(!$full||!$base||!str_starts_with($full,$base.DIRECTORY_SEPARATOR)||!is_file($full)){http_response_code(404);exit;}
$staff=is_logged_in()&&has_permission('projects.manage');
try{$approved=DB::query("SELECT p.id FROM projects p WHERE p.publication_state='published' AND p.approval_status='approved' AND (p.featured_image=? OR p.video_path=? OR p.video_poster=? OR EXISTS(SELECT 1 FROM project_media m WHERE m.project_id=p.id AND m.file_path=?)) LIMIT 1",[$path,$path,$path,$path])->fetchColumn();}catch(Throwable$e){$approved=false;}
if(!$approved&&!$staff){http_response_code(404);exit;}
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($full));header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store'); // Approval changes take effect immediately.
$size=filesize($full);$start=0;$end=$size-1;
if(isset($_SERVER['HTTP_RANGE'])){
 if(!preg_match('/^bytes=(\d+)-(\d*)$/',$_SERVER['HTTP_RANGE'],$m)){http_response_code(416);exit;}
 $start=(int)$m[1];$end=$m[2]===''?$end:min((int)$m[2],$end);if($start>$end){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}
 http_response_code(206);header("Content-Range: bytes $start-$end/$size");
}
header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));
$f=fopen($full,'rb');fseek($f,$start);$left=$end-$start+1;while($left>0&&!feof($f)){ $chunk=fread($f,min(65536,$left));if($chunk===false)break;echo$chunk;$left-=strlen($chunk);}fclose($f);
