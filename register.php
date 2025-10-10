<?php
// register.php
header('Content-Type: text/plain');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ozyde";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['signupEmail']);
    $countryCode = $_POST['countryCode'];
    $phone = trim($_POST['phone']);
    $password = $_POST['newPassword'];
    $confirm = $_POST['confirmPassword'];

    // Basic validation
    $errors = [];

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();
    
    if ($checkEmail->num_rows > 0) {
        $errors[] = "An account with this email already exists. Please use a different email or sign in.";
    }
    $checkEmail->close();

    // Validate passwords match
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match";
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    if (!preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must include at least one number";
    }
    
    if (!preg_match("/[!@#$%^&*]/", $password)) {
        $errors[] = "Password must include at least one special character (!@#$%^&*)";
    }

    // If there are errors, return them
    if (!empty($errors)) {
        echo implode(", ", $errors);
        exit;
    }

    try {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insert into DB
        $sql = "INSERT INTO users (first_name, last_name, email, password, phone, country_code, role) 
                VALUES (?, ?, ?, ?, ?, ?, 'customer')";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssss", $firstName, $lastName, $email, $hashedPassword, $phone, $countryCode);

        if ($stmt->execute()) {
            echo "success";
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }

    $conn->close();
} else {
    echo "Invalid request method";
}
?>