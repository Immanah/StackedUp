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
    // First, get user's name for the greeting
    $userStmt = $conn->prepare("SELECT first_name FROM users WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    
    // Fetch orders for this user
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $ordersResult = $stmt->get_result();

    $orders = [];

    while ($order = $ordersResult->fetch_assoc()) {
        // Fetch items for each order
        $stmtItems = $conn->prepare("SELECT oi.product_id, oi.quantity, oi.price, p.name AS title, p.image_url
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
                'price' => (float)$item['price'],
                'image' => $item['image_url']
            ];
        }

        // Determine order status for frontend
        $status = 'completed';
        if ($order['order_status'] === 'pending') {
            $status = 'upcoming';
        } elseif ($order['order_status'] === 'active') {
            $status = 'active';
        }

        $orders[] = [
            'id' => $order['id'],
            'orderNumber' => 'OZ-' . $order['id'],
            'total' => (float)$order['total_amount'],
            'status' => $status,
            'createdAt' => $order['created_at'],
            'rentalStart' => $order['rental_start_date'],
            'rentalEnd' => $order['rental_end_date'],
            'items' => $items
        ];
    }

    // Return both user info and orders
    echo json_encode([
        'user' => [
            'name' => $user['first_name'] ?? 'User'
        ],
        'orders' => $orders
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>