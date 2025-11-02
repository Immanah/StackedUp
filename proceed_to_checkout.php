<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if cart has items
if (!isset($_SESSION['cart_items']) || empty($_SESSION['cart_items'])) {
    header("Location: cart.php?error=empty_cart");
    exit;
}

// Redirect to checkout page
header("Location: checkout.php");
exit;
?>