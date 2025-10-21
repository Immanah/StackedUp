<?php
// register.php
session_start();
include 'db.php';
require 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_log("Register.php accessed via: " . $_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("POST data: " . print_r($_POST, true));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = trim($_POST['firstName']);
    $lastName  = trim($_POST['lastName']);
    $email     = trim($_POST['signupEmail']);
    $phone     = trim($_POST['phone']);
    $password  = $_POST['newPassword'];
    $confirm   = $_POST['confirmPassword'];

    $errors = [];

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password)) {
        $errors[] = "All fields are required";
    }

    if ($password !== $confirm) {
        $errors[] = "Passwords do not match";
    }

    if (strlen($password) < 8 || !preg_match("/[0-9]/", $password) || !preg_match("/[!@#$%^&*]/", $password)) {
        $errors[] = "Password must be at least 8 characters, include a number and a special character (!@#$%^&*)";
    }

    // Check if email exists
    $checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    $checkEmail->close();

    if (empty($errors)) {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Generate verification token and expiry
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+1 day'));

        // Insert new user (unverified)
        $sql = "INSERT INTO users (first_name, last_name, email, password, phone, role, email_verified, verification_token, verification_expires)
                VALUES (?, ?, ?, ?, ?, 'customer', 0, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $firstName, $lastName, $email, $hashedPassword, $phone, $token, $expires);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;

            // Send verification email
            if (sendVerificationEmail($email, $firstName, $token)) {
                $_SESSION['success_message'] = "Registration successful! Please check your email to verify your account.";
                header("Location: register.html");
                exit();
            } else {
                $errors[] = "Registered, but failed to send verification email. Please contact support.";
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
 * Send account verification email
 */
function sendVerificationEmail($email, $firstName, $token) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'talidavhana12@gmail.com';
        $mail->Password = 'kfjb gfdu gqcp hzja'; // your Gmail app password
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
        file_put_contents('email_errors.txt', "Verification email failed for $email: " . $mail->ErrorInfo . "\n", FILE_APPEND);
        return false;
    }
}

$conn->close();
?>