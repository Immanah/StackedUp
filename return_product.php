<?php
require 'db.php';

$product_id = $_POST['product_id'];
$size = $_POST['size'];

// Decrease stock by 1
$sql = "UPDATE product_sizes SET stock = stock - 1 
        WHERE product_id = ? AND size = ? AND stock > 0";

$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $product_id, $size);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "success";
} else {
    echo "out_of_stock";
}
?>
