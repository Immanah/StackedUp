<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

$id = (int)($_GET['id'] ?? 0);

// Check if payment_status column exists
$check_column = $mysqli->query("SHOW COLUMNS FROM `bookings` LIKE 'payment_status'");
$payment_status_exists = $check_column->num_rows > 0;

// Get booking details with user and product information
$stmt = $mysqli->prepare("
    SELECT 
        b.*,
        p.name as product_name,
        p.description as product_description,
        p.image as product_image,
        p.rental_price,
        p.security_deposit,
        p.size as product_size,
        p.color as product_color,
        p.brand as product_brand,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.address_line1,
        u.address_line2,
        u.city,
        u.province,
        u.postal_code,
        u.country
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN products p ON b.product_id = p.product_id
    WHERE b.booking_id = ?
    LIMIT 1
");

$stmt->bind_param('i', $id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<div class='card'><h3>Booking Not Found</h3><p>The requested booking does not exist.</p></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// Calculate rental duration
$start_date = new DateTime($booking['start_date']);
$end_date = new DateTime($booking['end_date']);
$rental_days = $start_date->diff($end_date)->days;
$today = new DateTime();
$is_upcoming = $start_date > $today;
$is_active = $booking['status'] == 'booked' && $end_date >= $today && $start_date <= $today;
$is_past = $end_date < $today;

// Get booking history/logs if available
$activity_logs = $mysqli->query("
    SELECT * FROM activity_log 
    WHERE context LIKE '%\"booking_id\":{$booking['booking_id']}%' 
    OR context LIKE '%\"booking_id\":\"{$booking['booking_id']}\"%'
    ORDER BY created_at DESC
    LIMIT 10
");

// Get similar bookings for this dress
$similar_bookings = $mysqli->query("
    SELECT b.*, u.first_name, u.last_name 
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.user_id 
    WHERE b.product_id = {$booking['product_id']} 
    AND b.booking_id != {$booking['booking_id']}
    ORDER BY b.start_date DESC 
    LIMIT 5
");
?>

<div class="card">
    <!-- Header Section -->
    <div style="display: flex; justify-content: between; align-items: start; margin-bottom: 20px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h3 style="margin: 0;">
                <i class="fas fa-receipt"></i> Booking #<?= e($booking['booking_id']) ?> 
                <?php if (!empty($booking['booking_ref'])): ?>
                    <small style="color: #666;">(Ref: <?= e($booking['booking_ref']) ?>)</small>
                <?php endif; ?>
            </h3>
            <p style="margin: 5px 0 0 0; color: #666;">
                <i class="fas fa-calendar-plus"></i> Created: <?= e(date('F j, Y g:i A', strtotime($booking['created_at']))) ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="bookings_list.php" class="btn" style="background: #6b7280;">
                <i class="fas fa-arrow-left"></i> Back to Bookings
            </a>
            <button onclick="window.print()" class="btn" style="background: #374151;">
                <i class="fas fa-print"></i> Print
            </button>
            <?php if ($payment_status_exists && $booking['payment_status'] == 'pending'): ?>
                <button onclick="markBookingAsPaid(<?= e($booking['booking_id']) ?>)" 
                        class="btn" 
                        style="background: #059669;">
                    <i class="fas fa-check-circle"></i> Mark as Paid
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Overview -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="background: <?= $is_active ? '#d4edda' : ($is_upcoming ? '#fff3cd' : '#e2e3e5') ?>; 
                    border-radius: 8px; padding: 15px; text-align: center;">
            <div style="font-size: 0.9rem; color: #666;">
                <i class="fas fa-calendar-check"></i> Rental Status
            </div>
            <div style="font-size: 1.2rem; font-weight: bold; color: <?= $is_active ? '#155724' : ($is_upcoming ? '#856404' : '#383d41') ?>;">
                <?= $is_active ? 'Active' : ($is_upcoming ? 'Upcoming' : 'Completed') ?>
            </div>
        </div>
        
        <div style="background: <?= $booking['status'] == 'booked' ? '#d4edda' : ($booking['status'] == 'cancelled' ? '#fee2e2' : '#e2e3e5') ?>; 
                    border-radius: 8px; padding: 15px; text-align: center;">
            <div style="font-size: 0.9rem; color: #666;">
                <i class="fas fa-bookmark"></i> Booking Status
            </div>
            <div style="font-size: 1.2rem; font-weight: bold; color: <?= $booking['status'] == 'booked' ? '#155724' : ($booking['status'] == 'cancelled' ? '#991b1b' : '#383d41') ?>;">
                <?= ucfirst(e($booking['status'])) ?>
            </div>
        </div>
        
        <?php if ($payment_status_exists): ?>
        <div style="background: <?= $booking['payment_status'] == 'paid' ? '#d4edda' : ($booking['payment_status'] == 'pending' ? '#fff3cd' : '#fee2e2') ?>; 
                    border-radius: 8px; padding: 15px; text-align: center;">
            <div style="font-size: 0.9rem; color: #666;">
                <i class="fas fa-credit-card"></i> Payment Status
            </div>
            <div style="font-size: 1.2rem; font-weight: bold; color: <?= $booking['payment_status'] == 'paid' ? '#155724' : ($booking['payment_status'] == 'pending' ? '#856404' : '#991b1b') ?>;">
                <?= ucfirst(e($booking['payment_status'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="background: #d1ecf1; border-radius: 8px; padding: 15px; text-align: center;">
            <div style="font-size: 0.9rem; color: #666;">
                <i class="fas fa-money-bill-wave"></i> Total Amount
            </div>
            <div style="font-size: 1.2rem; font-weight: bold; color: #0c5460;">
                R<?= e(number_format($booking['total_amount'] ?: $booking['rental_price'], 2)) ?>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <!-- Left Column: Customer & Rental Details -->
        <div>
            <!-- Customer Information -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user" style="color: #6b7280;"></i> Customer Information
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <strong>Name:</strong><br>
                        <?= e($booking['first_name'] . ' ' . $booking['last_name']) ?>
                    </div>
                    <div>
                        <strong>Email:</strong><br>
                        <?= e($booking['email']) ?>
                    </div>
                    <div>
                        <strong>Phone:</strong><br>
                        <?= e($booking['phone'] ?? 'Not provided') ?>
                    </div>
                    <div>
                        <strong>User ID:</strong><br>
                        #<?= e($booking['user_id']) ?>
                        <a href="customer_view.php?id=<?= e($booking['user_id']) ?>" style="margin-left: 5px; color: #3498db;">View Profile</a>
                    </div>
                </div>
                
                <?php if ($booking['address_line1']): ?>
                    <div style="margin-top: 15px;">
                        <strong>Address:</strong><br>
                        <?= e($booking['address_line1']) ?><br>
                        <?php if ($booking['address_line2']): ?><?= e($booking['address_line2']) ?><br><?php endif; ?>
                        <?= e($booking['city']) ?>, 
                        <?= e($booking['province']) ?> 
                        <?= e($booking['postal_code']) ?><br>
                        <?= e($booking['country']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Rental Period -->
            <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-calendar-alt" style="color: #856404;"></i> Rental Period
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: center;">
                    <div>
                        <strong>Start Date</strong><br>
                        <div style="font-size: 1.2rem; font-weight: bold;">
                            <?= e(date('l, F j, Y', strtotime($booking['start_date']))) ?>
                        </div>
                    </div>
                    <div>
                        <strong>End Date</strong><br>
                        <div style="font-size: 1.2rem; font-weight: bold;">
                            <?= e(date('l, F j, Y', strtotime($booking['end_date']))) ?>
                        </div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 10px; font-weight: bold; color: #856404;">
                    <i class="fas fa-clock"></i> <?= $rental_days ?> day<?= $rental_days != 1 ? 's' : '' ?> rental
                    <?php if ($is_upcoming): ?>
                        • Starts in <?= $today->diff($start_date)->days ?> day<?= $today->diff($start_date)->days != 1 ? 's' : '' ?>
                    <?php elseif ($is_active): ?>
                        • Ends in <?= $today->diff($end_date)->days ?> day<?= $today->diff($end_date)->days != 1 ? 's' : '' ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fees & Penalties -->
            <?php if ($booking['late_fee'] > 0 || $booking['damage_fee'] > 0): ?>
            <div style="background: #fee2e2; padding: 20px; border-radius: 8px;">
                <h4 style="margin-top: 0; color: #991b1b; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle"></i> Fees & Penalties
                </h4>
                <?php if ($booking['late_fee'] > 0): ?>
                    <div style="margin-bottom: 10px;">
                        <strong>Late Return Fee:</strong> R<?= e(number_format($booking['late_fee'], 2)) ?><br>
                        <small>Status: <?= e($booking['penalty_status']) ?></small>
                    </div>
                <?php endif; ?>
                <?php if ($booking['damage_fee'] > 0): ?>
                    <div>
                        <strong>Damage Fee:</strong> R<?= e(number_format($booking['damage_fee'], 2)) ?><br>
                        <small>Status: <?= e($booking['penalty_status']) ?></small>
                    </div>
                <?php endif; ?>
                <?php if ($booking['penalty_status'] != 'none' && $booking['penalty_status'] != 'paid'): ?>
                    <button style="margin-top: 10px; background: #dc2626; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-check"></i> Mark Penalty as Paid
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Dress Details & Actions -->
        <div>
            <!-- Dress Information -->
            <div style="background: #f0fff4; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-tshirt" style="color: #155724;"></i> Dress Details
                </h4>
                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <?php if (!empty($booking['product_image'])): ?>
                        <img src="<?= e($booking['product_image']) ?>" 
                             alt="<?= e($booking['product_name']) ?>" 
                             style="width: 100px; height: 120px; object-fit: cover; border-radius: 8px;">
                    <?php else: ?>
                        <div style="width: 100px; height: 120px; background: #e2e3e5; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #6b7280; flex-direction: column;">
                            <i class="fas fa-tshirt" style="font-size: 1.5rem;"></i>
                            <small style="margin-top: 5px;">No Image</small>
                        </div>
                    <?php endif; ?>
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 10px 0; color: #155724;"><?= e($booking['product_name']) ?></h4>
                        <?php if (!empty($booking['product_brand'])): ?>
                            <div style="margin-bottom: 5px;">
                                <strong>Brand:</strong> <?= e($booking['product_brand']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['product_color'])): ?>
                            <div style="margin-bottom: 5px;">
                                <strong>Color:</strong> <?= e($booking['product_color']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['product_size'])): ?>
                            <div style="margin-bottom: 5px;">
                                <strong>Size:</strong> <?= e($booking['product_size']) ?>
                            </div>
                        <?php endif; ?>
                       <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #d1fae5;">
                       <strong>Rental Price:</strong> R<?= e(number_format($booking['rental_price'], 2)) ?>/day<br>
                       <strong>Security Deposit:</strong> R800.00 (refundable)
                      </div>
                    </div>
                </div>
                <?php if (!empty($booking['product_description'])): ?>
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #d1fae5;">
                        <strong>Description:</strong><br>
                        <?= nl2br(e($booking['product_description'])) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div style="background: #f8fafc; border: 1px solid #e5e7eb; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-bolt" style="color: #374151;"></i> Quick Actions
                </h4>
                <div style="display: grid; gap: 15px;">
                    <!-- Status Update -->
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">
                            <i class="fas fa-sync-alt" style="margin-right: 5px;"></i>Update Booking Status
                        </label>
                        <select id="bookingStatus" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; background: white;">
                            <option value="booked" <?= $booking['status'] == 'booked' ? 'selected' : '' ?>>Booked</option>
                            <option value="returned" <?= $booking['status'] == 'returned' ? 'selected' : '' ?>>Returned</option>
                            <option value="cancelled" <?= $booking['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <button onclick="updateBookingStatus()" class="btn" style="width: 100%; margin-top: 10px; background: #374151;">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </div>

                    <!-- Payment Actions -->
                    <?php if ($payment_status_exists): ?>
                        <?php if ($booking['payment_status'] == 'pending'): ?>
                            <button onclick="markBookingAsPaid(<?= e($booking['booking_id']) ?>)" 
                                    class="btn" 
                                    style="background: #059669; width: 100%;">
                                <i class="fas fa-check-circle"></i> Confirm Payment Received
                            </button>
                        <?php else: ?>
                            <div style="text-align: center; padding: 12px; background: #d1fae5; border-radius: 6px; color: #065f46;">
                                <i class="fas fa-check-circle"></i> Payment Confirmed
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Cancel Booking -->
                    <div style="display: block; margin-bottom: 8px; font-weight: 600; color: #dc2626;">
                        <button onclick="cancelBooking(<?= e($booking['booking_id']) ?>)" 
                                class="btn" 
                                style="background: #dc2626; width: 100%;">
                            <i class="fas fa-times-circle"></i> Cancel This Booking
                        </button>
                    </div>
                </div>
            </div>

            <!-- Price Breakdown -->
<div style="background: #f8fafc; border: 1px solid #e5e7eb; padding: 20px; border-radius: 8px;">
    <h4 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-receipt" style="color: #374151;"></i> Price Breakdown
    </h4>
    <div style="display: grid; gap: 10px;">
        <!-- Rental Price -->
        <div style="display: flex; justify-content: space-between; padding: 8px 0;">
            <span>Rental Price (<?= $rental_days ?> days × R<?= e(number_format($booking['rental_price'], 2)) ?>):</span>
            <span>R<?= e(number_format($booking['rental_price'] * $rental_days, 2)) ?></span>
        </div>
        
        <!-- Security Deposit (Fixed R800) -->
        <div style="display: flex; justify-content: space-between; padding: 8px 0;">
            <span>Security Deposit:</span>
            <span>R800.00</span>
        </div>
        
        <?php if ($booking['late_fee'] > 0): ?>
            <div style="display: flex; justify-content: space-between; color: #dc2626; padding: 8px 0;">
                <span>Late Return Fee:</span>
                <span>R<?= e(number_format($booking['late_fee'], 2)) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($booking['damage_fee'] > 0): ?>
            <div style="display: flex; justify-content: space-between; color: #dc2626; padding: 8px 0;">
                <span>Damage Fee:</span>
                <span>R<?= e(number_format($booking['damage_fee'], 2)) ?></span>
            </div>
        <?php endif; ?>
        
        <hr style="border: none; border-top: 2px solid #e5e7eb; margin: 5px 0;">
        
        <!-- Total Amount -->
        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.1rem; padding: 8px 0; background: #f1f5f9; margin: -8px -20px -20px -20px; padding: 15px 20px; border-radius: 0 0 8px 8px;">
            <span>Total Amount:</span>
            <span>R<?= e(number_format($booking['total_amount'] ?: $booking['rental_price'], 2)) ?></span>
        </div>
        
        <!-- Breakdown Note -->
        <div style="font-size: 0.8rem; color: #6b7280; margin-top: 10px;">
            <i class="fas fa-info-circle"></i> 
            Total includes rental fee + security deposit. Security deposit of R800 will be refunded after dress return.
        </div>
    </div>
</div>
        </div>
    </div>

    <!-- Activity Log & Similar Bookings -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Activity Log -->
        <div>
            <h4 style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-clipboard-list"></i> Activity Log
            </h4>
            <?php if ($activity_logs->num_rows > 0): ?>
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                    <?php while ($log = $activity_logs->fetch_assoc()): ?>
                        <div style="padding: 12px 15px; border-bottom: 1px solid #e5e7eb;">
                            <div style="font-weight: 600;"><?= e(ucfirst(str_replace('_', ' ', $log['action']))) ?></div>
                            <div style="font-size: 0.85rem; color: #666;">
                                <i class="fas fa-clock" style="margin-right: 4px;"></i>
                                <?= e(date('M j, Y g:i A', strtotime($log['created_at']))) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #6b7280; background: #f9fafb; border-radius: 8px;">
                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    No activity logged for this booking
                </div>
            <?php endif; ?>
        </div>

        <!-- Similar Bookings -->
        <div>
            <h4 style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-chart-bar"></i> Similar Bookings
            </h4>
            <?php if ($similar_bookings->num_rows > 0): ?>
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                    <?php while ($similar = $similar_bookings->fetch_assoc()): ?>
                        <div style="padding: 12px 15px; border-bottom: 1px solid #e5e7eb;">
                            <div style="display: flex; justify-content: between; align-items: center;">
                                <div>
                                    <strong><?= e(date('M j, Y', strtotime($similar['start_date']))) ?></strong>
                                    <div style="font-size: 0.85rem; color: #666;">
                                        <i class="fas fa-user" style="margin-right: 4px;"></i>
                                        <?= e($similar['first_name'] . ' ' . $similar['last_name']) ?> • 
                                        <?= ucfirst(e($similar['status'])) ?>
                                    </div>
                                </div>
                                <a href="booking_view.php?id=<?= e($similar['booking_id']) ?>" 
                                   style="background: #3b82f6; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #6b7280; background: #f9fafb; border-radius: 8px;">
                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    No similar bookings found
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateBookingStatus() {
    const status = document.getElementById('bookingStatus').value;
    const currentStatus = '<?= e($booking['status']) ?>';
    
    if (status === currentStatus) {
        alert('⚠️ Booking status is already set to "' + status + '".');
        return;
    }
    
    if (!confirm(`Change booking status from "${currentStatus}" to "${status}"?`)) {
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    button.disabled = true;
    
    fetch('ajax_update_booking_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            booking_id: <?= e($booking['booking_id']) ?>,
            status: status,
            csrf: '<?= csrf() ?>'
        })
    })
    .then(r => {
        if (!r.ok) {
            throw new Error('Network response was not ok');
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            alert('✅ Booking status updated successfully!');
            location.reload();
        } else {
            throw new Error(data.error || 'Unknown error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Failed to update booking status: ' + error.message);
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function markBookingAsPaid(bookingId) {
    if (!confirm('Mark this booking as paid? This will confirm the payment and cannot be undone.')) {
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
    if (!confirm('Cancel this booking? This action cannot be undone and will remove the booking permanently.')) {
        return;
    }
    
    if (!confirm('⚠️ Are you absolutely sure? This will delete the booking and free up the dress for other rentals.')) {
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
            window.location.href = 'bookings_list.php';
        } else {
            alert('❌ Error: ' + data.error);
        }
    })
    .catch(() => {
        alert('❌ Network error occurred');
    });
}
</script>

<style>
.card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

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
    transition: all 0.2s ease;
}

.btn:hover {
    background: #222733ff;
    transform: translateY(-1px);
}

@media print {
    .btn {
        display: none !important;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

@media (max-width: 768px) {
    .card {
        padding: 1rem;
    }
    
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>