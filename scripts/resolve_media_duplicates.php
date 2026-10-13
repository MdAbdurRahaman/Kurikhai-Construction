<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../includes/db.php';
try{
 if(!in_array('--archive-and-merge',$argv,true))throw new RuntimeException('After a database backup, use --archive-and-merge. Redundant same-project/file metadata is archived, not lost.');
 db_exec('CREATE TABLE IF NOT EXISTS project_media_duplicates_archive (original_id INT UNSIGNED PRIMARY KEY,record_json LONGTEXT NOT NULL,archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
 if((int)DB::query('SELECT GET_LOCK(?,10)',['tabeeb-migrations-'.substr(hash('sha256',DB_NAME),0,32)])->fetchColumn()!==1)throw new RuntimeException('Migration already active');
 $count=DB::transaction(function($pdo){$groups=DB::query('SELECT project_id,file_path FROM project_media GROUP BY project_id,file_path HAVING COUNT(*)>1')->fetchAll();$n=0;foreach($groups as$g){$rows=DB::query('SELECT * FROM project_media WHERE project_id=? AND file_path=? ORDER BY id FOR UPDATE',[$g['project_id'],$g['file_path']])->fetchAll();array_shift($rows);foreach($rows as$r){db_exec('INSERT IGNORE INTO project_media_duplicates_archive(original_id,record_json) VALUES (?,?)',[$r['id'],json_encode($r,JSON_THROW_ON_ERROR)]);db_exec('DELETE FROM project_media WHERE id=?',[$r['id']]);$n++;}}return$n;});
 echo "$count redundant image references archived; lowest IDs retained. Review the archive before releasing.\n";
}catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}finally{try{DB::query('SELECT RELEASE_LOCK(?)',['tabeeb-migrations-'.substr(hash('sha256',DB_NAME),0,32)]);}catch(Throwable$ignored){}}
