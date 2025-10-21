<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

// Check if payment_status column exists in bookings table
$check_column = $mysqli->query("SHOW COLUMNS FROM `bookings` LIKE 'payment_status'");
$payment_status_exists = $check_column->num_rows > 0;

// Get pending rental payments statistics (with fallback if column doesn't exist)
if ($payment_status_exists) {
    $pending_bookings = $mysqli->query("SELECT COUNT(*) as count FROM bookings WHERE payment_status = 'pending'")->fetch_assoc()['count'];
    $paid_bookings = $mysqli->query("SELECT COUNT(*) as count FROM bookings WHERE payment_status = 'paid'")->fetch_assoc()['count'];
    
    // Calculate pending revenue
    $pending_revenue_result = $mysqli->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bookings WHERE payment_status = 'pending'");
    $pending_revenue = $pending_revenue_result->fetch_assoc()['total'];
} else {
    // Fallback: treat all bookings as paid if column doesn't exist
    $pending_bookings = 0;
    $paid_bookings = $mysqli->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];
    $pending_revenue = 0;
    
    // Show warning message
    echo '<div class="alert alert-warning" style="background: #f8f9fa; border: 1px solid #dee2e6; color: #6c757d; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <strong>Database Update Required:</strong> Please run the SQL update to add payment tracking columns to your bookings table.
          </div>';
}

$total_bookings = $mysqli->query("SELECT COUNT(*) as count FROM bookings")->fetch_assoc()['count'];

