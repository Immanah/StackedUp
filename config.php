<?php
// config.php - DB connection and helpers
session_start();

// Adjust these to your local DB credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ozyde');

$mysqli = new mysqli('localhost', 'root', '', 'ozyde');
if ($mysqli->connect_error) {
    die('DB Connect Error: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

// Simple CSRF helpers
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
function csrf() {
    return $_SESSION['csrf_token'];
}
function check_csrf($token) {
    return isset($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// Escape helper
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
