<?php
// api/register.php
header('Content-Type: application/json');
require_once '../controllers/AuthController.php';

$controller = new AuthController();
$controller->register();
?>