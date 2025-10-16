<?php
session_start();
require 'db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "You must be logged in to view this page.";
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user details from database
$user_sql = "SELECT email, first_name, last_name FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    echo "User not found.";
    exit;
}

$user_data = $user_result->fetch_assoc();
$user_email = $user_data['email'];
$user_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
$user_stmt->close();

// Store user info in session for future use
$_SESSION['user_email'] = $user_email;
$_SESSION['user_name'] = $user_name;

// Fetch the items user booked (from cart or selection)
$sql_items = "SELECT c.product_id, c.quantity, p.name, p.price, c.size, c.start_date, c.end_date
              FROM cart c
              JOIN products p ON c.product_id = p.product_id
              WHERE c.user_id = ?";
$stmt = $conn->prepare($sql_items);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$subtotal = 0;

while ($row = $result->fetch_assoc()) {
    // Calculate rental days
    $start = new DateTime($row['start_date']);
    $end = new DateTime($row['end_date']);
    $days = $start->diff($end)->days;
    $days = max(1, $days);
    
    $items[] = [
        'product_id' => $row['product_id'],
        'title' => $row['name'],
        'quantity' => $row['quantity'],
        'price' => $row['price'],
        'size' => $row['size'],
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date'],
        'days' => $days
    ];
    $subtotal += $row['price'];
}
$stmt->close();

// Calculate totals
$deposit = 800; // refundable deposit
$delivery = 250; // fixed delivery fee
$returnFee = 120; // fixed return fee
$total = $subtotal + $deposit + $delivery + $returnFee;

// Generate booking reference
$bookingRef = 'OZ' . rand(100000, 999999);

// Insert booking into database
$sql_booking = "INSERT INTO bookings (user_id, product_id, start_date, end_date, status, booking_ref, total_amount)
                VALUES (?, ?, ?, ?, 'booked', ?, ?)";
$stmt = $conn->prepare($sql_booking);
foreach ($items as $item) {
    $stmt->bind_param("iisssd", $user_id, $item['product_id'], $item['start_date'], $item['end_date'], $bookingRef, $total);
    $stmt->execute();
}
$stmt->close();

// Clear the user's cart after successful booking
$clear_cart_sql = "DELETE FROM cart WHERE user_id = ?";
$clear_stmt = $conn->prepare($clear_cart_sql);
$clear_stmt->bind_param("i", $user_id);
$clear_stmt->execute();
$clear_stmt->close();

