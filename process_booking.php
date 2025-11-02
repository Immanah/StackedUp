<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Collect booking data
    $booking_ref = $_POST['ref'] ?? 'OZY-' . uniqid();
    $payment_method = $_POST['method'] ?? 'card';
    $total_amount = $_POST['total'] ?? 0;
    $subtotal = $_POST['subtotal'] ?? 0;
    $deposit = $_POST['deposit'] ?? 0;
    $delivery_fee = $_POST['deliveryFee'] ?? 0;
    $return_fee = $_POST['returnFee'] ?? 0;
    
    // Customer details
    $customer_data = [
        'first_name' => $_POST['customer[firstName]'] ?? '',
        'last_name' => $_POST['customer[lastName]'] ?? '',
        'email' => $_POST['customer[email]'] ?? '',
        'phone' => $_POST['customer[phone]'] ?? '',
        'address' => $_POST['customer[address]'] ?? '',
        'city' => $_POST['customer[city]'] ?? '',
        'province' => $_POST['customer[province]'] ?? '',
        'postal_code' => $_POST['customer[postalCode]'] ?? ''
    ];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Create booking record
        $booking_sql = "INSERT INTO bookings (user_id, booking_ref, payment_method, total_amount, subtotal, deposit, delivery_fee, return_fee, customer_data, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())";
        $stmt = $conn->prepare($booking_sql);
        $customer_json = json_encode($customer_data);
        $stmt->bind_param("issddddddss", $user_id, $booking_ref, $payment_method, $total_amount, $subtotal,$deposit, $delivery_fee, $return_fee, $customer_json);
        $stmt->execute();
        $booking_id = $conn->insert_id;
        
        // 2. Move cart items to booking items and clear cart
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                $booking_item_sql = "INSERT INTO booking_items (booking_id, product_id, product_name, size, quantity, price, rental_days, start_date, end_date) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($booking_item_sql);
                $stmt->bind_param("iissiidss", $booking_id, $item['product_id'], $item['name'], $item['size'], $item['quantity'], $item['price'], $item['days'], $item['start_date'], $item['end_date']);
                $stmt->execute();
            }
            
            // 3. Clear user's cart
            $clear_cart_sql = "DELETE FROM cart WHERE user_id = ?";
            $stmt = $conn->prepare($clear_cart_sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        // Store booking ID in session for success page
        $_SESSION['last_booking_id'] = $booking_id;
        $_SESSION['last_booking_ref'] = $booking_ref;
        
        // Return success
        echo json_encode(['success' => true, 'booking_ref' => $booking_ref, 'booking_id' => $booking_id]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    $conn->close();
} else {
    header("Location: checkout.php");
    exit;
}
?>