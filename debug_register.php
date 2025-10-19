<?php
// debug_register.php - Let's find the actual error
session_start();

// Enable ALL error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h3>Debug Mode - Checking Issues</h3>";

// Test database connection first
echo "Testing database connection...<br>";
include 'db.php';
if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
} else {
    echo "✅ Database connected successfully<br>";
}

// Test if we're receiving POST data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "✅ POST data received<br>";
    echo "POST data: ";
    print_r($_POST);
} else {
    echo "❌ No POST data received<br>";
}

// Test basic PHP functionality
echo "Testing PHP functions...<br>";
$test_password = "Test123!";
$hashed = password_hash($test_password, PASSWORD_BCRYPT);
if ($hashed) {
    echo "✅ password_hash() works<br>";
} else {
    echo "❌ password_hash() failed<br>";
}

echo "<hr><h4>If you see this entire message, the basic setup works.</h4>";
?>