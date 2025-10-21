<?php
session_start();
require_once 'db.php';

// Google OAuth Configuration - NO SPACES!
$clientID = '771207645527-8or8trdlmec62ekl6pj63t8hae975dmi.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/ozyde/google_callback.php';

// Verify state parameter to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    unset($_SESSION['oauth2state']);
    header('Location: register.html?error=invalid_state');
    exit();
}

if (isset($_GET['code'])) {
    $authCode = $_GET['code'];
    
    // Exchange authorization code for access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = [
        'code' => $authCode,
        'client_id' => $clientID,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $tokenResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Token exchange failed: " . $tokenResponse);
        header('Location: register.html?error=token_exchange_failed');
        exit();
    }
    
    $tokenData = json_decode($tokenResponse, true);
    
    if (isset($tokenData['access_token'])) {
        // Get user info from Google
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokenData['access_token'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $userInfoResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("User info fetch failed: " . $userInfoResponse);
            header('Location: register.html?error=user_info_failed');
            exit();
        }
        
        $userInfo = json_decode($userInfoResponse, true);
        
        if (isset($userInfo['email'])) {
            // Process user registration/login
            $email = $userInfo['email'];
            $firstName = $userInfo['given_name'] ?? '';
            $lastName = $userInfo['family_name'] ?? '';
            $googleId = $userInfo['id'];
            $profilePicture = $userInfo['picture'] ?? '';
            
            // Check if user exists in database
            $checkUser = $conn->prepare("SELECT user_id, first_name, last_name, email, role FROM users WHERE email = ? OR google_id = ?");
            $checkUser->bind_param("ss", $email, $googleId);
            $checkUser->execute();
            $result = $checkUser->get_result();
            
            if ($result->num_rows > 0) {
                // User exists - log them in
                $user = $result->fetch_assoc();
                
                // Update Google ID if not set
                $updateGoogleId = $conn->prepare("UPDATE users SET google_id = ? WHERE user_id = ?");
                $updateGoogleId->bind_param("si", $googleId, $user['user_id']);
                $updateGoogleId->execute();
                $updateGoogleId->close();
                
                // Set session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['google_login'] = true;
                
                // Redirect based on role
                if ($user['role'] === 'admin' || $user['role'] === 'super_admin') {
                    header('Location: dashboard.php');
                } else {
                    header('Location: catalog.php');
                }
                exit();
                
            } else {
                // New user - create account
                $insertUser = $conn->prepare("INSERT INTO users (first_name, last_name, email, google_id, role, email_verified) VALUES (?, ?, ?, ?, 'customer', 1)");
                $insertUser->bind_param("ssss", $firstName, $lastName, $email, $googleId);
                
                if ($insertUser->execute()) {
                    $user_id = $insertUser->insert_id;
                    
                    // Set session
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = 'customer';
                    $_SESSION['login_time'] = time();
                    $_SESSION['google_login'] = true;
                    $_SESSION['new_registration'] = true;
                    
                    // Send welcome email
                    sendWelcomeEmail($email, $firstName . ' ' . $lastName, true);
                    
                    header('Location: catalog.php?welcome=1');
                    exit();
                } else {
                    // Registration failed
                    error_log("User creation failed: " . $insertUser->error);
                    header('Location: register.html?error=google_signup_failed');
                    exit();
                }
                $insertUser->close();
            }
            $checkUser->close();
        } else {
            // Failed to get user info
            header('Location: register.html?error=google_auth_failed');
            exit();
        }
    } else {
        // Failed to get access token
        header('Location: register.html?error=google_token_failed');
        exit();
    }
} else {
    // No authorization code
    header('Location: register.html?error=no_auth_code');
    exit();
}

function sendWelcomeEmail($email, $name, $isGoogleSignup = false) {
    $to = $email;
    $subject = 'Welcome to OZYDE Boutique!';
    
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
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to OZYDE! ✨</h1>
            </div>
            <div class='content'>
                <h2>Hello $name,</h2>
                <p>Thank you for joining OZYDE Boutique!" . ($isGoogleSignup ? " You've signed up using Google." : "") . "</p>
                <p>With your new account, you can:</p>
                <ul>
                    <li>Browse our exclusive dress collection</li>
                    <li>Save items to your wishlist</li>
                    <li>Rent designer dresses for special occasions</li>
                    <li>Track your orders and bookings</li>
                </ul>
                <p>Start exploring our catalog and find your perfect dress for any occasion!</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/ozyde/catalog.php' style='background: #0b0b0b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;'>Browse Catalog</a>
                </p>
            </div>
            <div class='footer'>
                <p>Best regards,<br>The OZYDE Team</p>
                <p>5 Liebenberg Rd, Noordwyk, Midrand 1687<br>Email: ozydedesigns@gmail.com</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "From: talidavhana12@gmail.com\r\n";
    $headers .= "Reply-To: talidavhana12@gmail.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    if (mail($to, $subject, $message, $headers)) {
        file_put_contents('email_log.txt', "Welcome email sent to: $email\n", FILE_APPEND);
    } else {
        file_put_contents('email_errors.txt', "Failed to send welcome email to: $email\n", FILE_APPEND);
    }
}

$conn->close();
?>