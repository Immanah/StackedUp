<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$req = json_decode(file_get_contents('php://input'), true);
if (!$req || !isset($req['csrf']) || !check_csrf($req['csrf'])) { 
    echo json_encode(['ok'=>false]); 
    exit; 
}

$order_id = (int)$req['order_id'];
$status = $mysqli->real_escape_string($req['status']);

// FIXED: Use order_status instead of status
$stmt = $mysqli->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE order_id = ?");
$stmt->bind_param('si', $status, $order_id);
$stmt->execute();

// Log the activity
$log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, 'order_status_update', ?)");
$ctx = json_encode(['order_id'=>$order_id,'order_status'=>$status]);
$log->bind_param('is', $_SESSION['admin_id'], $ctx); 
$log->execute();

echo json_encode(['ok'=>true]);

