<?php
session_start();
include 'db.php';
$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];

$conn->query("DELETE FROM cart WHERE user_id=$user_id AND product_id=$product_id");
$conn->close();

header("Location: cart.php");
exit;
?>
