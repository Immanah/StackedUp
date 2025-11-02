<?php
session_start();
require 'db.php';
require 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter both email and password']);
        exit;
    }

    // Look up the user
    $sql = "SELECT user_id, first_name, last_name, email, password, role, email_verified, twofa_code, twofa_expires, twofa_temp_token, twofa_attempts
        FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    

    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        exit;
    }

    // Generate 6-digit verification code
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+7 minutes'));
    $temp_token = bin2hex(random_bytes(16)); // 32-char random token

    // Store code + expiry + token in DB
    $update = "UPDATE users SET twofa_code=?, twofa_expires=?, twofa_temp_token=?, twofa_attempts=0 WHERE user_id=?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param('sssi', $code, $expires, $temp_token, $user['user_id']);
    $stmt->execute();
    $stmt->close();

    // Send email with PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'talidavhana12@gmail.com';       // your Gmail address
        $mail->Password = 'kfjb gfdu gqcp hzja';        // <-- paste your app password here
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('no-reply@ozyde.com', 'Ozyde');
        $mail->addAddress($user['email']);
        $mail->Subject = 'Your Ozyde verification code';
        $mail->isHTML(true);
        $mail->Body = "
            <p>Hi {$user['first_name']},</p>
            <p>Your verification code is: <strong>{$code}</strong></p>
            <p>This code expires at {$expires}.</p>
            <p>If you didn't request this, please ignore this email.</p>
        ";

        $mail->send();

        // Tell frontend 2FA step is needed
        echo json_encode([
            'status' => '2fa_required',
            'message' => 'A verification code has been sent to your email.',
            'temp_token' => $temp_token
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Email send failed: ' . $mail->ErrorInfo]);
        exit;
    }
}
?>