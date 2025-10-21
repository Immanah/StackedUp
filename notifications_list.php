<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

if (isset($_GET['mark']) && $_GET['mark'] === 'read' && isset($_GET['id']) && check_csrf($_GET['_csrf'] ?? '')) {
  $id = (int)$_GET['id'];
  $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
  $stmt->bind_param('i',$id); $stmt->execute();
  header('Location: notifications_list.php'); exit;
}

if (isset($_GET['markall']) && check_csrf($_GET['_csrf'] ?? '')) {
  $mysqli->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
  header('Location: notifications_list.php'); exit;
}

$res = $mysqli->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 200");
?>
<div class="card">
  <h3>Notifications <a class="btn" href="?markall=1&_csrf=<?= csrf() ?>" style="float:right">Mark all read</a></h3>
  <table class="table">
    <thead><tr><th></th><th>Title</th><th>Type</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
      <?php while ($n = $res->fetch_assoc()): ?>
      <tr style="<?= $n['is_read'] ? '' : 'font-weight:600' ?>">
        <td><?= $n['is_read'] ? '' : '●' ?></td>
        <td><?= e($n['title']) ?></td>
        <td><?= e($n['type']) ?></td>
        <td><?= e($n['created_at']) ?></td>
        <td>
          <a href="?mark=read&id=<?= e($n['notification_id']) ?>&_csrf=<?= csrf() ?>">Mark read</a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
