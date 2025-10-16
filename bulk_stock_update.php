<?php
require_once __DIR__ . '/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_csrf($_POST['_csrf'] ?? '')) {
    header('Location: products_list.php');
    exit;
}

$action = $_POST['action'] ?? '';
if ($action === 'set') {
    $ids = $_POST['product_ids'] ?? [];
    $stockVal = (int)($_POST['set_stock'] ?? 0);
    if (!empty($ids)) {
        $stmt = $mysqli->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
        foreach ($ids as $pid) {
            $pid = (int)$pid;
            $stmt->bind_param('ii', $stockVal, $pid);
            $stmt->execute();
            $log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, 'bulk_stock_set', ?)");
            $ctx = json_encode(['product_id'=>$pid,'stock'=>$stockVal]);
            $log->bind_param('is', $_SESSION['admin_id'], $ctx);
            $log->execute();
        }
    }
} else {
    $stocks = $_POST['stock'] ?? [];
    $stmt = $mysqli->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
    foreach ($stocks as $pid => $val) {
        $pid = (int)$pid; $v = (int)$val;
        $stmt->bind_param('ii', $v, $pid);
        $stmt->execute();
        $log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, 'bulk_stock_update', ?)");
        $ctx = json_encode(['product_id'=>$pid,'stock'=>$v]);
        $log->bind_param('is', $_SESSION['admin_id'], $ctx);
        $log->execute();
    }
}

header('Location: products_list.php');
exit;
