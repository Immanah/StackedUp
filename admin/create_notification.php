<?php
// create_notification.php - include or call internally; expects POST with title,message,type
require_once __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
if (!check_csrf($_POST['_csrf'] ?? '')) { http_response_code(403); echo 'bad csrf'; exit; }
$title = trim($_POST['title'] ?? '');
$msg = trim($_POST['message'] ?? '');
$type = trim($_POST['type'] ?? 'info');
$stmt = $mysqli->prepare("INSERT INTO notifications (type,title,message,related_id) VALUES (?,?,?,NULL)");
$stmt->bind_param('sss', $type, $title, $msg);
$stmt->execute();
echo json_encode(['ok'=>true,'id'=>$mysqli->insert_id]);
