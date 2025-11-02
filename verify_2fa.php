<?php
session_start();
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    echo json_encode(['status'=>'error','message'=>'DB connect error']); exit;
}
$mysqli->set_charset("utf8");

$temp_token = $_POST['temp_token'] ?? '';
$entered_code = trim($_POST['code'] ?? '');

if (!$temp_token || !$entered_code) {
    echo json_encode(['status'=>'error','message'=>'Token and code required']); exit;
}

// Find user by temp token
$stmt = $mysqli->prepare("SELECT user_id, twofa_code, twofa_expires, twofa_attempts FROM users WHERE twofa_temp_token = ?");
$stmt->bind_param('s', $temp_token);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['status'=>'error','message'=>'Invalid token']); exit;
}

// Check attempts
if ($user['twofa_attempts'] >= 5) {
    echo json_encode(['status'=>'error','message'=>'Too many attempts. Request a new code.']); exit;
}

// Check expiry
if (strtotime($user['twofa_expires']) < time()) {
    echo json_encode(['status'=>'error','message'=>'Code expired. Please request a new code.']); exit;
}

// Check code
if (hash_equals($user['twofa_code'], $entered_code)) {
    // success: clear 2fa fields and set session
    $stmt = $mysqli->prepare("UPDATE users SET twofa_code=NULL, twofa_expires=NULL, twofa_temp_token=NULL, twofa_attempts=0 WHERE user_id=?");
    $stmt->bind_param('i', $user['user_id']);
    $stmt->execute();
    $stmt->close();

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['logged_in'] = true;

    echo json_encode(['status'=>'success','message'=>'2FA verified']);
} else {
    // increment attempts
    $stmt = $mysqli->prepare("UPDATE users SET twofa_attempts = twofa_attempts + 1 WHERE user_id = ?");
    $stmt->bind_param('i', $user['user_id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status'=>'error','message'=>'Invalid code']);
}
