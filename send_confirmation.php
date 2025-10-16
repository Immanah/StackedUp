<?php
require 'vendor/autoload.php'; // PHPMailer autoload
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
require 'db.php'; // your database connection

if (!isset($_GET['booking_id'])) {
    echo json_encode(['error' => 'Booking ID not provided']);
    exit;
}

$booking_id = intval($_GET['booking_id']);

// Fetch booking and user details
$sql = "SELECT b.booking_id, b.start_date, b.end_date, b.status, u.name AS user_name, u.email AS user_email, p.name AS product_name
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN products p ON b.product_id = p.product_id
        WHERE b.booking_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Booking not found']);
    exit;
}

$booking = $result->fetch_assoc();
$userEmail = $booking['user_email'];
$userName = $booking['user_name'];
$productName = $booking['product_name'];
$startDate = $booking['start_date'];
$endDate = $booking['end_date'];
$status = ucfirst($booking['status']);

$stmt->close();

// Send email using PHPMailer
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'your_email@gmail.com'; // your Gmail
    $mail->Password   = 'your_app_password_here'; // Gmail App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('your_email@gmail.com', 'OZYDE');
    $mail->addAddress($userEmail, $userName);

    $mail->isHTML(true);
    $mail->Subject = "Booking Confirmation - OZYDE";
    $mail->Body = "
        <h2>Booking Confirmed!</h2>
        <p>Hi {$userName},</p>
        <p>Your booking for <strong>{$productName}</strong> has been confirmed.</p>
        <ul>
            <li>Start Date: {$startDate}</li>
            <li>End Date: {$endDate}</li>
            <li>Status: {$status}</li>
        </ul>
        <p>Thank you for choosing OZYDE! We look forward to serving you.</p>
        <p>Keep your booking reference <strong>#{$booking_id}</strong> for communication.</p>
    ";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Confirmation email sent']);
} catch (Exception $e) {
    echo json_encode(['error' => "Email could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
}
?>
