<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];
$action = $_POST['action'];

$q = $conn->query("SELECT quantity FROM cart WHERE user_id=$user_id AND product_id=$product_id");
if ($q && $q->num_rows > 0) {
    $row = $q->fetch_assoc();
    $qty = $row['quantity'];
    if ($action == 'inc') $qty++;
    elseif ($action == 'dec' && $qty > 1) $qty--;
    $conn->query("UPDATE cart SET quantity=$qty WHERE user_id=$user_id AND product_id=$product_id");
}
$conn->close();
header("Location: cart.php");
exit;
?>
