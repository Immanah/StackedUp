<?php
// verify.php - verifies a user's email using the token
include 'db.php';
session_start();

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($email)) {
    echo "Invalid verification link.";
    exit;
}

$sql = "SELECT user_id FROM users WHERE email = ? AND verification_token = ? LIMIT 1";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        // mark verified
        $stmt->bind_result($user_id);
        $stmt->fetch();
        $update = "UPDATE users SET is_verified = 1, verification_token = NULL WHERE user_id = ?";
        if ($u = $conn->prepare($update)) {
            $u->bind_param("i", $user_id);
            $u->execute();
            $u->close();
            echo "Your email has been verified. You may now log in.";
        } else {
            echo "Verification failed.";
        }
    } else {
        echo "Invalid or expired verification link.";
    }
    $stmt->close();
} else {
    echo "Server error.";
}

$conn->close();
?>