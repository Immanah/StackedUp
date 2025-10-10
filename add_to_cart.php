<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $size = $_POST['size'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Check if item already exists in cart
    $check_sql = "SELECT * FROM cart WHERE user_id = ? AND product_id = ? AND size = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("iis", $user_id, $product_id, $size);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing item
        $update_sql = "UPDATE cart SET start_date = ?, end_date = ? WHERE user_id = ? AND product_id = ? AND size = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssiis", $start_date, $end_date, $user_id, $product_id, $size);
    } else {
        // Insert new item
        $insert_sql = "INSERT INTO cart (user_id, product_id, size, start_date, end_date, quantity) VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iisss", $user_id, $product_id, $size, $start_date, $end_date);
    }
    
    if ($stmt->execute()) {
        header("Location: cart.php");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>