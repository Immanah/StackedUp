<?php
session_start();
header('Content-Type: application/json');

require 'db.php'; // make sure this creates a mysqli $conn

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Prepare the query
    $stmt = $conn->prepare("
        SELECT o.order_id, o.total_amount, o.payment_status, o.order_status, o.created_at,
               oi.order_item_id, oi.product_id, oi.quantity, oi.price, p.name AS product_name
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $ordersRaw = [];
    while ($row = $result->fetch_assoc()) {
        $ordersRaw[] = $row;
    }

    // Group items by order
    $orders = [];
    foreach ($ordersRaw as $row) {
        $oid = $row['order_id'];
        if (!isset($orders[$oid])) {
            $orders[$oid] = [
                'id' => $oid,
                'createdAt' => $row['created_at'],
                'status' => $row['order_status'],
                'total' => $row['total_amount'],
                'items' => []
            ];
        }

        if ($row['order_item_id']) {
            $orders[$oid]['items'][] = [
                'id' => $row['order_item_id'],
                'title' => $row['product_name'],
                'quantity' => $row['quantity'],
                'price' => $row['price']
            ];
        }
    }

    echo json_encode(array_values($orders));

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
