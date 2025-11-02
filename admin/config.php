<?php
// config.php - DB connection and helpers
session_start();

// ✅ Replace these with your actual HostAfrica database credentials
define('DB_HOST', 'localhost');              // Host is usually 'localhost' on HostAfrica
define('DB_USER', 'ozyderen_ozyde');         // Your HostAfrica DB username
define('DB_PASS', '7QADxddwtwYFXSWDUWTB');   // Your HostAfrica DB password
define('DB_NAME', 'ozyderen_ozyde');         // Your HostAfrica DB name

// ✅ Create the connection
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ✅ Check connection
if ($mysqli->connect_error) {
    die('DB Connect Error: ' . $mysqli->connect_error);
}

// ✅ Set charset
$mysqli->set_charset('utf8mb4');

// ✅ CSRF token generator
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// ✅ CSRF helpers
function csrf() {
    return $_SESSION['csrf_token'];
}

function check_csrf($token) {
    return isset($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// ✅ HTML escaping helper
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
