<?php
// api/login.php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../controllers/AuthController.php';

$controller = new AuthController();
$controller->login();
?>