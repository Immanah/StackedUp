<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

// Check if payment_status column exists
$check_column = $mysqli->query("SHOW COLUMNS FROM `bookings` LIKE 'payment_status'");
$payment_status_exists = $check_column->num_rows > 0;

// Get filter parameters
$statusFilter = $mysqli->real_escape_string($_GET['status'] ?? '');
$paymentFilter = $mysqli->real_escape_string($_GET['payment'] ?? '');
$dateFilter = $mysqli->real_escape_string($_GET['date'] ?? '');

// Build WHERE clause
$where = "1=1";
if ($statusFilter) {
    $where .= " AND b.status = '{$statusFilter}'";
}
if ($paymentFilter && $payment_status_exists) {
    $where .= " AND b.payment_status = '{$paymentFilter}'";
}
if ($dateFilter) {
    if ($dateFilter === 'today') {
        $where .= " AND DATE(b.start_date) = CURDATE()";
    } elseif ($dateFilter === 'upcoming') {
        $where .= " AND b.start_date >= CURDATE()";
    } elseif ($dateFilter === 'past') {
        $where .= " AND b.end_date < CURDATE()";
    }
}

// Get bookings count for statistics
$total_bookings = $mysqli->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
$pending_bookings = $payment_status_exists ? 
    $mysqli->query("SELECT COUNT(*) as count FROM bookings WHERE payment_status = 'pending'")->fetch_assoc()['count'] : 0;
$paid_bookings = $payment_status_exists ? 
    $mysqli->query("SELECT COUNT(*) as count FROM bookings WHERE payment_status = 'paid'")->fetch_assoc()['count'] : $total_bookings;

// Get bookings with filters
$sql = "
    SELECT 
        b.*,
        p.name as product_name,
        p.image,
        p.rental_price,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.user_id
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN products p ON b.product_id = p.product_id
    WHERE {$where}
    ORDER BY b.created_at DESC
    LIMIT 500
";

$bookings = $mysqli->query($sql);
?>

