<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

$res = $mysqli->query("SELECT * FROM messages ORDER BY created_at DESC LIMIT 500");
?>
<div class="card">
  <h3>Messages</h3>
  <table class="table">
    <thead>
      <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Channel</th><th>Message</th><th>Date</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php while ($m = $res->fetch_assoc()): ?>
      <tr>
        <td><?= e($m['message_id']) ?></td>
        <td><?= e($m['name']) ?></td>
        <td><?= e($m['email']) ?></td>
        <td><?= e($m['phone']) ?></td>
        <td><?= e($m['channel']) ?></td>
        <td><?= e(substr($m['message'],0,80)) ?>...</td>
        <td><?= e($m['created_at']) ?></td>
        <td>
          <a href="message_view.php?id=<?= e($m['message_id']) ?>">View</a> |
          <a href="message_delete.php?id=<?= e($m['message_id']) ?>&_csrf=<?= csrf() ?>" data-confirm="Delete this message?">Delete</a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
