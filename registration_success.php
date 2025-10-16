<?php
session_start();

// Check if user just registered
if (!isset($_SESSION['new_registration'])) {
    header("Location: register.html");
    exit();
}

// Clear the registration flag
unset($_SESSION['new_registration']);

$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$user_email = $_SESSION['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - OZYDE</title>
    <style>
        :root {
            --bg: #f6f6f7;
            --accent: #0b0b0b;
            --success: #2fa46b;
            --muted: #7a7a7a;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: #333;
            line-height: 1.6;
        }
        
        .success-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .success-icon {
            font-size: 80px;
            color: var(--success);
            margin-bottom: 20px;
        }
        
        .success-title {
            font-size: 32px;
            color: var(--accent);
            margin-bottom: 15px;
        }
        
        .success-message {
            color: var(--muted);
            margin-bottom: 30px;
            font-size: 18px;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            text-align: left;
        }
        
        .info-item {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--muted);
        }
        
        .next-steps {
            text-align: left;
            margin: 25px 0;
        }
        
        .steps-list {
            list-style: none;
            padding: 0;
        }
        
        .steps-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .steps-list li:before {
            content: "✓";
            color: var(--success);
            font-weight: bold;
            margin-right: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 10px;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #333;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--accent);
            border: 2px solid var(--accent);
        }
        
        .btn-outline:hover {
            background: var(--accent);
            color: white;
        }
        
        .countdown {
            margin-top: 20px;
            color: var(--muted);
            font-size: 14px;
        }
        
        .email-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">🎉</div>
        <h1 class="success-title">Welcome to OZYDE!</h1>
        <p class="success-message">Your account has been created successfully</p>
        
        <div class="user-info">
            <div class="info-item">
                <span class="info-label">Name:</span>
                <span><?php echo htmlspecialchars($user_name); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span>
                <span><?php echo htmlspecialchars($user_email); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Account Type:</span>
                <span>Customer Account</span>
            </div>
        </div>
        
        <div class="email-note">
            <strong>📧 Check your inbox!</strong> We've sent a welcome email to <?php echo htmlspecialchars($user_email); ?> 
            with more information about your account.
        </div>
        
        <div class="next-steps">
            <h3 style="margin-bottom: 15px;">What's Next?</h3>
            <ul class="steps-list">
                <li>Browse our designer dress collection</li>
                <li>Add items to your wishlist</li>
                <li>Rent dresses for your special occasions</li>
                <li>Track your orders and bookings</li>
            </ul>
        </div>
        
        <div>
            <a href="catalog.php" class="btn">Start Shopping Now</a>
            <a href="customerdashboard.php" class="btn btn-outline">Go to Dashboard</a>
        </div>
        
        <div class="countdown">
            Auto-redirecting to catalog in <span id="countdown">5</span> seconds...
        </div>
    </div>

    <script>
        // Countdown and auto-redirect
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(function() {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = 'catalog.php';
            }
        }, 1000);
        
        // Allow user to stop the redirect by clicking
        document.addEventListener('click', function() {
            clearInterval(countdown);
            countdownElement.textContent = 'redirect stopped';
        });
    </script>
</body>
</html>