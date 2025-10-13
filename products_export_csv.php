<?php
require_once __DIR__ . '/admin_auth.php';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products_export_' . date('Ymd') . '.csv');
$out = fopen('php://output', 'w');
fputcsv($out, ['product_id','name','sku','price','stock','is_rental','category_id']);
$res = $mysqli->query("SELECT product_id,name,sku,price,stock,is_rental,category_id FROM products ORDER BY product_id ASC");
while ($row = $res->fetch_assoc()) {
    fputcsv($out, [$row['product_id'],$row['name'],$row['sku'],$row['price'],$row['stock'],$row['is_rental'],$row['category_id']]);
}
fclose($out);
exit;
