<?php
header('Content-Type: application/json');
session_start();
require_once 'db.php'; // your mysqli connection

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch wishlist items with product info
$sql = "SELECT w.id AS wishlist_id, p.product_id, p.name, p.price, p.image
        FROM wishlist w
        JOIN products p ON w.product_id = p.product_id
        WHERE w.user_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all as associative array
$items = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($items);

$stmt->close();
$conn->close();
