<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

// Fix: Use user_id instead of id
$res = $mysqli->query("SELECT user_id, first_name, last_name, email, created_at, phone FROM users ORDER BY created_at DESC LIMIT 500");
?>
<div class="card">
  <h3>Customers</h3>
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Joined</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($u = $res->fetch_assoc()): ?>
        <tr>
          <td><?= e($u['user_id']) ?></td> <!-- Fix: user_id instead of id -->
          <td><?= e($u['first_name'].' '.$u['last_name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['phone'] ?? 'N/A') ?></td>
          <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
          <td>
            <a href="customer_view.php?id=<?= e($u['user_id']) ?>" class="btn" style="padding:4px 8px;font-size:12px;">View</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>