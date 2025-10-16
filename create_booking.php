
<?php
session_start();
require 'db.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- 1. Ensure user is logged in ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// --- 2. Get POST data (from booking form) ---
$product_id = $_POST['product_id'] ?? null;
$start_date = $_POST['start_date'] ?? null;

// Validate input
if (!$product_id || !$start_date) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Calculate end date (3-day rental including delivery and return)
$start_dt = new DateTime($start_date);
$end_dt = clone $start_dt;
$end_dt->modify('+2 days'); // 3-day period including start day
$end_date = $end_dt->format('Y-m-d');

// --- 3. Insert booking into database ---
$stmt = $conn->prepare("
    INSERT INTO bookings (product_id, user_id, start_date, end_date) 
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("iiss", $product_id, $user_id, $start_date, $end_date);
$stmt->execute();
$booking_id = $stmt->insert_id;
$stmt->close();

// --- 4. Fetch booking info with user and product details ---
$sql = "SELECT b.booking_id, b.start_date, b.end_date, b.status, 
               u.name AS user_name, u.email AS user_email, 
               p.name AS product_name
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN products p ON b.product_id = p.product_id
        WHERE b.booking_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

// --- 5. Send confirmation email ---
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'shafee.mmadi@gmail.com'; // <-- replace
    $mail->Password   = 'anug yjfi iorp yhll'; // <-- replace with app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('shafee.mmadi@gmail.com', 'OZYDE');
    $mail->addAddress($booking['user_email'], $booking['user_name']);

    $mail->isHTML(true);
    $mail->Subject = "Booking Confirmation - OZYDE";
    $mail->Body = "
        <h2>Booking Confirmed!</h2>
        <p>Hi {$booking['user_name']},</p>
        <p>Your booking for <strong>{$booking['product_name']}</strong> has been confirmed.</p>
        <ul>
            <li>Start Date: {$booking['start_date']}</li>
            <li>End Date: {$booking['end_date']}</li>
            <li>Status: " . ucfirst($booking['status']) . "</li>
        </ul>
        <p>Thank you for choosing OZYDE!</p>
        <p>Your booking reference: <strong>#{$booking['booking_id']}</strong></p>
    ";

    $mail->send();
    // Optional: Log email sent or success response
} catch (Exception $e) {
    // Optional: log error to a file or database
}

// --- 6. Return JSON response ---
echo json_encode([
    'success' => true,
    'booking_id' => $booking_id,
    'start_date' => $start_date,
    'end_date' => $end_date
]);
?>