// Get recent pending bookings
if ($payment_status_exists) {
    $recent_pending = $mysqli->query("
        SELECT 
            b.*,
            p.name as product_name,
            p.image,
            u.first_name,
            u.last_name,
            u.email,
            u.phone
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        LEFT JOIN products p ON b.product_id = p.product_id
        WHERE b.payment_status = 'pending'
        ORDER BY b.created_at DESC
        LIMIT 20
    ");
} else {
    // If column doesn't exist, return empty result set
    $recent_pending = $mysqli->query("SELECT 1 WHERE 1=0"); // Empty result set
}

// Get upcoming bookings (paid ones that are coming soon)
if ($payment_status_exists) {
    $upcoming_bookings = $mysqli->query("
        SELECT 
            b.*,
            p.name as product_name,
            p.image,
            u.first_name,
            u.last_name,
            u.phone
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        LEFT JOIN products p ON b.product_id = p.product_id
        WHERE b.payment_status = 'paid' 
        AND b.start_date >= CURDATE()
        ORDER BY b.start_date ASC
        LIMIT 10
    ");
} else {
    $upcoming_bookings = $mysqli->query("SELECT 1 WHERE 1=0"); // Empty result set
}

?>
<div class="card">
    <h3 style="border-bottom: 1px solid #dee2e6; padding-bottom: 15px; margin-bottom: 25px;">Rental Payments Dashboard</h3>
    
    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: #2d3748; border: 1px solid #4a5568; border-radius: 8px; padding: 20px; text-align: center;">
            <h4 style="margin: 0 0 10px 0; color: #e2e8f0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Pending Payments</h4>
            <div style="font-size: 2rem; font-weight: bold; color: #ffffff;"><?= $pending_bookings ?></div>
            <small style="color: #a0aec0;">Awaiting payment confirmation</small>
        </div>
        
        <div style="background: #2d3748; border: 1px solid #4a5568; border-radius: 8px; padding: 20px; text-align: center;">
            <h4 style="margin: 0 0 10px 0; color: #e2e8f0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Pending Revenue</h4>
            <div style="font-size: 2rem; font-weight: bold; color: #ffffff;">R<?= number_format($pending_revenue, 2) ?></div>
            <small style="color: #a0aec0;">Revenue awaiting confirmation</small>
        </div>
        
        <div style="background: #2d3748; border: 1px solid #4a5568; border-radius: 8px; padding: 20px; text-align: center;">
            <h4 style="margin: 0 0 10px 0; color: #e2e8f0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Paid Bookings</h4>
            <div style="font-size: 2rem; font-weight: bold; color: #ffffff;"><?= $paid_bookings ?></div>
            <small style="color: #a0aec0;">Confirmed paid rentals</small>
        </div>
        
        <div style="background: #2d3748; border: 1px solid #4a5568; border-radius: 8px; padding: 20px; text-align: center;">
            <h4 style="margin: 0 0 10px 0; color: #e2e8f0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Total Bookings</h4>
            <div style="font-size: 2rem; font-weight: bold; color: #ffffff;"><?= $total_bookings ?></div>
            <small style="color: #a0aec0;">All rental bookings</small>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap;">
        <?php if ($payment_status_exists): ?>
            <a href="bookings_list.php?status=pending" class="btn" style="background: #4a5568;">
                Manage Pending Payments (<?= $pending_bookings ?>)
            </a>
            <a href="bookings_list.php?status=paid" class="btn" style="background: #718096;">
                View Paid Bookings (<?= $paid_bookings ?>)
            </a>
        <?php endif; ?>
        <a href="bookings_list.php" class="btn" style="background: #2d3748;">
            All Bookings
        </a>
        <a href="products_list.php" class="btn" style="background: #1a202c;">
            Manage Dresses
        </a>
    </div>

    <!-- Recent Pending Bookings -->
    <h4 style="color: #2d3748; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 20px;">Recent Pending Payments</h4>
    <?php if ($payment_status_exists && $recent_pending->num_rows > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Booking Ref</th>
                    <th>Dress & Customer</th>
                    <th>Rental Period</th>
                    <th>Amount</th>
                    <th>Customer Contact</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($booking = $recent_pending->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong style="color: #2d3748;"><?= e($booking['booking_ref'] ?? 'N/A') ?></strong>
                            <br><small style="color: #718096;">#<?= e($booking['booking_id']) ?></small>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if (!empty($booking['image'])): ?>
                                    <img src="<?= e($booking['image']) ?>" alt="<?= e($booking['product_name']) ?>" 
                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0;">
                                <?php endif; ?>
                                <div>
                                    <strong style="color: #2d3748;"><?= e($booking['product_name']) ?></strong>
                                    <br><small style="color: #718096;">Rented by <?= e($booking['first_name'] . ' ' . $booking['last_name']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="color: #2d3748;"><?= e(date('M j', strtotime($booking['start_date']))) ?> - <?= e(date('M j, Y', strtotime($booking['end_date']))) ?></span>
                            <br><small style="color: #718096;"><?= $booking['rental_days'] ?? 3 ?> days</small>
                        </td>
                        <td><strong style="color: #2d3748;">R<?= e(number_format($booking['total_amount'], 2)) ?></strong></td>
                        <td>
                            <span style="color: #2d3748;"><?= e($booking['email']) ?></span>
                            <?php if (!empty($booking['phone'])): ?>
                                <br><small style="color: #718096;">Phone: <?= e($booking['phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="color: #718096;"><?= e(date('M j, g:i A', strtotime($booking['created_at']))) ?></td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <a href="booking_view.php?id=<?= e($booking['booking_id']) ?>" 
                                   class="btn" 
                                   style="padding: 4px 8px; font-size: 12px; background: #4a5568;">
                                    View Details
                                </a>
                                <button onclick="markBookingAsPaid(<?= e($booking['booking_id']) ?>)" 
                                        class="btn" 
                                        style="background: #718096; padding: 4px 8px; font-size: 12px;">
                                    Mark Paid
                                </button>
                                <button onclick="cancelBooking(<?= e($booking['booking_id']) ?>)" 
                                        class="btn" 
                                        style="background: #2d3748; padding: 4px 8px; font-size: 12px;">
                                    Cancel
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php elseif (!$payment_status_exists): ?>
        <div style="text-align: center; padding: 40px; color: #718096; background: #f8f9fa; border-radius: 8px;">
            <h4 style="color: #4a5568;">Database Update Required</h4>
            <p>Please run the SQL update to enable payment tracking features.</p>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #718096; background: #f8f9fa; border-radius: 8px;">
            <h4 style="color: #4a5568;">No Pending Payments</h4>
            <p>All rental bookings are fully paid and confirmed.</p>
        </div>
    <?php endif; ?>

    <!-- Upcoming Bookings -->
    <?php if ($payment_status_exists && $upcoming_bookings->num_rows > 0): ?>
        <h4 style="color: #2d3748; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-top: 40px; margin-bottom: 20px;">Upcoming Rentals (Paid & Confirmed)</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Dress</th>
                    <th>Customer</th>
                    <th>Rental Dates</th>
                    <th>Contact</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($booking = $upcoming_bookings->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong style="color: #2d3748;"><?= e($booking['product_name']) ?></strong>
                        </td>
                        <td style="color: #4a5568;"><?= e($booking['first_name'] . ' ' . $booking['last_name']) ?></td>
                        <td>
                            <strong style="color: #2d3748;"><?= e(date('M j', strtotime($booking['start_date']))) ?> - <?= e(date('M j', strtotime($booking['end_date']))) ?></strong>
                            <br><small style="color: #718096;"><?= round((strtotime($booking['end_date']) - strtotime($booking['start_date'])) / (60 * 60 * 24)) ?> days</small>
                        </td>
                        <td style="color: #718096;">
                            <?= e($booking['phone'] ?? 'No phone') ?>
                        </td>
                        <td><strong style="color: #2d3748;">R<?= e(number_format($booking['total_amount'], 2)) ?></strong></td>
                        <td>
                            <a href="booking_view.php?id=<?= e($booking['booking_id']) ?>" 
                               class="btn" 
                               style="padding: 4px 8px; font-size: 12px;">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($payment_status_exists): ?>
<script>
function markBookingAsPaid(bookingId) {
    if (!confirm('Mark this rental booking as paid? This will confirm the payment and lock in the booking.')) {
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
            alert('Booking marked as paid! The rental is now confirmed.');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(() => {
        alert('Network error occurred');
    });
}

function cancelBooking(bookingId) {
    if (!confirm('Cancel this booking? This will remove the pending payment and free up the dress for other rentals.')) {
        return;
    }
    
    if (!confirm('Are you sure? This action cannot be undone.')) {
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
            alert('Booking cancelled successfully.');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(() => {
        alert('Network error occurred');
    });
}
</script>
<?php endif; ?>

<style>
.table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
  background: white;
}

.table th {
  background: #2d3748;
  color: white;
  font-weight: 600;
  padding: 0.75rem;
  text-align: left;
  border-bottom: 2px solid #4a5568;
}

.table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}

.table tr:hover {
  background: #f8f9fa;
}

.btn {
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 0.5rem 1rem;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  font-size: 0.9rem;
  margin: 2px;
  transition: background-color 0.2s;
}

.btn:hover {
  background: #4a5568;
}

.card {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  border: 1px solid #e2e8f0;
}

/* Color palette for reference:
   #1a202c - Dark grey (almost black)
   #2d3748 - Dark grey
   #4a5568 - Medium grey
   #718096 - Light grey
   #e2e8f0 - Very light grey
   #f8f9fa - Off-white
*/
</style>

<?php require_once __DIR__ . '/footer.php'; ?>