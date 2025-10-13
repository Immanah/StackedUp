<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = $mysqli->prepare("SELECT o.*, u.first_name, u.last_name, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.order_id = ? LIMIT 1");
$stmt->bind_param('i',$id); $stmt->execute(); $order = $stmt->get_result()->fetch_assoc();
$items = $mysqli->query("SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id WHERE order_id = {$id}");
?>
<div class="card">
  <h3>Order #<?= e($order['order_id']) ?> <small style="color:#666">Total R<?= e(number_format($order['total_amount'],2)) ?></small></h3>
  <p>Customer: <?= e($order['first_name'].' '.$order['last_name']) ?> (<?= e($order['email']) ?>)</p>
  <p>Status: <?= e($order['status']) ?> | Created: <?= e($order['created_at']) ?></p>
  <h4>Items</h4>
  <table class="table"><thead><tr><th>Product</th><th>Qty</th><th>Price</th></tr></thead><tbody>
    <?php while ($it = $items->fetch_assoc()): ?>
      <tr><td><?= e($it['name']) ?></td><td><?= e($it['quantity']) ?></td><td>R<?= e(number_format($it['price'],2)) ?></td></tr>
    <?php endwhile; ?>
  </tbody></table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
