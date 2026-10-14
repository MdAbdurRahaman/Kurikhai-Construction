<?php
require_once dirname(__DIR__).'/includes/data.php';
require_login();
$pageTitle = 'Your Portal Access';
require __DIR__.'/header.php';
?>
<div class="table-card" style="padding:24px;">
<h2>Your dashboard access</h2>
<p>Use the available links in the sidebar. If you need another section, ask your administrator to update your account access.</p>
<p><a href="change-password.php">Change your password</a></p>
</div>
<?php require __DIR__.'/footer.php'; ?>
