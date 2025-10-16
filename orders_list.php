<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

// FIXED: Use order_status instead of status
$statusFilter = $mysqli->real_escape_string($_GET['status'] ?? '');
$where = "1=1";
if ($statusFilter) $where .= " AND o.order_status = '{$statusFilter}'";

// FIXED: Use order_status instead of status, and user_id instead of id
$sql = "SELECT o.order_id, o.total_amount, o.order_status, o.payment_status, o.created_at, 
               u.first_name, u.last_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.user_id 
        WHERE {$where} 
        ORDER BY o.created_at DESC 
        LIMIT 500";
$res = $mysqli->query($sql);
?>
<div class="card">
  <h3>Orders</h3>
  
  <!-- Status Filter -->
  <div style="margin-bottom: 20px;">
    <form method="get" style="display: flex; gap: 12px; align-items: center;">
      <label><strong>Filter by Status:</strong></label>
      <select name="status" onchange="this.form.submit()">
        <option value="">All Orders</option>
        <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="processing" <?= $statusFilter == 'processing' ? 'selected' : '' ?>>Processing</option>
        <option value="completed" <?= $statusFilter == 'completed' ? 'selected' : '' ?>>Completed</option>
        <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
      </select>
      <?php if ($statusFilter): ?>
        <a href="orders_list.php" class="btn" style="background: #6b7280; padding: 6px 12px;">Clear Filter</a>
      <?php endif; ?>
    </form>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Customer</th>
        <th>Total</th>
        <th>Order Status</th>
        <th>Payment Status</th>
        <th>When</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($o = $res->fetch_assoc()): ?>
        <tr>
          <td><strong>#<?= e($o['order_id']) ?></strong></td>
          <td><?= e(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?></td>
          <td><strong>R<?= e(number_format($o['total_amount'], 2)) ?></strong></td>
          <td>
            <select class="order-status" data-order-id="<?= e($o['order_id']) ?>">
              <?php 
              $statuses = ['pending', 'processing', 'completed', 'cancelled']; 
              foreach ($statuses as $s): 
                $statusClass = [
                  'pending' => 'status-pending',
                  'processing' => 'status-processing', 
                  'completed' => 'status-completed',
                  'cancelled' => 'status-cancelled'
                ][$s] ?? '';
              ?>
                <option value="<?= $s ?>" <?= $s == $o['order_status'] ? 'selected' : '' ?>>
                  <?= ucfirst($s) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <span class="status-badge status-<?= e($o['payment_status']) ?>">
              <?= ucfirst($o['payment_status']) ?>
            </span>
          </td>
          <td><?= e(date('M j, Y g:i A', strtotime($o['created_at']))) ?></td>
          <td>
            <a href="order_view.php?id=<?= e($o['order_id']) ?>" class="btn" style="padding: 4px 8px; font-size: 12px;">View</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <?php if ($res->num_rows === 0): ?>
    <div style="text-align: center; padding: 40px; color: #6b7280;">
      <h4>No orders found</h4>
      <p><?= $statusFilter ? "No orders with status '{$statusFilter}'" : 'No orders in the system yet' ?></p>
      <?php if ($statusFilter): ?>
        <a href="orders_list.php" class="btn">View All Orders</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<style>
.table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
}

.table th,
.table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}

.table th {
  background: #f8fafc;
  font-weight: 600;
  color: #374151;
}

.order-status {
  padding: 4px 8px;
  border-radius: 4px;
  border: 1px solid #d1d5db;
  background: white;
  cursor: pointer;
}

.order-status:focus {
  outline: none;
  border-color: #262c36ff;
}

.status-badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-processing { background: #dbeafe; color: #1e40af; }
.status-completed { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.status-paid { background: #dcfce7; color: #166534; }
.status-failed { background: #fee2e2; color: #991b1b; }

.btn {
  background: #14181dff;
  color: white;
  border: none;
  border-radius: 6px;
  padding: 0.5rem 1rem;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  font-size: 0.9rem;
}

.btn:hover {
  background: #222733ff;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>

<script>
document.querySelectorAll('.order-status').forEach(sel => {
  sel.addEventListener('change', () => {
    const id = sel.dataset.orderId;
    const st = sel.value;
    
    if (!confirm(`Change order #${id} status to "${st}"?`)) {
      // Reset to original value if user cancels
      sel.blur();
      return;
    }
    
    fetch('ajax_update_order_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: id, 
        status: st, 
        csrf: '<?= csrf() ?>'
      })
    })
    .then(r => r.json())
    .then(d => { 
      if (d.ok) {
        // Update the status badge visually
        const statusCell = sel.closest('td');
        statusCell.innerHTML = `
          <select class="order-status" data-order-id="${id}">
            <option value="pending" ${st === 'pending' ? 'selected' : ''}>Pending</option>
            <option value="processing" ${st === 'processing' ? 'selected' : ''}>Processing</option>
            <option value="completed" ${st === 'completed' ? 'selected' : ''}>Completed</option>
            <option value="cancelled" ${st === 'cancelled' ? 'selected' : ''}>Cancelled</option>
          </select>
        `;
        
        // Re-attach event listener to the new select
        const newSelect = statusCell.querySelector('.order-status');
        newSelect.addEventListener('change', arguments.callee);
        
        // Show success message
        showNotification(`Order #${id} status updated to ${st}`, 'success');
      } else {
        alert('Failed to update order status');
        // Reset select on error
        location.reload();
      }
    })
    .catch(() => {
      alert('Error updating order status');
      location.reload();
    });
  });
});

function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 6px;
    color: white;
    font-weight: 600;
    z-index: 1000;
    background: ${type === 'success' ? '#10b981' : '#3b82f6'};
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  `;
  notification.textContent = message;
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.remove();
  }, 3000);
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>