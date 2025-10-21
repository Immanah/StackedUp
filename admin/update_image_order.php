<?php
// update_image_order.php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !check_csrf($input['csrf'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF']);
    exit;
}

$order = $input['order'] ?? [];
$product_id = (int)($input['product_id'] ?? 0);

foreach ($order as $item) {
    $stmt = $mysqli->prepare("UPDATE product_images SET sort_order = ? WHERE image_id = ? AND product_id = ?");
    $stmt->bind_param('iii', $item['pos'], $item['id'], $product_id);
    $stmt->execute();
}

echo json_encode(['ok' => true]);