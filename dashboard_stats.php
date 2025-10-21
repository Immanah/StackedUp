<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$data = [];
for ($i = 11; $i >= 0; $i--) {
    $start = date('Y-m-01 00:00:00', strtotime("-{$i} months"));
    $end = date('Y-m-t 23:59:59', strtotime("-{$i} months"));
    $stmt = $mysqli->prepare("SELECT IFNULL(SUM(total_amount),0) AS total, COUNT(*) AS orders FROM orders WHERE created_at BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $data[] = ['month' => date('Y-m', strtotime($start)), 'revenue' => (float)$row['total'], 'orders' => (int)$row['orders']];
}

$u = $mysqli->query("SELECT COUNT(*) AS cnt FROM users")->fetch_assoc();
echo json_encode(['ok'=>true,'months'=>$data,'user_count'=>(int)$u['cnt']]);
