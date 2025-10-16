<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

$errors = [];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $order_id = (int)$_POST['order_id'];
        $status = $mysqli->real_escape_string($_POST['status']);
        
        $stmt = $mysqli->prepare("UPDATE custom_orders SET status = ?, updated_at = NOW() WHERE custom_order_id = ?");
        $stmt->bind_param('si', $status, $order_id);
        
        if ($stmt->execute()) {
            // Log the activity
            $log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, 'custom_order_status_update', ?)");
            $ctx = json_encode(['custom_order_id' => $order_id, 'status' => $status]);
            $log->bind_param('is', $_SESSION['admin_id'], $ctx);
            $log->execute();
            
            header('Location: custom_orders_list.php?updated=1');
            exit;
        } else {
            $errors[] = 'Failed to update order status.';
        }
    }
}

// Handle filters
$status_filter = $mysqli->real_escape_string($_GET['status'] ?? '');
$where = "1=1";
if ($status_filter && $status_filter !== 'all') {
    $where .= " AND co.status = '{$status_filter}'";
}

// Fetch custom orders with user information
$sql = "SELECT co.*, 
               u.first_name, 
               u.last_name, 
               u.email,
               u.phone
        FROM custom_orders co
        LEFT JOIN users u ON co.user_id = u.user_id
        WHERE $where
        ORDER BY co.created_at DESC";

$res = $mysqli->query($sql);
?>

<div class="card">
    <h3>Custom Dress Orders</h3>
    
    <?php if ($errors): ?>
        <div style="color:#b91c1c; background:#fef2f2; padding:1rem; border-radius:6px; margin-bottom:1.5rem;">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated'])): ?>
        <div style="color:green; background:#f0fff4; padding:1rem; border-radius:6px; margin-bottom:1.5rem;">
            Order status updated successfully!
        </div>
    <?php endif; ?>

    <!-- Status Filter -->
    <div style="margin-bottom: 20px;">
        <form method="get" style="display: flex; gap: 12px; align-items: center;">
            <label><strong>Filter by Status:</strong></label>
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?= !$status_filter || $status_filter === 'all' ? 'selected' : '' ?>>All Orders</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_consultation" <?= $status_filter === 'in_consultation' ? 'selected' : '' ?>>In Consultation</option>
                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <?php if ($status_filter && $status_filter !== 'all'): ?>
                <a href="custom_orders_list.php" class="btn" style="background: #6b7280; padding: 6px 12px;">Clear Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="custom-orders-container">
        <?php if ($res->num_rows > 0): ?>
            <?php while ($order = $res->fetch_assoc()): ?>
                <div class="custom-order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <h4>Order #<?= e($order['custom_order_id']) ?></h4>
                            <div class="customer-info">
                                <strong>Customer:</strong> 
                                <?= e($order['first_name'] . ' ' . $order['last_name']) ?>
                                (<?= e($order['email']) ?>)
                                <?php if ($order['phone']): ?>
                                    | <strong>Phone:</strong> <?= e($order['phone']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="order-date">
                                Submitted: <?= e(date('M j, Y g:i A', strtotime($order['created_at']))) ?>
                            </div>
                        </div>
                        <div class="order-status">
                            <form method="post" class="status-form">
                                <input type="hidden" name="_csrf" value="<?= csrf() ?>">
                                <input type="hidden" name="order_id" value="<?= e($order['custom_order_id']) ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_consultation" <?= $order['status'] === 'in_consultation' ? 'selected' : '' ?>>In Consultation</option>
                                    <option value="in_progress" <?= $order['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                            <span class="status-badge status-<?= e($order['status']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="order-details">
                        <div class="detail-section">
                            <h5>Order Description</h5>
                            <div class="description-box">
                                <?= nl2br(e($order['description'])) ?>
                            </div>
                        </div>

                        <div class="details-grid">
                            <?php if ($order['fabric_preference']): ?>
                                <div class="detail-item">
                                    <strong>Fabric Preference:</strong> <?= e($order['fabric_preference']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['budget']): ?>
                                <div class="detail-item">
                                    <strong>Budget:</strong> R<?= e(number_format($order['budget'], 2)) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['notes']): ?>
                                <div class="detail-item full-width">
                                    <strong>Additional Notes:</strong> <?= e($order['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="measurements-section">
                            <h5>Measurements</h5>
                            <div class="measurements-grid">
                                <?php if ($order['bust']): ?>
                                    <div class="measurement-item">
                                        <strong>Bust:</strong> <?= e($order['bust']) ?> cm
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['waist']): ?>
                                    <div class="measurement-item">
                                        <strong>Waist:</strong> <?= e($order['waist']) ?> cm
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['hips']): ?>
                                    <div class="measurement-item">
                                        <strong>Hips:</strong> <?= e($order['hips']) ?> cm
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['height']): ?>
                                    <div class="measurement-item">
                                        <strong>Height:</strong> <?= e($order['height']) ?> cm
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['sleeve_length']): ?>
                                    <div class="measurement-item">
                                        <strong>Sleeve Length:</strong> <?= e($order['sleeve_length']) ?> cm
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['shoulder_width']): ?>
                                    <div class="measurement-item">
                                        <strong>Shoulder Width:</strong> <?= e($order['shoulder_width']) ?> cm
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($order['image_url']): ?>
                            <div class="image-section">
                                <h5>Reference Image</h5>
                                <div class="reference-image">
                                    <img src="../<?= e($order['image_url']) ?>" 
                                         alt="Custom order reference image"
                                         onclick="openImageModal('../<?= e($order['image_url']) ?>')">
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="order-actions">
                        <button class="btn btn-contact" 
                                onclick="contactCustomer('<?= e($order['email']) ?>', '<?= e($order['first_name'] . ' ' . $order['last_name']) ?>')">
                            📧 Contact Customer
                        </button>
                        
                        <?php if ($order['phone']): ?>
                            <button class="btn btn-call" 
                                    onclick="callCustomer('<?= e($order['phone']) ?>')">
                                📞 Call Customer
                            </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-danger" 
                                onclick="deleteOrder(<?= e($order['custom_order_id']) ?>)">
                            🗑️ Delete Order
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #6b7280;">
                <h4>No custom orders found</h4>
                <p><?= $status_filter ? "No orders with status '{$status_filter}'" : 'No custom dress orders yet' ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="close">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<style>
