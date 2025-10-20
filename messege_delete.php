<?php
require_once __DIR__ . '/admin_auth.php';
if (!check_csrf($_GET['_csrf'] ?? '')) die('Bad token');
$id = (int)($_GET['id'] ?? 0);
$stmt = $mysqli->prepare("DELETE FROM messages WHERE message_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
header('Location: messages_list.php');
exit;
