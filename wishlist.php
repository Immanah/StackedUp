<?php
session_start();
require 'config.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Remove from wishlist
if(isset($_GET['remove'])){
    $wishlist_id = intval($_GET['remove']);
    $stmt = $conn->prepare("DELETE FROM wishlist WHERE wishlist_id=? AND user_id=?");
    $stmt->bind_param("ii",$wishlist_id,$user_id);
    $stmt->execute();
    header("Location: wishlist.php");
    exit;
}

// Fetch wishlist items
$stmt = $conn->prepare("
    SELECT w.wishlist_id, p.product_id, p.name, p.price, p.image
    FROM wishlist w
    JOIN products p ON w.product_id = p.product_id
    WHERE w.user_id = ?
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$wishlist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
