<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

$id = (int)($_GET['id'] ?? 0);

// Fix: Use user_id instead of id
$stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1"); 
$stmt->bind_param('i', $id); 
$stmt->execute(); 
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "<div class='card' style='color:red;'>Customer not found.</div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// Fix: Use user_id in the orders query
$orders = $mysqli->query("SELECT * FROM orders WHERE user_id = {$id} ORDER BY created_at DESC");
?>
<div class="card">
  <h3>Customer Details</h3>
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
    <div>
      <h4>Personal Information</h4>
      <p><strong>Name:</strong> <?= e($user['first_name'].' '.$user['last_name']) ?></p>
      <p><strong>Email:</strong> <?= e($user['email']) ?></p>
      <p><strong>Phone:</strong> <?= e($user['phone'] ?? 'Not provided') ?></p>
      <p><strong>User ID:</strong> <?= e($user['user_id']) ?></p>
    </div>
    <div>
      <h4>Account Information</h4>
      <p><strong>Role:</strong> <?= e($user['role']) ?></p>
      <p><strong>Email Verified:</strong> <?= $user['email_verified'] ? 'Yes' : 'No' ?></p>
      <p><strong>2FA Enabled:</strong> <?= $user['twofa_enabled'] ? 'Yes' : 'No' ?></p>
      <p><strong>Joined:</strong> <?= e($user['created_at']) ?></p>
      <?php if ($user['last_login']): ?>
        <p><strong>Last Login:</strong> <?= e($user['last_login']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <h4>Order History</h4>
  <?php if ($orders->num_rows > 0): ?>
    <table class="table">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Total Amount</th>
          <th>Payment Status</th>
          <th>Order Status</th>
          <th>Delivery Method</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($o = $orders->fetch_assoc()): ?>
          <tr>
            <td>
              <a href="order_view.php?id=<?= e($o['order_id']) ?>" style="color:#0369a1;">
                #<?= e($o['order_id']) ?>
              </a>
            </td>
            <td>R<?= e(number_format($o['total_amount'], 2)) ?></td>
            <td>
              <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                <?php 
                  if ($o['payment_status'] === 'paid') echo 'background:#dcfce7; color:#166534;';
                  elseif ($o['payment_status'] === 'failed') echo 'background:#fee2e2; color:#991b1b;';
                  else echo 'background:#fef3c7; color:#92400e;';
                ?>">
                <?= e(ucfirst($o['payment_status'])) ?>
              </span>
            </td>
            <td>
              <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px;
                <?php
                  if ($o['order_status'] === 'completed') echo 'background:#dcfce7; color:#166534;';
                  elseif ($o['order_status'] === 'cancelled') echo 'background:#fee2e2; color:#991b1b;';
                  elseif ($o['order_status'] === 'processing') echo 'background:#dbeafe; color:#1e40af;';
                  else echo 'background:#fef3c7; color:#92400e;';
                ?>">
                <?= e(ucfirst($o['order_status'])) ?>
              </span>
            </td>
            <td><?= e(ucfirst($o['delivery_method'])) ?></td>
            <td><?= e(date('M j, Y g:i A', strtotime($o['created_at']))) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div style="text-align: center; padding: 20px; color: #6b7280;">
      <p>No orders found for this customer.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>