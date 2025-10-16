<?php
// resend_verification.php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Check if user exists and is not verified
    $sql = "SELECT user_id, first_name, verification_token FROM users WHERE email = ? AND email_verified = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Generate new token
        $newToken = bin2hex(random_bytes(32));
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update token in database
        $updateSql = "UPDATE users SET verification_token = ?, token_expires = ? WHERE user_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ssi", $newToken, $tokenExpires, $user['user_id']);
        
        if ($updateStmt->execute()) {
            // Resend verification email
            $verificationLink = "http://localhost/ozyde/verify_email.php?token=" . $newToken;
            
            $to = $email;
            $subject = 'Verify Your Email - OZYDE Boutique';
            $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #0b0b0b; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .footer { padding: 20px; text-align: center; color: #666; }
                    .verify-btn { background: #0b0b0b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Verify Your Email ✨</h1>
                    </div>
                    <div class='content'>
                        <h2>Hello {$user['first_name']},</h2>
                        <p>We received a request to resend your verification email. Click the button below to verify your account:</p>
                        
                        <p style='text-align: center;'>
                            <a href='$verificationLink' class='verify-btn'>Verify Email Address</a>
                        </p>
                        
                        <p><strong>This link will expire in 24 hours.</strong></p>
                    </div>
                    <div class='footer'>
                        <p>Best regards,<br>The OZYDE Team</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $headers = "From: ozydedesigns@gmail.com\r\n";
            $headers .= "Reply-To: ozydedesigns@gmail.com\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            if (mail($to, $subject, $message, $headers)) {
                echo "success";
            } else {
                echo "email_failed";
            }
        } else {
            echo "update_failed";
        }
    } else {
        echo "user_not_found";
    }
} else {
    echo "invalid_request";
}
?>