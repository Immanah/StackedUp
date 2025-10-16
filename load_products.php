<?php
require 'config.php';

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 8;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$category = $_GET['category'] ?? '';
$color = $_GET['color'] ?? '';
$minPrice = $_GET['minPrice'] ?? '';
$maxPrice = $_GET['maxPrice'] ?? '';

$where = [];

if($category) $where[] = "category='". $conn->real_escape_string($category) ."'";
if($color) $where[] = "color='". $conn->real_escape_string($color) ."'";
if($minPrice !== '') $where[] = "price >= ". floatval($minPrice);
if($maxPrice !== '') $where[] = "price <= ". floatval($maxPrice);

$whereSQL = count($where) ? "WHERE ". implode(' AND ', $where) : "";

$sql = "SELECT product_id, name, price, image, designer FROM products $whereSQL ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

$products = [];
while($row = $result->fetch_assoc()){
    $products[] = $row;
}

echo json_encode($products);
