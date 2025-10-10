<?php
require 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Simulated booking data
$userEmail = 'your_email@example.com'; // put your actual email here
$bookingRef = 'TEST12345';
$items = [
    ['title' => 'Evening Gown', 'quantity' => 1, 'price' => 500.00],
    ['title' => 'Wig', 'quantity' => 2, 'price' => 300.00],
];
$total = 1100.00;

// Create email
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // or your SMTP server
    $mail->SMTPAuth   = true;
    $mail->Username   = 'shafee.mmadi@gmail.com'; // your email
    $mail->Password   = 'anug yjfi iorp yhll';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('shafee.mmadi@gmail.com', 'OZYDE');
    $mail->addAddress('shafiemmadi@outlook.com');

    // Content
    $mail->isHTML(true);
    $mail->Subject = "Booking Confirmed - Reference $bookingRef";

    $itemList = '';
    foreach ($items as $it) {
        $itemList .= $it['title'] . ' x' . $it['quantity'] . ' - R' . number_format($it['price'], 2) . '<br>';
    }

    $mail->Body = "
        <h2>Booking Confirmed!</h2>
        <p>Your booking reference is: <strong>$bookingRef</strong></p>
        <p>Items booked:<br>$itemList</p>
        <p>Total: R" . number_format($total, 2) . "</p>
        <p>Thank you for booking with OZYDE!</p>
    ";

    $mail->send();
    echo 'Test email has been sent successfully!';
} catch (Exception $e) {
    echo "Email could not be sent. Error: {$mail->ErrorInfo}";
}
