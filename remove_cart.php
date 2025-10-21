<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: register.html");
    exit;
}

// Get cart_id from POST data
$cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($cart_id <= 0) {
    header("Location: cart.php");
    exit;
}

// Verify that the cart item belongs to the logged-in user and then delete it
$delete_sql = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
$stmt = $conn->prepare($delete_sql);
$stmt->bind_param("ii", $cart_id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "<script>
            alert('Item removed from cart successfully!');
            window.location.href = 'cart.php';
        </script>";
    } else {
        echo "<script>
            alert('Item not found in your cart or you do not have permission to remove it.');
            window.location.href = 'cart.php';
        </script>";
    }
} else {
    echo "<script>
        alert('Error removing item from cart. Please try again.');
        window.location.href = 'cart.php';
    </script>";
}

$stmt->close();
$conn->close();
?>