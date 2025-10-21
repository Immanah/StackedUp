<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = $mysqli->prepare("SELECT * FROM messages WHERE message_id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$msg = $stmt->get_result()->fetch_assoc();
?>
<div class="card">
  <h3>Message #<?= e($msg['message_id']) ?></h3>
  <p><strong>Name:</strong> <?= e($msg['name']) ?></p>
  <p><strong>Email:</strong> <?= e($msg['email']) ?></p>
  <p><strong>Phone:</strong> <?= e($msg['phone']) ?></p>
  <p><strong>Channel:</strong> <?= e($msg['channel']) ?></p>
  <hr>
  <p><?= nl2br(e($msg['message'])) ?></p>
  <p><small>Sent: <?= e($msg['created_at']) ?></small></p>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
