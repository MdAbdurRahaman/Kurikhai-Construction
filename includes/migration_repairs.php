<?php
// This runs under the migration advisory lock. Every automatic repair preserves prior state.
function repair_history_record(string $key, string $type, string $id, array $before, ?array $after=null): void {
    db_exec('INSERT IGNORE INTO repair_history (repair_key,entity_type,entity_id,before_json,after_json) VALUES (?,?,?,?,?)',[$key,$type,$id,json_encode($before,JSON_THROW_ON_ERROR),$after===null?null:json_encode($after,JSON_THROW_ON_ERROR)]);
}
function media_constraint_preflight(PDO $pdo): void {
    if (!db_val("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='project_media'")) return;
    $duplicates=DB::query('SELECT project_id,file_path,COUNT(*) AS n FROM project_media GROUP BY project_id,file_path HAVING COUNT(*)>1')->fetchAll();
    if ($duplicates) throw new RuntimeException('Duplicate project media detected. Run scripts/resolve_media_duplicates.php after a database backup, review its archive, then retry migration. No records were removed.');
}
function production_evidence_repairs(): void {
    $legacy=DB::query("SELECT * FROM integration_verifications WHERE service_type='gsc_prefix' AND status='Verified with dated evidence' AND verified_at IS NULL AND evidence_summary='Verified via legacy file googlea9c88d1e38989379.html'")->fetchAll();
    foreach($legacy as $row) DB::transaction(function()use($row){
        repair_history_record('legacy-gsc-'.$row['id'],'integration_verifications',(string)$row['id'],$row);
        db_exec("UPDATE integration_verifications SET status='Verification pending',evidence_summary='Legacy file preserved; Google account verification evidence is still required' WHERE id=? AND verified_at IS NULL",[$row['id']]);
    });
    $legacy=DB::query("SELECT * FROM marketing_baseline WHERE collection_status='Measured' AND metric_value IS NULL AND collected_at IS NULL AND evidence_reference IS NULL AND metric_key IN ('leads_persisted_total','leads_qualified_total','leads_won_total')")->fetchAll();
    foreach($legacy as $row) DB::transaction(function()use($row){
        repair_history_record('legacy-baseline-'.$row['id'],'marketing_baseline',(string)$row['id'],$row);
        db_exec("UPDATE marketing_baseline SET collection_status='Not available',status_reason='Legacy seed was labelled measured without collection evidence' WHERE id=?",[$row['id']]);
    });
}
function repair_legacy_content(): void {
    foreach(['posts','services'] as $table) {
        $legacyFile=BOOTSTRAP_DIR.'/legacy_'.$table.'.json';$cleanFile=BOOTSTRAP_DIR.'/'.$table.'.json';
        if(!is_file($legacyFile)||!is_file($cleanFile))continue;
        $old=json_decode(file_get_contents($legacyFile),true,512,JSON_THROW_ON_ERROR);
        $clean=array_column(json_decode(file_get_contents($cleanFile),true,512,JSON_THROW_ON_ERROR),null,'id');
        foreach($old as $seed) {
            if(!isset($clean[$seed['id']]))continue;
            $row=DB::query("SELECT * FROM `$table` WHERE id=?",[$seed['id']])->fetch();if(!$row)continue;
            $fields=$table==='posts'?['content'=>'content','excerpt'=>'excerpt']:['overview'=>'overview','features'=>'features_json','process'=>'process_json','faqs'=>'faqs_json','og_description'=>'og_description'];
            $updates=[];
            foreach($fields as $jsonKey=>$dbKey) {
                $oldVal=$seed[$jsonKey]??null;$newVal=$clean[$seed['id']][$jsonKey]??null;
                $current=$row[$dbKey]??null;
                if(is_array($oldVal))$current=json_decode((string)$current,true);
                if($oldVal!==$newVal && $current===$oldVal) $updates[$dbKey]=is_array($newVal)?json_encode($newVal,JSON_UNESCAPED_UNICODE):$newVal;
            }
            if($updates) DB::transaction(function()use($table,$row,$updates){
                repair_history_record('content-'.hash('sha256',$table.$row['id'].json_encode($updates)), $table,(string)$row['id'],$row,$updates);
                $set=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($updates)));
                db_exec("UPDATE `$table` SET $set WHERE id=?",array_merge(array_values($updates),[$row['id']]));
            });
            $text=implode(' ',array_map(fn($v)=>is_string($v)?$v:'',DB::query("SELECT * FROM `$table` WHERE id=?",[$row['id']])->fetch()));
            if(preg_match('/25% lower|100%.*(?:deposit|handover|refund)|written workmanship warranty|in-house master|will never warp/i',$text))
                db_exec('INSERT IGNORE INTO content_review_items(entity_type,entity_id,reason) VALUES (?,?,?)',[$table,$row['id'],'Review remaining unsupported guarantees/team or product claims; preserve client edits.']);
        }
    }
}
function repair_rate_limit_uniqueness(): void {
    $exists=db_val("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rate_limits'");if(!$exists)return;
    db_exec('CREATE TABLE IF NOT EXISTS repair_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,repair_key VARCHAR(190) NOT NULL UNIQUE,entity_type VARCHAR(80) NOT NULL,entity_id VARCHAR(100) NOT NULL,before_json LONGTEXT NOT NULL,after_json LONGTEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $groups=DB::query('SELECT rate_key,action_type FROM rate_limits GROUP BY rate_key,action_type HAVING COUNT(*)>1')->fetchAll();
    foreach($groups as$g)DB::transaction(function()use($g){
        $rows=DB::query('SELECT * FROM rate_limits WHERE rate_key=? AND action_type=? ORDER BY reset_at DESC,id ASC FOR UPDATE',[$g['rate_key'],$g['action_type']])->fetchAll();$keep=array_shift($rows);$attempts=(int)$keep['attempts'];
        foreach($rows as$r){repair_history_record('rate-duplicate-'.$r['id'],'rate_limits',(string)$r['id'],$r);if($r['reset_at']>date('Y-m-d H:i:s'))$attempts+=(int)$r['attempts'];db_exec('DELETE FROM rate_limits WHERE id=?',[$r['id']]);}
        db_exec('UPDATE rate_limits SET attempts=? WHERE id=?',[min(1000000,$attempts),$keep['id']]);
    });
}

function verify_project_media_integrity(): void {
    if(DB::query('SELECT m.id FROM project_media m LEFT JOIN projects p ON p.id=m.project_id WHERE p.id IS NULL LIMIT 1')->fetchColumn()) throw new RuntimeException('Orphan project media detected. For the known client seed run migrate.php --seed; otherwise reconcile its parent from a backup. Activation is blocked; no media was removed.');
}
