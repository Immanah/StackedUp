<?php
// register.php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $email = $_POST['signupEmail'];
    $phone = $_POST['phone'];
    $password = $_POST['newPassword'];
    $confirm = $_POST['confirmPassword'];

    if ($password !== $confirm) {
        echo "Passwords do not match.";
        exit;
    }
    
    if (strlen($password) < 8 || 
        !preg_match("/[0-9]/", $password) || 
        !preg_match("/[!@#$%^&*]/", $password)) {
        echo "Password must be at least 8 characters, include a number and a special character (!@#$%^&*).";
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insert into DB
    $sql = "INSERT INTO users (first_name, last_name, email, password, phone) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $firstName, $lastName, $email, $hashedPassword, $phone);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>

