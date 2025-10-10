<?php
// =======================
// Connect to MySQL
// =======================
$host = "localhost";
$user = "root";      // change if needed
$pass = "";          // change if needed
$dbname = "ozyde";   // make sure this DB exists

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =======================
// Collect form data
// =======================
$firstName = $conn->real_escape_string($_POST['firstName'] ?? '');
$lastName  = $conn->real_escape_string($_POST['lastName'] ?? '');
$email     = $conn->real_escape_string($_POST['email'] ?? '');
$phone     = $conn->real_escape_string($_POST['phone'] ?? '');
$address   = $conn->real_escape_string($_POST['address'] ?? '');
$bust      = intval($_POST['bust'] ?? 0);
$waist     = intval($_POST['waist'] ?? 0);
$hip       = intval($_POST['hip'] ?? 0);
$styles    = isset($_POST['styles']) ? $conn->real_escape_string($_POST['styles']) : "[]";

// =======================
// Insert or Update
// =======================

// Check if this email already exists
$check = $conn->query("SELECT id FROM profiles WHERE email='$email' LIMIT 1");

if ($check && $check->num_rows > 0) {
    // Update existing profile
    $row = $check->fetch_assoc();
    $id = $row['id'];

    $sql = "UPDATE profiles 
            SET first_name='$firstName', 
                last_name='$lastName', 
                phone='$phone', 
                address='$address', 
                bust=$bust, 
                waist=$waist, 
                hip=$hip, 
                styles='$styles'
            WHERE id=$id";
} else {
    // Insert new profile
    $sql = "INSERT INTO profiles (first_name, last_name, email, phone, address, bust, waist, hip, styles)
            VALUES ('$firstName', '$lastName', '$email', '$phone', '$address', $bust, $waist, $hip, '$styles')";
}

// =======================
// Run query & show result
// =======================
if ($conn->query($sql) === TRUE) {
    echo "<p style='color:green;font-family:Arial;'>Profile saved successfully!</p>";
    echo "<a href='profile.php'>Back to Profile</a>";
} else {
    echo "<p style='color:red;'>Error: " . $conn->error . "</p>";
    echo "<a href='profile.php'>Back to Profile</a>";
}

$conn->close();
?>
