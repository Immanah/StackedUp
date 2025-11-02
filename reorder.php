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

// Get order ID from POST
if (!isset($_POST['orderId'])) {
    echo json_encode(['error' => 'No order ID provided']);
    exit;
}

$orderId = intval($_POST['orderId']);

try {
    // Begin transaction
    $conn->begin_transaction();

    // Fetch original order
    $stmtOrder = $conn->prepare("SELECT delivery_method FROM orders WHERE id = ? AND user_id = ?");
    $stmtOrder->bind_param("ii", $orderId, $userId);
    $stmtOrder->execute();
    $orderResult = $stmtOrder->get_result();
    $originalOrder = $orderResult->fetch_assoc();

    if (!$originalOrder) {
        throw new Exception("Original order not found");
    }

    $deliveryMethod = $originalOrder['delivery_method'];

    // Fetch items of original order
    $stmtItems = $conn->prepare("SELECT product_id, quantity, price FROM order_items WHERE order_id = ?");
    $stmtItems->bind_param("i", $orderId);
    $stmtItems->execute();
    $itemsResult = $stmtItems->get_result();

    $items = [];
    $totalAmount = 0;
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = $item;
        $totalAmount += $item['price'] * $item['quantity'];
    }

    if (empty($items)) {
        throw new Exception("No items found in the original order");
    }

    // Insert new order
    $stmtNewOrder = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_status, delivery_method, order_status, created_at) VALUES (?, ?, 'pending', ?, 'pending', NOW())");
    $stmtNewOrder->bind_param("ids", $userId, $totalAmount, $deliveryMethod);
    $stmtNewOrder->execute();
    $newOrderId = $conn->insert_id;

    // Insert order items
    $stmtNewItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($items as $item) {
        $stmtNewItem->bind_param("iiid", $newOrderId, $item['product_id'], $item['quantity'], $item['price']);
        $stmtNewItem->execute();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'newOrderId' => $newOrderId]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}
