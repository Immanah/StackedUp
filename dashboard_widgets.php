<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// Totals
$total_products = $mysqli->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'] ?? 0;
$total_orders = $mysqli->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'] ?? 0;
$total_customers = $mysqli->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;

// Revenue this month
$monthStart = date('Y-m-01 00:00:00');
$stmt = $mysqli->prepare("SELECT SUM(total_amount) AS sum FROM orders WHERE created_at >= ?");
$stmt->bind_param('s', $monthStart);
$stmt->execute();
$revenue_this_month = $stmt->get_result()->fetch_assoc()['sum'] ?? 0;

// Top categories
$cats = [];
$q = "
 SELECT c.name, SUM(oi.price*oi.quantity) AS sales
 FROM order_items oi
 LEFT JOIN products p ON oi.product_id=p.product_id
 LEFT JOIN categories c ON p.category_id=c.category_id
 GROUP BY c.category_id, c.name
 ORDER BY sales DESC LIMIT 5
";
$res = $mysqli->query($q);
while($r = $res->fetch_assoc()) $cats[] = $r;

// Revenue by month
$monthly = [];
$res = $mysqli->query("
 SELECT DATE_FORMAT(created_at,'%b') AS month, SUM(total_amount) AS revenue
 FROM orders
 GROUP BY DATE_FORMAT(created_at,'%Y-%m')
 ORDER BY MIN(created_at) ASC
");
while($r = $res->fetch_assoc()) $monthly[] = $r;

echo json_encode([
  'ok'=>true,
  'total_products'=>(int)$total_products,
  'total_orders'=>(int)$total_orders,
  'total_customers'=>(int)$total_customers,
  'revenue_this_month'=>(float)$revenue_this_month,
  'top_categories'=>$cats,
  'monthly_revenue'=>$monthly
]);

