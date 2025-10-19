<?php
// register.php - FIXED VERSION FOR YOUR DATABASE
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reportING(E_ALL);

include 'db.php';

// For now, let's skip PHPMailer to make it work
// require 'vendor/autoload.php';

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
    echo "<pre>"; // Debug output
    echo "Starting registration process...\n";
    
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['signupEmail']);
    $countryCode = $_POST['countryCode'];
    $phone = trim($_POST['phone']);
    $password = $_POST['newPassword'];
    $confirm = $_POST['confirmPassword'];

    echo "Data received:\n";
    echo "First: $firstName\nLast: $lastName\nEmail: $email\nCountry: $countryCode\nPhone: $phone\n";

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
        echo "Password hashed successfully\n";

        // For now, let's do a simple insert without verification
        // This matches your actual database structure
        $sql = "INSERT INTO users (first_name, last_name, email, password, phone, country_code, role) 
                VALUES (?, ?, ?, ?, ?, ?, 'customer')";
        
        echo "SQL: $sql\n";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssssss", $firstName, $lastName, $email, $hashedPassword, $phone, $countryCode);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                echo "✅ SUCCESS! User registered with ID: $user_id\n";
                
                // Simple success - we'll add email verification later
                $_SESSION['success_message'] = "Registration successful! You can now sign in.";
                echo "<script>alert('Registration successful!'); window.location.href = 'register.html';</script>";
                exit();
                
            } else {
                echo "❌ Execute failed: " . $stmt->error . "\n";
                $errors[] = "Registration failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "❌ Prepare failed: " . $conn->error . "\n";
            $errors[] = "Database error: " . $conn->error;
        }
    }

    // Handle errors
    if (!empty($errors)) {
        echo "Errors found:\n";
        foreach ($errors as $error) {
            echo " - $error\n";
        }
        $_SESSION['registration_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        // Don't redirect - show errors on same page for debugging
        echo "<script>alert('" . implode("\\n", $errors) . "'); window.location.href = 'register.html';</script>";
        exit();
    }
    
    echo "</pre>";
} else {
    echo "No POST data received";
}

$conn->close();
?>