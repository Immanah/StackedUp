<?php
// resend_2fa.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mysqli = new mysqli('localhost', 'root', '', 'ozyde');

$temp_token = $_POST['temp_token'] ?? '';
if (!$temp_token) { echo json_encode(['status'=>'error','message'=>'No token']); exit; }

$stmt = $mysqli->prepare("SELECT user_id, email FROM users WHERE twofa_temp_token = ?");
$stmt->bind_param('s', $temp_token);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();
if (!$user) { echo json_encode(['status'=>'error','message'=>'Invalid token']); exit; }

// generate new code
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+7 minutes'));

$stmt = $mysqli->prepare("UPDATE users SET twofa_code=?, twofa_expires=?, twofa_attempts=0 WHERE user_id=?");
$stmt->bind_param('ssi', $code, $expires, $user['user_id']);
$stmt->execute();
$stmt->close();

// send email (use same PHPMailer SMTP config as login.php)
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'talidavhana12@gmail.com';       // your Gmail address
    $mail->Password = 'kfjb gfdu gqcp hzja';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('no-reply@yourdomain.com','Ozyde');
    $mail->addAddress($user['email']);
    $mail->Subject = 'Your new Ozyde verification code';
    $mail->isHTML(true);
    $mail->Body = "Your new verification code is <strong>{$code}</strong>. Expires at {$expires}.";

    $mail->send();
    echo json_encode(['status'=>'success','message'=>'New code sent']);
} catch (Exception $e) {
    echo json_encode(['status'=>'error','message'=>'Failed to send code: '.$mail->ErrorInfo]);
}
