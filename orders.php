<?php
session_start();
header('Content-Type: application/json');

require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Fetch orders for this user
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $ordersResult = $stmt->get_result();

    $orders = [];

    while ($order = $ordersResult->fetch_assoc()) {
        // Fetch items for each order
        $stmtItems = $conn->prepare("SELECT oi.product_id, oi.quantity, oi.price, p.name AS title 
                                     FROM order_items oi 
                                     JOIN products p ON oi.product_id = p.id 
                                     WHERE oi.order_id = ?");
        $stmtItems->bind_param("i", $order['id']);
        $stmtItems->execute();
        $itemsResult = $stmtItems->get_result();

        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = [
                'productId' => $item['product_id'],
                'title' => $item['title'],
                'quantity' => (int)$item['quantity'],
                'price' => (float)$item['price']
            ];
        }

        $orders[] = [
            'id' => $order['id'],
            'total' => (float)$order['total_amount'],
            'status' => $order['order_status'], // pending / complete
            'createdAt' => $order['created_at'],
            'items' => $items
        ];
    }

    echo json_encode($orders);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
