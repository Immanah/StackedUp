<?php
header('Content-Type: application/json');
require 'db.php'; // include your mysqli connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$wishlist_id = isset($_POST['wishlist_id']) ? (int)$_POST['wishlist_id'] : 0;

if (!$wishlist_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid wishlist ID']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM wishlist WHERE wishlist_id = ?");
$stmt->bind_param('i', $wishlist_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close();
$conn->close();
