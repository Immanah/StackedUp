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
$user_email = $_SESSION['user_email']; // make sure this is stored in session
$user_name  = $_SESSION['user_name'];  // store full name in session

// Fetch the items user booked (from cart or selection)
$sql_items = "SELECT c.product_id, c.quantity, p.name, p.price
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
    $items[] = [
        'product_id' => $row['product_id'],
        'title' => $row['name'],
        'quantity' => $row['quantity'],
        'price' => $row['price'] * $row['quantity']
    ];
    $subtotal += $row['price'] * $row['quantity'];
}
$stmt->close();

// Calculate totals
$vat = $subtotal * 0.15; // 15% VAT
$deposit = $subtotal * 0.2; // refundable deposit
$delivery = 150; // fixed delivery fee
$returnFee = 50; // fixed return fee
$total = $subtotal + $vat + $delivery + $returnFee;

// Generate booking reference
$bookingRef = 'OZ' . rand(100000, 999999);

// Insert booking into database
$sql_booking = "INSERT INTO bookings (user_id, product_id, start_date, end_date, status)
                VALUES (?, ?, ?, ?, 'booked')";

$start_date = date('Y-m-d'); // for example today
$end_date = date('Y-m-d', strtotime('+2 days')); // 3-day rental including start/end

$stmt = $conn->prepare($sql_booking);
foreach ($items as $item) {
    $stmt->bind_param("iiss", $user_id, $item['product_id'], $start_date, $end_date);
    $stmt->execute();
}
$stmt->close();

// Send confirmation email
function sendBookingConfirmation($userEmail, $userName, $bookingRef, $items, $total) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // your SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'shafee.mmadi@gmail.com'; // your email
        $mail->Password   = 'anug yjfi iorp yhll';   // email app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('shafee.mmadi@gmail.com', 'OZYDE');
        $mail->addAddress($userEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = "Booking Confirmation — $bookingRef";
        $mail->Body    = "<h2>Booking Confirmed!</h2>
            <p>Hi $userName,</p>
            <p>Thank you for booking with OZYDE.</p>
            <p><strong>Booking Reference:</strong> $bookingRef</p>
            <ul>";
        foreach ($items as $item) {
            $mail->Body .= "<li>{$item['title']} — Quantity: {$item['quantity']} — R{$item['price']}</li>";
        }
        $mail->Body .= "</ul>
            <p><strong>Total:</strong> R$total</p>
            <p>We look forward to delivering your items on time!</p>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

sendBookingConfirmation($user_email, $user_name, $bookingRef, $items, $total);
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
    --nav-bg: #0b0b0b;
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
<div style="display:flex;justify-content:space-between;margin-top:8px"><div>VAT (15%)</div><div id="vat"></div></div>
<div style="display:flex;justify-content:space-between;margin-top:8px"><div>Deposit (refundable)</div><div id="deposit"></div></div>
<div style="display:flex;justify-content:space-between;margin-top:8px"><div>Delivery</div><div id="delivery"></div></div>
<div style="display:flex;justify-content:space-between;margin-top:8px"><div>Return fee</div><div id="returnFee"></div></div>
<div class="big-total" style="display:flex;justify-content:space-between"><div>Total</div><div id="totalVal"></div></div>
</div>

<div class="subtle">A confirmation email will be sent to you shortly. Keep the booking reference above for payments and communication.</div>

<div style="display:flex;gap:12px;justify-content:flex-end">
<button class="btn" id="backShop">Back to shop</button>
</div>
</div>
</div>
</main>

<script>
const items = <?= json_encode($items) ?>;
const subtotal = <?= $subtotal ?>;
const vat = <?= $vat ?>;
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
    row.innerHTML = `
        <div>
            <div style="font-weight:700">${it.title}</div>
            <div class="muted" style="margin-top:4px">${it.quantity} pcs</div>
        </div>
        <div style="font-weight:700">R${Number(it.price).toFixed(2)}</div>
    `;
    area.appendChild(row);
});

// Populate totals
document.getElementById('subtotal').textContent = 'R' + subtotal.toFixed(2);
document.getElementById('vat').textContent = 'R' + vat.toFixed(2);
document.getElementById('deposit').textContent = 'R' + deposit.toFixed(2);
document.getElementById('delivery').textContent = 'R' + deliveryFee.toFixed(2);
document.getElementById('returnFee').textContent = 'R' + returnFee.toFixed(2);
document.getElementById('totalVal').textContent = 'R' + totalVal.toFixed(2);

// Back button
document.getElementById('backShop').addEventListener('click', () => {
    window.location.href = 'loggedinnavbar.html';
});
</script>
</body>
</html>
