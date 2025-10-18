<?php
// register.php - CORRECTED COMBINED VERSION
session_start();
include 'db.php';
require 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email domain validation function
function validateEmailDomain($email) {
    // PHP email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['isValid' => false];
    }

    // Check for common TLD typos
    $domain = explode('@', $email)[1];
    $tldParts = explode('.', $domain);
    $tld = end($tldParts);
    $tld = strtolower($tld);

    $commonTypos = [
        'cpm' => 'com',
        'con' => 'com',
        'comm' => 'com',
        'coom' => 'com',
        'cim' => 'com',
        'vom' => 'com',
        'commm' => 'com',
        'cmo' => 'com',
        'cop' => 'com',
        'co' => 'com',
        'cm' => 'com',
        'om' => 'com',
        'ocm' => 'com'
    ];

    if (isset($commonTypos[$tld])) {
        $suggestion = str_replace('.' . $tld, '.' . $commonTypos[$tld], $domain);
        $suggestion = explode('@', $email)[0] . '@' . $suggestion;
        return [
            'isValid' => false,
            'suggestion' => $suggestion
        ];
    }

    return ['isValid' => true];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['signupEmail']);
    $countryCode = $_POST['countryCode'];
    $phone = trim($_POST['phone']);
    $password = $_POST['newPassword'];
    $confirm = $_POST['confirmPassword'];

    $errors = [];

    // Required fields validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password)) {
        $errors[] = "All fields are required";
    }

    // Email domain validation
    if (empty($errors)) {
        $emailCheck = validateEmailDomain($email);
        if (!$emailCheck['isValid']) {
            if (isset($emailCheck['suggestion'])) {
                $errors[] = "Email domain might be incorrect. Did you mean: " . $emailCheck['suggestion'] . "?";
            } else {
                $errors[] = "Please enter a valid email address";
            }
        }
    }

    // Check if email exists
    if (empty($errors)) {
        $checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $checkEmail->store_result();

        if ($checkEmail->num_rows > 0) {
            $errors[] = "An account with this email already exists. Please use a different email or sign in.";
        }
        $checkEmail->close();
    }

    // Password validation
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match";
    }

    if (strlen($password) < 8 || !preg_match("/[0-9]/", $password) || !preg_match("/[!@#$%^&*]/", $password)) {
        $errors[] = "Password must be at least 8 characters, include a number and a special character (!@#$%^&*)";
    }

    // Phone validation
    $cleanPhone = preg_replace('/\D/', '', $phone);
    if ($countryCode === '+27' && strlen($cleanPhone) !== 9) {
        $errors[] = "South African numbers must be 9 digits (without country code)";
    }

    if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 15) {
        $errors[] = "Phone number must be between 8-15 digits";
    }

    if (empty($errors)) {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Generate verification token and expiry
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+1 day'));

        // Insert new user with combined fields
        $sql = "INSERT INTO users (first_name, last_name, email, password, phone, country_code, role, email_verified, verification_token, verification_expires, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'customer', 0, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $firstName, $lastName, $email, $hashedPassword, $phone, $countryCode, $token, $expires);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Send verification email (from group member's version)
            if (sendVerificationEmail($email, $firstName, $token)) {
                $_SESSION['success_message'] = "Registration successful! Please check your email to verify your account.";
                header("Location: register.html?registration=success");
                exit();
            } else {
                // Email failed but registration succeeded
                $_SESSION['success_message'] = "Registration successful! However, verification email failed. Please contact support.";
                header("Location: register.html?registration=success");
                exit();
            }
            
        } else {
            $errors[] = "Registration failed: " . $stmt->error;
        }
        $stmt->close();
    }

    // Handle errors
    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: register.html");
        exit();
    }
}

/**
 * Send account verification email (from group member's version)
 */
function sendVerificationEmail($email, $firstName, $token) {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'talidavhana12@gmail.com'; // Update with your email
        $mail->Password = 'your_app_password'; // Update with your app password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('no-reply@ozyde.com', 'Ozyde Boutique');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Verify your OZYDE Boutique account';

        $verifyLink = "http://localhost/ozyde/verify_email.php?token=$token";

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #f7f7f7; color: #333; }
                .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; }
                .header { background: #000; color: white; padding: 15px; text-align: center; border-radius: 8px 8px 0 0; }
                .button { display: inline-block; padding: 12px 24px; background: #000; color: white; text-decoration: none; border-radius: 6px; }
                .footer { text-align: center; color: #777; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Verify Your Email</h2>
                </div>
                <p>Hi <strong>$firstName</strong>,</p>
                <p>Thank you for registering with OZYDE Boutique! To activate your account, please verify your email by clicking the button below:</p>
                <p style='text-align:center; margin:30px 0;'>
                    <a class='button' href='$verifyLink'>Verify Email</a>
                </p>
                <p>This link will expire in 24 hours.</p>
                <div class='footer'>
                    <p>© Ozyde Boutique | 5 Liebenberg Rd, Noordwyk, Midrand 1687</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Verification email failed for $email: " . $mail->ErrorInfo);
        return false;
    }
}

$conn->close();
?>