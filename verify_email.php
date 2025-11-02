<?php
require 'db.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Invalid verification link');
}

// Check token
$stmt = $conn->prepare("SELECT user_id, verification_expires FROM users WHERE verification_token=? AND email_verified=0");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die('Invalid or already verified link');
}

// Check expiration
if (strtotime($user['verification_expires']) < time()) {
    die('Verification link expired. Please register again.');
}

// Mark verified
$update = $conn->prepare("UPDATE users SET email_verified=1, verification_token=NULL, verification_expires=NULL WHERE user_id=?");
$update->bind_param("i", $user['user_id']);
$update->execute();
$update->close();

echo "<h2>Email verified successfully!</h2>
<p>You can now <a href='register.html'>sign in</a>.</p>";
?>
