<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';
$res=$mysqli->query("SELECT a.log_id,a.action,a.context,a.created_at,ad.username FROM activity_log a LEFT JOIN admins ad ON a.admin_id=ad.id ORDER BY a.created_at DESC LIMIT 500");
?>
<div class="card">
  <h3>Activity Log</h3>
  <table class="table"><thead><tr><th>ID</th><th>Admin</th><th>Action</th><th>Context</th><th>When</th></tr></thead>
  <tbody><?php while($r=$res->fetch_assoc()):?>
    <tr><td><?=$r['log_id']?></td><td><?=e($r['username'])?></td><td><?=e($r['action'])?></td><td><pre style="white-space:pre-wrap"><?=e($r['context'])?></pre></td><td><?=$r['created_at']?></td></tr>
  <?php endwhile;?></tbody></table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
