<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$c = $mysqli->query("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0")->fetch_assoc()['cnt'] ?? 0;
echo json_encode(['count'=>(int)$c]);
