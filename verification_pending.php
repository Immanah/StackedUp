<?php
session_start();
if (!isset($_SESSION['temp_user_id'])) {
    header("Location: register.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - Ozyde</title>
    <style>
        body { 
            font-family: 'Helvetica Neue', Arial, sans-serif; 
            background: #f8f9fa; 
            margin: 0; 
            padding: 20px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 6px 20px rgba(10, 10, 10, 0.03);
            max-width: 500px;
            text-align: center;
        }
        .success-icon { 
            font-size: 48px; 
            margin-bottom: 20px; 
        }
        h1 { 
            color: #0b0b0b; 
            margin-bottom: 16px; 
        }
        p { 
            color: #666; 
            line-height: 1.6; 
            margin-bottom: 20px; 
        }
        .resend-link { 
            color: #0b0b0b; 
            text-decoration: none; 
            font-weight: 600; 
        }
        .resend-link:hover { 
            text-decoration: underline; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">📧</div>
        <h1>Check Your Email</h1>
        <p>We've sent a verification link to <strong><?php echo htmlspecialchars($_SESSION['temp_email']); ?></strong></p>
        <p>Please click the link in the email to verify your account and start shopping with OZYDE.</p>
        <p>Didn't receive the email? <a href="resend_verification.php" class="resend-link">Resend verification email</a></p>
        <p><small>Make sure to check your spam folder if you can't find the email.</small></p>
    </div>
</body>
</html>