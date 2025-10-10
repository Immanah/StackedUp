<?php
// test_setup.php
// Run this ONCE to insert a dummy user, products, order, and items for testing

require 'db.php';
session_start();

// 1. Dummy user
$conn->query("
    INSERT INTO users (user_id, name, email, password)
    VALUES (1, 'Test User', 'shafiemmadi@outlook.com', '1234567')
    ON DUPLICATE KEY UPDATE email='shafiemmadi@outlook.com'
");

// 2. Dummy products
$conn->query("INSERT IGNORE INTO products (product_id, name, price) VALUES (1, 'Evening Gown', 500.00)");
$conn->query("INSERT IGNORE INTO products (product_id, name, price) VALUES (2, 'Imported Wig', 500.00)");

// 3. Dummy order
$conn->query("
    INSERT INTO orders (order_id, user_id, total_amount, payment_status, delivery_method, order_status, created_at)
    VALUES (101, 1, 1500.00, 'paid', 'courier', 'confirmed', NOW())
    ON DUPLICATE KEY UPDATE order_status='confirmed'
");

// 4. Dummy order items
$conn->query("
    INSERT IGNORE INTO order_items (order_item_id, order_id, product_id, quantity, price)
    VALUES (1, 101, 1, 2, 500.00)
");
$conn->query("
    INSERT IGNORE INTO order_items (order_item_id, order_id, product_id, quantity, price)
    VALUES (2, 101, 2, 1, 500.00)
");

// 5. Force login session
$_SESSION['user_id'] = 1;

echo "✅ Test data inserted. You can now open confirmation.php";