.custom-orders-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.custom-order-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: box-shadow 0.2s;
}

.custom-order-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f3f4f6;
}

.order-info h4 {
    margin: 0 0 8px 0;
    color: #111827;
    font-size: 18px;
}

.customer-info, .order-date {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 4px;
}

.order-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.status-form select {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: white;
    font-size: 14px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: capitalize;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-in_consultation { background: #dbeafe; color: #1e40af; }
.status-in_progress { background: #fce7f3; color: #be185d; }
.status-completed { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }

.order-details {
    margin-bottom: 20px;
}

.detail-section {
    margin-bottom: 20px;
}

.detail-section h5 {
    margin: 0 0 12px 0;
    color: #374151;
    font-size: 16px;
    font-weight: 600;
}

.description-box {
    background: #f8fafc;
    padding: 16px;
    border-radius: 8px;
    border-left: 4px solid #3b82f6;
    line-height: 1.5;
    color: #4b5563;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.detail-item {
    padding: 12px;
    background: #f9fafb;
    border-radius: 6px;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.measurements-section h5 {
    margin-bottom: 12px;
}

.measurements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

.measurement-item {
    padding: 10px;
    background: #f0f9ff;
    border-radius: 6px;
    border: 1px solid #e0f2fe;
}

.image-section h5 {
    margin-bottom: 12px;
}

.reference-image {
    max-width: 300px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    cursor: pointer;
}

.reference-image img {
    width: 100%;
    height: auto;
    display: block;
    transition: transform 0.2s;
}

.reference-image:hover img {
    transform: scale(1.05);
}

.order-actions {
    display: flex;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid #f3f4f6;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-contact {
    background: #3b82f6;
    color: white;
}

.btn-call {
    background: #10b981;
    color: white;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.9);
}

.modal-content {
    margin: auto;
    display: block;
    width: 80%;
    max-width: 700px;
    max-height: 80vh;
    object-fit: contain;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.close {
    position: absolute;
    top: 20px;
    right: 35px;
    color: #fff;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 1001;
}

.close:hover {
    color: #ccc;
}

@media (max-width: 768px) {
    .order-header {
        flex-direction: column;
        gap: 12px;
    }
    
    .order-status {
        align-items: flex-start;
    }
    
    .order-actions {
        flex-direction: column;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Image modal functionality
function openImageModal(src) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    modal.style.display = 'block';
    modalImg.src = src;
}

document.querySelector('.close').addEventListener('click', function() {
    document.getElementById('imageModal').style.display = 'none';
});

// Close modal when clicking outside the image
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});

function contactCustomer(email, name) {
    const subject = encodeURIComponent(`Regarding your custom dress order - OZYDE`);
    const body = encodeURIComponent(`Dear ${name},\n\n`);
    window.open(`mailto:${email}?subject=${subject}&body=${body}`, '_blank');
}

function callCustomer(phone) {
    if (confirm(`Call customer at ${phone}?`)) {
        window.open(`tel:${phone}`, '_self');
    }
}

function deleteOrder(orderId) {
    if (confirm('Are you sure you want to delete this custom order? This action cannot be undone.')) {
        // Implement delete functionality
        fetch('delete_custom_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId,
                _csrf: '<?= csrf() ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to delete order: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error deleting order: ' + error);
        });
    }
}

// Close modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('imageModal').style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>