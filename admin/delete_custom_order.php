<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !check_csrf($input['_csrf'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$order_id = (int)($input['order_id'] ?? 0);

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

try {
    // First, get the image URL to delete the file
    $stmt = $mysqli->prepare("SELECT image_url FROM custom_orders WHERE custom_order_id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if ($order && $order['image_url'] && file_exists('../' . $order['image_url'])) {
        unlink('../' . $order['image_url']);
    }
    
    // Delete the order
    $stmt = $mysqli->prepare("DELETE FROM custom_orders WHERE custom_order_id = ?");
    $stmt->bind_param('i', $order_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, 'custom_order_deleted', ?)");
        $ctx = json_encode(['custom_order_id' => $order_id]);
        $log->bind_param('is', $_SESSION['admin_id'], $ctx);
        $log->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete order']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>