<div class="card">
    <h3><i class="fas fa-chart-bar"></i> Bookings Management</h3>
    
    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="background: #e2e3e5; border-radius: 8px; padding: 15px; text-align: center;">
            <div style="font-size: 1.5rem; font-weight: bold; color: #383d41;">
                <i class="fas fa-calendar-alt" style="color: #383d41; margin-right: 8px;"></i><?= $total_bookings ?>
            </div>
            <small>Total Bookings</small>
        </div>
        
        <?php if ($payment_status_exists): ?>
        <div style="background: #fff3cd; border-radius: 8px; padding: 15px; text-align: center;">
            <div style="font-size: 1.5rem; font-weight: bold; color: #856404;">
                <i class="fas fa-clock" style="color: #856404; margin-right: 8px;"></i><?= $pending_bookings ?>
            </div>
            <small>Pending Payment</small>
        </div>
        
        <div style="background: #d4edda; border-radius: 8px; padding: 15px; text-align: center;">
            <div style="font-size: 1.5rem; font-weight: bold; color: #155724;">
                <i class="fas fa-check-circle" style="color: #155724; margin-right: 8px;"></i><?= $paid_bookings ?>
            </div>
            <small>Paid Bookings</small>
        </div>
        <?php endif; ?>
        
        <div style="background: #d7f6dfff; border-radius: 8px; padding: 15px; text-align: center;">
            <div style="font-size: 1.5rem; font-weight: bold; color: #0c5460;">
                <i class="fas fa-arrow-up" style="color: #0c5460; margin-right: 8px;"></i>
                <?= $mysqli->query("SELECT COUNT(*) as count FROM bookings WHERE start_date >= CURDATE()")->fetch_assoc()['count'] ?>
            </div>
            <small>Upcoming</small>
        </div>
    </div>

    <!-- Filters -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h4 style="margin-top: 0;"><i class="fas fa-filter"></i> Filters</h4>
        <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Booking Status</label>
                <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">All Statuses</option>
                    <option value="booked" <?= $statusFilter == 'booked' ? 'selected' : '' ?>>Booked</option>
                    <option value="returned" <?= $statusFilter == 'returned' ? 'selected' : '' ?>>Returned</option>
                    <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <?php if ($payment_status_exists): ?>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Payment Status</label>
                <select name="payment" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">All Payments</option>
                    <option value="pending" <?= $paymentFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $paymentFilter == 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="failed" <?= $paymentFilter == 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <?php endif; ?>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Date Filter</label>
                <select name="date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">All Dates</option>
                    <option value="today" <?= $dateFilter == 'today' ? 'selected' : '' ?>>Today's Rentals</option>
                    <option value="upcoming" <?= $dateFilter == 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="past" <?= $dateFilter == 'past' ? 'selected' : '' ?>>Past Rentals</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn" style="background: #3498db;">
                    <i class="fas fa-check"></i> Apply Filters
                </button>
                <a href="bookings_list.php" class="btn" style="background: #6b7280;">
                    <i class="fas fa-times"></i> Clear All
                </a>
            </div>
        </form>
    </div>

    <!-- Bookings Table -->
    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Dress & Customer</th>
                    <th>Rental Period</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <?php if ($payment_status_exists): ?>
                    <th>Payment</th>
                    <?php endif; ?>
                    <th>Contact</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($bookings->num_rows > 0): ?>
                    <?php while ($booking = $bookings->fetch_assoc()): 
                        $is_upcoming = strtotime($booking['start_date']) > time();
                        $is_active = $booking['status'] == 'booked' && strtotime($booking['end_date']) >= time();
                        $is_past = strtotime($booking['end_date']) < time();
                        
                        // Determine row color based on status
                        $row_style = '';
                        if ($is_active) {
                            $row_style = 'background: #f0fff4;';
                        } elseif ($is_past) {
                            $row_style = 'background: #f8f9fa;';
                        } elseif ($is_upcoming) {
                            $row_style = 'background: #d1ecf1;'; // Same color as upcoming stats card
                        }
                    ?>
                        <tr style="<?= $row_style ?>">
                            <td>
                                <strong>#<?= e($booking['booking_id']) ?></strong>
                                <?php if (!empty($booking['booking_ref'])): ?>
                                    <br><small style="color: #666;">Ref: <?= e($booking['booking_ref']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($booking['image'])): ?>
                                        <img src="<?= e($booking['image']) ?>" alt="<?= e($booking['product_name']) ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: #e2e3e5; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #6b7280;">
                                            <i class="fas fa-tshirt" style="font-size: 1.5rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= e($booking['product_name']) ?></strong>
                                        <br>
                                        <small>
                                            <i class="fas fa-user" style="margin-right: 4px;"></i> <?= e($booking['first_name'] . ' ' . $booking['last_name']) ?>
                                            <?php if ($booking['user_id']): ?>
                                                <a href="customer_view.php?id=<?= e($booking['user_id']) ?>" style="margin-left: 5px; color: #3498db;">(View)</a>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="text-align: center;">
                                    <div style="font-weight: 600;"><?= e(date('M j', strtotime($booking['start_date']))) ?></div>
                                    <div style="color: #666; font-size: 0.8rem;">to</div>
                                    <div style="font-weight: 600;"><?= e(date('M j, Y', strtotime($booking['end_date']))) ?></div>
                                    <div style="color: #666; font-size: 0.8rem;">
                                        <?= round((strtotime($booking['end_date']) - strtotime($booking['start_date'])) / (60 * 60 * 24)) ?> days
                                        <?php if ($is_upcoming): ?>
                                            <br><span style="color: #0c5460;"><i class="fas fa-clock" style="margin-right: 4px;"></i>Upcoming</span>
                                        <?php elseif ($is_active): ?>
                                            <br><span style="color: #155724;"><i class="fas fa-check-circle" style="margin-right: 4px;"></i>Active</span>
                                        <?php elseif ($is_past): ?>
                                            <br><span style="color: #6c757d;"><i class="fas fa-calendar-check" style="margin-right: 4px;"></i>Completed</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong>R<?= e(number_format($booking['total_amount'] ?: $booking['rental_price'], 2)) ?></strong>
                                <?php if ($booking['total_amount'] && $booking['rental_price'] && $booking['total_amount'] != $booking['rental_price']): ?>
                                    <br><small style="color: #666;">Base: R<?= e(number_format($booking['rental_price'], 2)) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select class="booking-status" data-booking-id="<?= e($booking['booking_id']) ?>" 
                                        style="padding: 4px 8px; border-radius: 4px; border: 1px solid #ddd; background: white;">
                                    <option value="booked" <?= $booking['status'] == 'booked' ? 'selected' : '' ?>>Booked</option>
                                    <option value="returned" <?= $booking['status'] == 'returned' ? 'selected' : '' ?>>Returned</option>
                                    <option value="cancelled" <?= $booking['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </td>
                            <?php if ($payment_status_exists): ?>
                            <td>
                                <?php if ($booking['payment_status'] == 'pending'): ?>
                                    <span class="status-badge status-pending">
                                        <i class="fas fa-clock" style="margin-right: 4px;"></i>Pending
                                    </span>
                                    <br>
                                    <button onclick="markBookingAsPaid(<?= e($booking['booking_id']) ?>)" 
                                            class="btn" 
                                            style="background: #27ae60; padding: 2px 6px; font-size: 11px; margin-top: 4px;">
                                        <i class="fas fa-check"></i> Mark Paid
                                    </button>
                                <?php elseif ($booking['payment_status'] == 'paid'): ?>
                                    <span class="status-badge status-completed">
                                        <i class="fas fa-check-circle" style="margin-right: 4px;"></i>Paid
                                    </span>
                                <?php elseif ($booking['payment_status'] == 'failed'): ?>
                                    <span class="status-badge status-cancelled">
                                        <i class="fas fa-times-circle" style="margin-right: 4px;"></i>Failed
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge">Unknown</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <div style="font-size: 0.85rem;">
                                    <i class="fas fa-envelope" style="margin-right: 4px;"></i> <?= e($booking['email']) ?>
                                    <?php if (!empty($booking['phone'])): ?>
                                        <br><i class="fas fa-phone" style="margin-right: 4px;"></i> <?= e($booking['phone']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?= e(date('M j, Y', strtotime($booking['created_at']))) ?>
                                <br><small style="color: #666;"><?= e(date('g:i A', strtotime($booking['created_at']))) ?></small>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px; min-width: 120px;">
                                    <a href="booking_view.php?id=<?= e($booking['booking_id']) ?>" 
                                       class="btn" 
                                       style="padding: 4px 8px; font-size: 12px; background: #3498db;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($payment_status_exists && $booking['payment_status'] == 'pending'): ?>
                                        <button onclick="markBookingAsPaid(<?= e($booking['booking_id']) ?>)" 
                                                class="btn" 
                                                style="background: #27ae60; padding: 4px 8px; font-size: 12px;">
                                            <i class="fas fa-check"></i> Mark Paid
                                        </button>
                                        <button onclick="cancelBooking(<?= e($booking['booking_id']) ?>)" 
                                                class="btn" 
                                                style="background: #e74c3c; padding: 4px 8px; font-size: 12px;">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $payment_status_exists ? '9' : '8' ?>" style="text-align: center; padding: 40px; color: #6b7280;">
                            <h4><i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px;"></i></h4>
                            <h4>No Bookings Found</h4>
                            <p>
                                <?php 
                                if ($statusFilter || $paymentFilter || $dateFilter) {
                                    echo "No bookings match your current filters.";
                                } else {
                                    echo "No bookings have been made yet.";
                                }
                                ?>
                            </p>
                            <?php if ($statusFilter || $paymentFilter || $dateFilter): ?>
                                <a href="bookings_list.php" class="btn">
                                    <i class="fas fa-list"></i> View All Bookings
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary -->
    <?php if ($bookings->num_rows > 0): ?>
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <strong><i class="fas fa-chart-pie"></i> Summary:</strong> 
            Showing <?= $bookings->num_rows ?> booking(s)
            <?php if ($statusFilter): ?> • Status: <?= ucfirst($statusFilter) ?><?php endif; ?>
            <?php if ($paymentFilter): ?> • Payment: <?= ucfirst($paymentFilter) ?><?php endif; ?>
            <?php if ($dateFilter): ?> • Date: <?= ucfirst($dateFilter) ?><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($payment_status_exists): ?>
<script>
function markBookingAsPaid(bookingId) {
    if (!confirm('Mark this booking as paid? This will confirm the payment.')) {
        return;
    }
    
    fetch('ajax_mark_booking_paid.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            booking_id: bookingId,
            csrf: '<?= csrf() ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Booking marked as paid!');
            location.reload();
        } else {
            alert('❌ Error: ' + data.error);
        }
    })
    .catch(() => {
        alert('❌ Network error occurred');
    });
}