// Send confirmation email
function sendBookingConfirmation($userEmail, $userName, $bookingRef, $items, $total, $subtotal, $deposit, $delivery, $returnFee) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'shafee.mmadi@gmail.com';
        $mail->Password   = 'anug yjfi iorp yhll';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet = 'UTF-8'; // Add charset

        $mail->setFrom('shafee.mmadi@gmail.com', 'OZYDE');
        $mail->addAddress($userEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = "Booking Confirmation — $bookingRef | OZYDE"; // Removed emoji from subject
        
        // Build beautiful email body
        $email_body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: "Inter", "Helvetica Neue", Arial, sans-serif; margin: 0; padding: 0; background: #f8f9fa; }
                .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                .header { background: #000000; padding: 40px 30px; text-align: center; color: white; } /* Changed to solid black */
                .logo { font-size: 28px; font-weight: 800; letter-spacing: 2px; margin-bottom: 10px; }
                .gold-accent { color: #ffff; }
                .content { padding: 40px 30px; }
                .section { margin-bottom: 30px; }
                .booking-ref { background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center; border-left: 4px solid #e3dfd1ff; }
                .ref-number { font-size: 24px; font-weight: 800; color: #0b0b0b; letter-spacing: 1px; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .items-table th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e9ecef; font-weight: 600; color: #495057; }
                .items-table td { padding: 12px; border-bottom: 1px solid #e9ecef; }
                .totals-table { width: 100%; border-collapse: collapse; }
                .totals-table td { padding: 8px 0; }
                .totals-table .total-row { border-top: 2px solid #e9ecef; font-weight: 800; font-size: 18px; }
                .footer { background: #f8f9fa; padding: 30px; text-align: center; color: #000000; font-size: 14px; }
                .highlight-box { background: #fff9e6; border: 1px solid #ffecb3; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .section-title { color: #0b0b0b; margin-bottom: 20px; font-size: 18px; font-weight: 600; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">OZ<span class="gold-accent">YDE</span></div>
                </div>
                
                <div class="content">
                    <div class="section">
                        <h2 style="color: #0b0b0b; margin-bottom: 10px;">Booking Confirmed!</h2>
                        <p style="color: #0b0b0b; font-size: 16px; line-height: 1.6;">Hi ' . $userName . ',</p>
                        <p style="color: #0b0b0b; font-size: 16px; line-height: 1.6;">Thank you for choosing OZYDE. Your booking has been confirmed and we\'re preparing your items.</p>
                    </div>
                    
                    <div class="booking-ref">
                        <div style="color: #000000; font-size: 14px; margin-bottom: 8px;">BOOKING REFERENCE</div>
                        <div class="ref-number">' . $bookingRef . '</div>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Your Items</div>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Size</th>
                                    <th>Rental Period</th>
                                    <th style="text-align: right;">Price</th>
                                </tr>
                            </thead>
                            <tbody>';
        
        foreach ($items as $item) {
            $start_date = date('M j, Y', strtotime($item['start_date']));
            $end_date = date('M j, Y', strtotime($item['end_date']));
            $email_body .= '<tr>
                <td style="font-weight: 600;">' . $item['title'] . '</td>
                <td>' . $item['size'] . '</td>
                <td>' . $start_date . ' to ' . $end_date . '</td>
                <td style="text-align: right; font-weight: 600;">R' . number_format($item['price'], 2) . '</td>
            </tr>';
        }
        
        $email_body .= '</tbody>
                        </table>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">Order Summary</div>
                        <table class="totals-table">
                            <tr>
                                <td>Subtotal:</td>
                                <td style="text-align: right;">R' . number_format($subtotal, 2) . '</td>
                            </tr>
                            <tr>
                                <td>Refundable Deposit:</td>
                                <td style="text-align: right;">R' . number_format($deposit, 2) . '</td>
                            </tr>
                            <tr>
                                <td>Delivery Fee:</td>
                                <td style="text-align: right;">R' . number_format($delivery, 2) . '</td>
                            </tr>
                            <tr>
                                <td>Return Fee:</td>
                                <td style="text-align: right;">R' . number_format($returnFee, 2) . '</td>
                            </tr>
                            <tr class="total-row">
                                <td><strong>Total Amount:</strong></td>
                                <td style="text-align: right;"><strong>R' . number_format($total, 2) . '</strong></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="highlight-box">
                        <h4 style="color: #0b0b0b; margin-top: 0;">Important Information</h4>
                        <ul style="color: #6c757d; line-height: 1.6; padding-left: 20px;">
                            <li>Please have your ID ready for verification upon delivery/collection</li>
                            <li>Your R' . number_format($deposit, 2) . ' deposit will be refunded after inspection when items are returned undamaged</li>
                            <li>Keep this booking reference for all communications</li>
                            <li>Contact us immediately if there are any issues with your items</li>
                        </ul>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <p style="color: #6c757d; font-size: 14px;">Need help? Contact us at <a href="mailto:support@ozyde.com" style="color: #d4af37;">support@ozyde.com</a></p>
                    </div>
                </div>
                
                <div class="footer">
                    <p>© 2024 OZYDE All rights reserved.</p>
                    <p>5 Liebenberg Rd, Noordwyk, Midrand 1687</p>
                    <p>Tel: +27 11 123 4567 | Email: ozydedesigns@gmail.com</p>
                </div>
            </div>
        </body>
        </html>';

        $mail->Body = $email_body;
        
        // Plain text version for email clients that don't support HTML
        $plain_text = "BOOKING CONFIRMED - $bookingRef\n\n";
        $plain_text .= "Hi $userName,\n\n";
        $plain_text .= "Thank you for choosing OZYDE. Your booking has been confirmed.\n\n";
        $plain_text .= "BOOKING REFERENCE: $bookingRef\n\n";
        $plain_text .= "YOUR ITEMS:\n";
        foreach ($items as $item) {
            $start_date = date('M j, Y', strtotime($item['start_date']));
            $end_date = date('M j, Y', strtotime($item['end_date']));
            $plain_text .= "- {$item['title']} (Size: {$item['size']}) - Rental: $start_date to $end_date - R{$item['price']}\n";
        }
        $plain_text .= "\nORDER SUMMARY:\n";
        $plain_text .= "Subtotal: R" . number_format($subtotal, 2) . "\n";
        $plain_text .= "Deposit: R" . number_format($deposit, 2) . "\n";
        $plain_text .= "Delivery: R" . number_format($delivery, 2) . "\n";
        $plain_text .= "Return Fee: R" . number_format($returnFee, 2) . "\n";
        $plain_text .= "TOTAL: R" . number_format($total, 2) . "\n\n";
        $plain_text .= "Your deposit will be refunded after inspection when items are returned undamaged.\n\n";
        $plain_text .= "Need help? Contact support@ozyde.com\n";
        $plain_text .= "OZYDE Luxury Rentals";
        
        $mail->AltBody = $plain_text;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

$email_sent = sendBookingConfirmation($user_email, $user_name, $bookingRef, $items, $total, $subtotal, $deposit, $delivery, $returnFee);

$conn->close();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Booking confirmed — OZYDE</title>
<style>
:root {
    --bg: #f6f6f7;
    --nav-bg: #000000; /* Changed to black */
    --muted: #7a7a7a;
    --accent: #111;
    --gold1: #d4af37;
    --gold2: #f0c75e;
    --max-width: 1100px;
}
* {box-sizing: border-box;}
body {margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;color:var(--accent);background:var(--bg);min-height:100vh;}
.nav-wrap{background:var(--nav-bg);color:#fff;position:sticky;top:0;z-index:120;box-shadow:0 6px 20px rgba(2,2,2,0.12);}
.nav{max-width:var(--max-width);margin:0 auto;padding:12px 18px;display:flex;align-items:center;gap:18px;justify-content:space-between;}
.logo{display:flex;gap:12px;align-items:center;font-weight:800;letter-spacing:1px;font-size:20px;}
.logo-badge{width:40px;height:40px;border-radius:8px;background:linear-gradient(135deg,#fff2,#fff6);display:flex;align-items:center;justify-content:center;color:#111;font-weight:900;font-size:16px;}
main{max-width:var(--max-width);margin:28px auto;padding:0 18px 60px;}
.confirm-wrap{display:grid;grid-template-columns:220px 1fr;gap:24px;align-items:start;}
@media (max-width:900px){.confirm-wrap{grid-template-columns:1fr;}}
.left-art{display:flex;align-items:center;justify-content:center;}
.card{background:#fff;border-radius:12px;border:3px solid #000;padding:22px;box-shadow:0 12px 40px rgba(0,0,0,0.12);min-height:300px;}
.card h1{margin:0 0 8px 0;font-size:22px;}
.muted{color:var(--muted);}
.item-list{margin-top:12px;border-radius:8px;padding:12px;background:#fafafa;}
.row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f1f1;}
.row:last-child{border-bottom:0;}
.totals{margin-top:12px;}
.big-total{font-weight:800;font-size:20px;margin-top:6px;}
.ref{font-weight:800;color:var(--gold1);float:right;}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:var(--gold1);color:#111;font-weight:700;border:0;cursor:pointer;margin-top:14px;}
.subtle{font-size:13px;color:var(--muted);margin-top:12px;}
.success-notice {background: #e8ffef; padding: 12px; border-radius: 8px; border-left: 4px solid #2fa46b; margin-bottom: 16px;}
</style>
</head>
<body>
<header class="nav-wrap">
<div class="nav">
<div class="logo">
<div class="logo-badge">OZ</div>
<div>OZYDE</div>
</div>
</div>
</header>

<main>
<div style="margin-bottom:12px">
<h1>Booking confirmed</h1>
<div class="muted">Thanks — your booking is all set.</div>

<?php if ($email_sent): ?>
<div class="success-notice">
    Confirmation email sent to <?php echo htmlspecialchars($user_email); ?>
</div>
<?php else: ?>
<div class="success-notice" style="background: #fff3cd; border-left-color: #ffc107;">
    Booking confirmed but email could not be sent. Please save your reference number.
</div>
<?php endif; ?>
</div>

<div class="confirm-wrap">
<div class="left-art">
<!-- optional SVG -->
</div>

<div class="card">
<div style="display:flex;align-items:center;justify-content:space-between">
<div>
<div style="font-size:14px;color:var(--muted)">Reference</div>
<div style="font-size:18px;margin-top:6px"><span class="ref"><?= $bookingRef ?></span></div>
</div>
<div style="font-weight:700;color:#111">OZYDE</div>
</div>

<div class="item-list" id="itemListArea"></div>

<div class="totals">
<div style="display:flex;justify-content:space-between"><div>Subtotal</div><div id="subtotal"></div></div>
<div style="display:flex;justify-content:space-between;margin-top:8px"><div>Deposit (refundable)</div><div id="deposit"></div></div>
<div style="display:flex;justify-content:space-between;margin-top:8px"><div>Delivery</div><div id="delivery"></div></div>
<div style="display:flex;justify-content:space-between;margin-top:8px"><div>Return fee</div><div id="returnFee"></div></div>
<div class="big-total" style="display:flex;justify-content:space-between"><div>Total</div><div id="totalVal"></div></div>
</div>

<div class="subtle">
    <?php if ($email_sent): ?>
        A confirmation email has been sent to <?php echo htmlspecialchars($user_email); ?>. Keep the booking reference above for payments and communication.
    <?php else: ?>
        Please save your booking reference: <strong><?= $bookingRef ?></strong> for future reference.
    <?php endif; ?>
</div>

<div style="display:flex;gap:12px;justify-content:flex-end">
<button class="btn" id="backShop">Back to shop</button>
</div>
</div>
</div>
</main>

<script>
const items = <?= json_encode($items) ?>;
const subtotal = <?= $subtotal ?>;
const deposit = <?= $deposit ?>;
const deliveryFee = <?= $delivery ?>;
const returnFee = <?= $returnFee ?>;
const totalVal = <?= $total ?>;

// Populate items
const area = document.getElementById('itemListArea');
area.innerHTML = '';
items.forEach(it => {
    const row = document.createElement('div');
    row.className = 'row';
    const startDate = new Date(it.start_date).toLocaleDateString();
    const endDate = new Date(it.end_date).toLocaleDateString();
    row.innerHTML = `
        <div>
            <div style="font-weight:700">${it.title}</div>
            <div class="muted" style="margin-top:4px">
                Size: ${it.size} | Rental: ${startDate} - ${endDate}
            </div>
        </div>
        <div style="font-weight:700">R${Number(it.price).toFixed(2)}</div>
    `;
    area.appendChild(row);
});

// Populate totals
document.getElementById('subtotal').textContent = 'R' + subtotal.toFixed(2);
document.getElementById('deposit').textContent = 'R' + deposit.toFixed(2);
document.getElementById('delivery').textContent = 'R' + deliveryFee.toFixed(2);
document.getElementById('returnFee').textContent = 'R' + returnFee.toFixed(2);
document.getElementById('totalVal').textContent = 'R' + totalVal.toFixed(2);

// Back button
document.getElementById('backShop').addEventListener('click', () => {
    window.location.href = 'catalog.php';
});
</script>
</body>
</html>