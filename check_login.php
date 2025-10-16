<?php
// check_login.php
session_start();
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'logged' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_name' => $_SESSION['user_name'] ?? null
]);
