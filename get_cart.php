<?php
session_start();
header('Content-Type: application/json');

// Initialize cart if not already set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'view');

// ✅ Clean expired items (older than 30 minutes)
$now = time();
foreach ($_SESSION['cart'] as $pid => $item) {
    if (!isset($item['added_time'])) {
        unset($_SESSION['cart'][$pid]);
        continue;
    }
    if ($now - $item['added_time'] > 1800) { // 30 minutes
        unset($_SESSION['cart'][$pid]);
    }
}

switch ($action) {
    case 'view':
        // Fetch all valid cart items
        echo json_encode(['cart' => array_values($_SESSION['cart'])]);
        break;

    case 'update':
        $product_id = $_POST['product_id'] ?? null;
        $qty = (int)($_POST['qty'] ?? 1);

        if (!$product_id || !isset($_SESSION['cart'][$product_id])) {
            echo json_encode(['error' => 'Invalid product']);
            exit;
        }

        $_SESSION['cart'][$product_id]['qty'] = max(1, $qty);
        echo json_encode(['success' => true]);
        break;

    case 'remove':
        $product_id = $_POST['product_id'] ?? null;
        if ($product_id && isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Product not found']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>
