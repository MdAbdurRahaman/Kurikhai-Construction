<?php
require_once __DIR__.'/../includes/data.php';require_login();require_permission('content.manage');
$pageTitle='Content review';$currentPage='content-review';require __DIR__.'/header.php';
$rows=DB::query('SELECT * FROM content_review_items ORDER BY id')->fetchAll();
?><h2>Content requiring factual review</h2><p>Exact unchanged legacy claims were corrected with archived prior values. These remaining records contain wording that needs client or manufacturer evidence. Edit them through the usual content editor; client changes were preserved.</p>
<?php foreach($rows as$r):?><p><?= htmlspecialchars($r['entity_type'].' / '.$r['entity_id'].' — '.$r['reason']) ?></p><?php endforeach;?>
<?php if(!$rows):?><p>No flagged records.</p><?php endif;require __DIR__.'/footer.php';?>
