<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ozyderen_ozyde');
define('DB_USER', 'ozyderen_ozyde');
define('DB_PASS', '7QADxddwtwYFXSWDUWTB');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}


?>