function cancelBooking(bookingId) {
    if (!confirm('Cancel this booking? This action cannot be undone.')) {
        return;
    }
    
    fetch('ajax_cancel_booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            booking_id: bookingId,
            csrf: '<?= csrf() ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Booking cancelled successfully.');
            location.reload();
        } else {
            alert('❌ Error: ' + data.error);
        }
    })
    .catch(() => {
        alert('❌ Network error occurred');
    });
}

// Update booking status
document.querySelectorAll('.booking-status').forEach(select => {
    select.addEventListener('change', function() {
        const bookingId = this.dataset.bookingId;
        const newStatus = this.value;
        
        if (!confirm(`Change booking status to "${newStatus}"?`)) {
            this.blur();
            return;
        }
        
        fetch('ajax_update_booking_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                booking_id: bookingId,
                status: newStatus,
                csrf: '<?= csrf() ?>'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Booking status updated!');
                location.reload();
            } else {
                alert('❌ Error: ' + data.error);
                location.reload();
            }
        })
        .catch(() => {
            alert('❌ Network error occurred');
            location.reload();
        });
    });
});
</script>
<?php endif; ?>

<style>
.table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}

.table th,
.table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
  vertical-align: top;
}

.table th {
  background: #f8fafc;
  font-weight: 600;
  color: #374151;
  position: sticky;
  top: 0;
}

.status-badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
  display: inline-block;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-completed { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }

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
  margin: 2px;
  text-align: center;
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

/* Responsive design */
@media (max-width: 768px) {
  .table {
    font-size: 0.8rem;
  }
  
  .table th,
  .table td {
    padding: 0.5rem;
  }
  
  .btn {
    padding: 0.3rem 0.6rem;
    font-size: 0.8rem;
  }
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>