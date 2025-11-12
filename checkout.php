<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch cart items for user with variants
$sql = "SELECT 
            c.cart_id, 
            c.product_id, 
            p.name AS product_name, 
            p.image, 
            p.price, 
            c.size, 
            c.start_date, 
            c.end_date,
            c.quantity,
            c.variant_id,
            pv.size as variant_size,
            pv.price as variant_price
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        LEFT JOIN product_variants pv ON c.variant_id = pv.variant_id
        WHERE c.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$items = []; // For JavaScript

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        try {
            $start = new DateTime($row['start_date']);
            $end = new DateTime($row['end_date']);
            $start_formatted = $start->format('M j');
            $end_formatted = $end->format('M j, Y');
            $rental_period = $start_formatted . ' - ' . $end_formatted;
        } catch (Exception $e) {
            $rental_period = 'Date not set';
        }

        // Use variant price if available, otherwise use product price
        $price = !empty($row['variant_price']) ? (float)$row['variant_price'] : (float)$row['price'];
        $size = !empty($row['variant_size']) ? $row['variant_size'] : $row['size'];

        $cart_items[] = [
            'cart_id' => $row['cart_id'],
            'product_id' => $row['product_id'],
            'name' => $row['product_name'],
            'image' => $row['image'],
            'price' => $price,
            'size' => $size,
            'quantity' => (int)$row['quantity'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'rental_period' => $rental_period,
            'variant_id' => $row['variant_id']
        ];
        
        $items[] = [
            'title' => $row['product_name'],
            'rental_period' => $rental_period,
            'price' => $price,
            'size' => $size
        ];
    }
}

// After successful payment processing, update the booking with variant support
function createBookingWithPayment($user_id, $cart_items, $payment_data) {
    global $conn;
    
    $booking_ref = 'OZ' . rand(100000, 999999);
    $total_amount = $payment_data['total'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Create booking for each cart item
        foreach ($cart_items as $item) {
            $stmt = $conn->prepare("
                INSERT INTO bookings 
                (product_id, user_id, start_date, end_date, booking_ref, total_amount, 
                 payment_status, payment_method, amount_paid, variant_id, size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $payment_status = $payment_data['method'] === 'card' ? 'paid' : 'pending';
            $variant_id = !empty($item['variant_id']) ? $item['variant_id'] : NULL;
            $size = !empty($item['size']) ? $item['size'] : '';
            
            $stmt->bind_param(
                "iisssdssdiss",
                $item['product_id'],
                $user_id,
                $item['start_date'],
                $item['end_date'],
                $booking_ref,
                $item['price'],
                $payment_status,
                $payment_data['method'],
                $payment_data['amount_paid'],
                $variant_id,
                $size
            );
            
            $stmt->execute();
            
            // Update variant stock if variant is used
            if (!empty($variant_id)) {
                $update_stock = $conn->prepare("
                    UPDATE product_variants 
                    SET stock = stock - 1 
                    WHERE variant_id = ? AND stock > 0
                ");
                $update_stock->bind_param("i", $variant_id);
                $update_stock->execute();
                $update_stock->close();
            }
        }
        
        // Clear cart
        $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $clear_cart->bind_param("i", $user_id);
        $clear_cart->execute();
        
        $conn->commit();
        return $booking_ref;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// If cart is empty, redirect back to cart
if (empty($cart_items)) {
    header("Location: cart.php");
    exit;
}

// Calculate PHP totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'];
}

$deposit = 800;
$deliveryFee = 0;
$returnFee = 0;
$total = $subtotal + $deposit;

$stmt->close();
$conn->close();
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Checkout — OZYDE</title>
    
    <!-- Load Google Maps API -->
    <script>
        function loadGoogleMaps() {
            return new Promise((resolve, reject) => {
                if (window.google && window.google.maps) {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyDNtN2PthreyRY418NuINNnihVx_eX_ifQ&loading=async&libraries=places,marker&callback=initAutocomplete';
                script.async = true;
                script.defer = true;
                
                script.onload = resolve;
                script.onerror = reject;
                
                document.head.appendChild(script);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadGoogleMaps().catch(error => {
                console.error('Failed to load Google Maps API:', error);
                showGoogleMapsError('Failed to load address suggestions. Please enter your address manually.');
            });
        });
    </script>
    
   <style>
        /* Your existing CSS styles remain exactly the same */
        :root {
            --bg: #fff;
            --text: #222;
            --muted: #7a7a7a;
            --accent: #111;
            --card-bg: #0b0b0b;
            --radius: 14px;
            --max-width: 1100px;
            --gold: #c6a04a;
            --error: #e74c3c;
            --success: #2ecc71;
            --warning: #f39c12;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            font-family: Inter, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            color: var(--text);
            background: linear-gradient(180deg, #ffffff 0%, #f5f5f7 100%);
            padding: 28px;
            display: flex;
            justify-content: center;
        }
        
        .wrap {
            width: 100%;
            max-width: var(--max-width);
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(16, 16, 24, 0.06);
            overflow: hidden;
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 28px;
            padding: 28px;
        }
        
        @media (max-width: 980px) {
            .wrap {
                grid-template-columns: 1fr;
                padding: 18px;
                gap: 18px;
            }
        }
        
        .left {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        
        .group {
            background: #fff;
            border-radius: 10px;
            padding: 16px;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        
        h2 {
            margin: 0 0 6px 0;
            font-size: 20px;
        }
        
        .muted {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .small {
            font-size: 13px;
            color: var(--muted);
        }
        
        .right {
            padding: 12px 4px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 8px;
            position: relative;
        }
        
        label {
            font-size: 13px;
            color: var(--muted);
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        select {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            font-size: 14px;
            transition: border-color 0.2s;
            width: 100%;
        }
        
        input.error {
            border-color: var(--error);
        }
        
        .error-message {
            color: var(--error);
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }
        
        .delivery-options {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        
        .opt {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #eee;
            cursor: pointer;
            font-weight: 600;
            background: #fff;
            color: var(--muted);
        }
        
        .opt.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        
        .methods {
            display: flex;
            gap: 8px;
            margin-top: 6px;
            flex-wrap: wrap;
        }
        
        .method {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #eee;
            cursor: pointer;
            font-weight: 600;
            background: #fff;
            color: var(--muted);
        }
        
        .method.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        
        .payment-row {
            display: flex;
            gap: 18px;
            align-items: start;
            flex-wrap: wrap;
        }
        
        .card-preview {
            width: 320px;
            border-radius: 12px;
            padding: 16px;
            position: sticky;
            top: 24px;
            align-self: flex-start;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.95), rgba(30, 30, 30, 0.95));
            color: #fff;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
        }
        
        @media (max-width:980px) {
            .card-preview {
                position: static;
                width: 100%;
            }
        }
        
        .card-preview .inner {
            position: relative;
            min-height: 120px;
            padding-right: 10px;
        }
        
        .card-bank {
            position: absolute;
            right: 16px;
            top: 12px;
            font-weight: 700;
            font-size: 12px;
            opacity: 0.9;
        }
        
        .card-chip {
            width: 44px;
            height: 32px;
            border-radius: 6px;
            background: linear-gradient(#eee, #bbb);
            margin-bottom: 12px;
        }
        
        .card-number {
            font-family: "Courier New", monospace;
            letter-spacing: 3px;
            font-size: 18px;
            margin-top: 8px;
            padding-right: 68px;
        }
        
        .card-name {
            text-transform: uppercase;
            font-size: 12px;
            margin-top: 12px;
        }
        
        .card-exp {
            font-size: 13px;
            opacity: 0.95;
            padding-right: 68px;
        }
        
        .card-icons {
            position: absolute;
            right: 12px;
            top: 12px;
            display: flex;
            gap: 8px;
            align-items: center;
            z-index: 5;
        }
        
        .brand-circle {
            width: 22px;
            height: 14px;
            border-radius: 3px;
            background: linear-gradient(90deg, #fff2, #fff6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #222;
            font-weight: 700;
            padding: 2px 4px;
        }
        
        .return-options {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .return-btn {
            padding: 8px 12px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #eee;
            cursor: pointer;
            font-weight: 600;
            color: var(--muted);
        }
        
        .return-btn.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            align-items: center;
        }
        
        .summary-total {
            font-weight: 800;
            font-size: 18px;
            margin-top: 8px;
        }
        
        .btn {
            padding: 12px 16px;
            border-radius: 10px;
            border: 0;
            cursor: pointer;
            background: var(--accent);
            color: #fff;
            font-weight: 700;
            transition: all 0.2s;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn.ghost {
            background: #fff;
            color: var(--accent);
            border: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        .thumb {
            width: 72px;
            height: 50px;
            border-radius: 6px;
            background: #f0f0f0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e6e6e6;
        }
        
        .notice {
            background: #fff6;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
        }
        
        .success {
            padding: 12px;
            background: #e8ffef;
            border-left: 4px solid var(--success);
            border-radius: 8px;
            color: #075;
        }
        
        .warning {
            padding: 12px;
            background: #fff8e8;
            border-left: 4px solid var(--warning);
            border-radius: 8px;
            color: #856404;
        }
        
        .error-notice {
            padding: 12px;
            background: #ffe8e8;
            border-left: 4px solid var(--error);
            border-radius: 8px;
            color: #c00;
        }
        
        .foot-note {
            font-size: 12px;
            color: var(--muted);
            margin-top: 8px;
        }
        
        .glam-line {
            height: 2px;
            background: linear-gradient(90deg, var(--gold), #ffd27a);
            border-radius: 2px;
            margin: 10px 0;
        }
        
        /* Store Reference Card Styles */
        .store-ref-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            margin-top: 16px;
            position: relative;
            overflow: hidden;
        }

        .store-ref-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--gold), #ffd27a);
        }

        .ref-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ozyde-logo {
            font-weight: 800;
            font-size: 20px;
            letter-spacing: 1px;
            color: var(--accent);
        }

        .logo-accent {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 0 2px rgba(198, 160, 74, 0.2);
        }

        .ref-badge {
            background: rgba(198, 160, 74, 0.1);
            color: var(--gold);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(198, 160, 74, 0.2);
        }

        .ref-content {
            margin-bottom: 24px;
        }

        .ref-number-container {
            text-align: center;
            margin-bottom: 24px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .ref-label {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .ref-number {
            font-family: 'Courier New', monospace;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 2px;
            color: var(--accent);
            margin: 12px 0;
            padding: 12px 20px;
            background: #fff;
            border-radius: 8px;
            border: 2px dashed rgba(198, 160, 74, 0.3);
            display: inline-block;
        }

        .ref-subtitle {
            font-size: 14px;
            color: var(--muted);
        }

        .deadline-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding: 16px;
            background: rgba(255, 245, 230, 0.5);
            border-radius: 10px;
            border-left: 4px solid var(--gold);
        }

        .deadline-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .deadline-text {
            flex: 1;
        }

        .deadline-label {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .deadline-date {
            font-weight: 700;
            color: var(--accent);
            font-size: 16px;
        }

        .ref-note {
            display: flex;
            gap: 12px;
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 8px;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.4;
        }

        .note-icon {
            flex: 0 0 auto;
            font-size: 14px;
        }

        .note-text {
            flex: 1;
        }

        .ref-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-action {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .btn-copy {
            background: var(--accent);
            color: #fff;
        }

        .btn-copy:hover {
            background: #000;
            transform: translateY(-1px);
        }

        .btn-print {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn-print:hover {
            background: rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        .btn-icon {
            font-size: 16px;
        }

        /* Animation for copy feedback */
        @keyframes copied {
            0% { background: var(--accent); }
            50% { background: #2fa46b; }
            100% { background: var(--accent); }
        }

        .copied {
            animation: copied 0.5s ease;
        }

        /* Loading spinner */
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--accent);
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .processing {
            opacity: 0.7;
            pointer-events: none;
        }

        /* First come first serve warning */
        .fcfs-warning {
            background: rgba(243, 156, 18, 0.1);
            border: 1px solid rgba(243, 156, 18, 0.3);
            border-left: 4px solid var(--warning);
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
            font-size: 13px;
        }

        /* Google Maps autocomplete styling */
        .pac-container {
            border-radius: 8px !important;
            border: 1px solid #e8e8e8 !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
            font-family: Inter, sans-serif !important;
            z-index: 10000 !important;
            margin-top: 4px !important;
            background: white !important;
        }

        .pac-item {
            padding: 8px 12px !important;
            border-bottom: 1px solid #f0f0f0 !important;
            font-size: 14px !important;
            cursor: pointer !important;
            color: #222 !important;
        }

        .pac-item:hover {
            background: #f8f8f8 !important;
        }

        .pac-item-query {
            font-size: 14px !important;
            color: #222 !important;
            font-weight: 600 !important;
        }

        .pac-matched {
            font-weight: 600 !important;
            color: #111 !important;
        }

        .pac-icon {
            margin-right: 8px !important;
        }

        /* Phone input formatting */
        .phone-hint {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Address autocomplete hint */
        .address-hint {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
            font-style: italic;
        }

        /* Map Preview Styles */
        #mapPreview {
            border: 1px solid #e8e8e8;
            background: #f9f9f9;
            transition: all 0.3s ease;
            border-radius: 8px;
            overflow: hidden;
        }

        #mapPreview:not([style*="display: none"]) {
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Read-only field styling */
        input[readonly], select[readonly] {
            background-color: #f9f9f9 !important;
            cursor: not-allowed;
            opacity: 0.9;
        }

        /* Address field states */
        .address-loading {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23757575"><path d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Z" opacity="0.5"/><path d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"/></svg>') !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
            background-size: 16px 16px !important;
        }

        .address-success {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232ecc71"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>') !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
            background-size: 16px 16px !important;
        }

        /* Google Maps error state */
        .address-error {
            color: var(--error);
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }

        .address-error.show {
            display: block;
        }

        /* New PlaceAutocompleteElement Styling */
        .gmpx-place-autocomplete {
            width: 100%;
            position: relative;
        }

        .gmpx-place-autocomplete input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            font-size: 14px;
            font-family: Inter, sans-serif;
            background: white;
        }

        .gmpx-place-autocomplete input:focus {
            outline: none;
            border-color: #111;
            box-shadow: 0 0 0 2px rgba(17, 17, 17, 0.1);
        }

        /* Override default Google styles */
        .gmpx-control {
            border: none !important;
            background: transparent !important;
        }

        .gmpx-listbox {
            background: white !important;
            border: 1px solid #e8e8e8 !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
            margin-top: 4px !important;
        }

        .gmpx-option {
            padding: 8px 12px !important;
            border-bottom: 1px solid #f0f0f0 !important;
            font-family: Inter, sans-serif !important;
            font-size: 14px !important;
            color: #222 !important;
        }

        .gmpx-option:hover {
            background: #f8f8f8 !important;
        }

        .gmpx-option[aria-selected="true"] {
            background: #111 !important;
            color: white !important;
        }
    </style>
</head>

<body>
    <main class="wrap" aria-label="Checkout page">

        <!-- LEFT: order summary & notes -->
        <section class="left" aria-hidden="false">
            <div class="group">
                <h3 style="margin:0 0 8px 0">Order Summary</h3>
                <div class="small">Items from your cart</div>

                <!-- Items list (dynamic) -->
                <div id="itemsList" style="margin-top:12px">
                    <?php foreach ($cart_items as $item): ?>
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <div>
                            <div style="font-weight:700"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="small"><?php echo htmlspecialchars($item['rental_period']); ?></div>
                            <?php if (!empty($item['size'])): ?>
                            <div class="small">Size: <?php echo htmlspecialchars($item['size']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="font-weight:700">R<?php echo number_format($item['price'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="glam-line"></div>

                <div class="summary-row">
                    <div class="small">Subtotal</div>
                    <div id="subtotal" class="small">R<?php echo number_format($subtotal, 2); ?></div>
                </div>
                
                <div class="summary-row">
                    <div class="small">Delivery fee</div>
                    <div id="deliveryFee" class="small">R0.00</div>
                </div>
                <div class="summary-row">
                    <div class="small">Return fee</div>
                    <div id="returnFee" class="small">R0.00</div>
                </div>
                <div class="summary-row">
                    <div class="small">Deposit (refundable)</div>
                    <div id="deposit" class="small">R<?php echo number_format($deposit, 2); ?></div>
                </div>

                <div class="summary-total" id="totalRow">
                    <div style="display:flex; justify-content:space-between;">
                        <div>Total</div>
                        <div id="totalAmount">R<?php echo number_format($total, 2); ?></div>
                    </div>
                </div>

                <div class="foot-note" style="margin-top:10px">
                    Deposit returned after inspection if items are returned on time and undamaged.
                </div>
            </div>
        </section>

        <!-- RIGHT: forms & payment -->
        <section class="right" aria-labelledby="checkout-heading">
            <div>
                <h2 id="checkout-heading">Checkout</h2>
                <div class="muted">Complete your details and choose a payment method</div>
            </div>

            <div class="group" aria-label="Shipping details">
                <h3 style="margin:0 0 8px 0">Shipping & Contact</h3>

                <div class="grid" style="margin-bottom:8px">
                    <div class="field">
                        <label for="firstName">First name</label>
                        <input id="firstName" type="text" placeholder="Full name" required>
                        <div class="error-message" id="firstNameError">Please enter your first name</div>
                    </div>
                    <div class="field">
                        <label for="lastName">Last name</label>
                        <input id="lastName" type="text" placeholder="Surname" required>
                        <div class="error-message" id="lastNameError">Please enter your last name</div>
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" type="email" placeholder=" " required>
                        <div class="error-message" id="emailError">Please enter a valid email address</div>
                    </div>
                    <div class="field">
                        <label for="phone">Phone</label>
                        <input id="phone" type="tel" placeholder="+27 12 345 6789" required>
                        <div class="error-message" id="phoneError">Please enter a valid phone number (10+ digits)</div>
                        <div class="phone-hint">Format: +27 XX XXX XXXX, 0XX XXX XXXX, or international numbers</div>
                    </div>
                </div>

                <!-- Google Maps Address Field -->
                <div class="field">
                    <label for="address">Delivery Address</label>
                    <input id="address" type="text" placeholder="Start typing your address..." required>
                    <div class="error-message" id="addressError">Please enter your delivery address</div>
                    <div class="address-hint">Start typing your address and select from suggestions</div>
                </div>

                <!-- Map Preview -->
                <div id="mapPreview" style="height: 200px; width: 100%; margin-top: 12px; border-radius: 8px; display: none;"></div>

                <div class="field">
                    <label for="addr2">Address Line 2 (optional)</label>
                    <input id="addr2" type="text" placeholder="Unit number, complex name, etc.">
                    <div class="address-hint">Additional address information (apartment, suite, building)</div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="city">City</label>
                        <input id="city" type="text" placeholder="" required style="background-color: #f9f9f9;">
                        <div class="error-message" id="cityError">Please enter your city</div>
                    </div>
                    <div class="field">
                        <label for="province">Province</label>
                        <select id="province" required style="background-color: #f9f9f9;">
                          <option value="">Select Province</option>
                          <option>Gauteng</option><option>Western Cape</option><option>KwaZulu-Natal</option>
                          <option>Eastern Cape</option><option>North West</option><option>Mpumalanga</option>
                          <option>Northern Cape</option><option>Free State</option><option>Limpopo</option>
                        </select>
                        <div class="error-message" id="provinceError">Please select your province</div>
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="postal">Postal code</label>
                        <input id="postal" type="text" placeholder="" required style="background-color: #f9f9f9;">
                        <div class="error-message" id="postalError">Please enter your postal code</div>
                    </div>
                    <div class="field">
                        <label for="country">Country</label>
                        <input id="country" type="text" value="South Africa" readonly>
                    </div>
                </div>

                <div style="margin-top:8px;">
                    <div class="small">Delivery options</div>
                    <div class="delivery-options" role="radiogroup" aria-label="Delivery options">
                        <button class="opt active" data-delivery="collect" id="optCollect">Collect in store (Free)</button>
                        <button class="opt" data-delivery="standard" id="optDeliver">Deliver to me (R250)</button>
                    </div>
                    
                    <div style="margin-top: 16px;">
                        <div class="small">Return options</div>
                        <div class="delivery-options" role="radiogroup" aria-label="Return options">
                            <button class="opt active" data-return="drop" id="returnDrop">Drop off in store (Free)</button>
                            <button class="opt" data-return="pickup" id="returnPickup">Arrange pickup (R120)</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- payment & card preview row -->
            <div class="payment-row">
                <!-- card preview -->
                <div class="card-preview" id="cardPreview" aria-hidden="false">
                    <div class="inner">
                        <div class="card-bank">OZYDE</div>
                        <div class="card-chip" aria-hidden="true"></div>

                        <div class="card-number" id="cardNumberPreview">0000 0000 0000 0000</div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                            <div>
                                <div style="font-size:10px; opacity:0.85">Card holder</div>
                                <div class="card-name" id="cardNamePreview">NAME SURNAME</div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-size:10px; opacity:0.85">Expires</div>
                                <div class="card-exp" id="cardExpiryPreview">MM/YY</div>
                            </div>
                        </div>

                        <div class="card-icons" aria-hidden="true" style="right:12px; top:12px;">
                            <div class="brand-circle" title="Visa">V</div>
                            <div class="brand-circle" title="Mastercard" style="background:linear-gradient(90deg,#ff5b5b,#ffd66b); color:#fff">M</div>
                        </div>
                    </div>
                </div>

                <!-- payment fields -->
                <div style="flex:1; min-width:280px;">
                    <div class="group">
                        <h3 style="margin:0 0 8px 0">Payment method</h3>
                        <div class="muted">Choose a payment option</div>
                        <div class="methods" role="tablist" aria-label="Payment options" style="margin-top:10px">
                            <button class="method active" data-method="card" role="tab" aria-selected="true">Card</button>
                            <button class="method" data-method="store" role="tab" aria-selected="false">Pay in store</button>
                            <button class="method" data-method="eft" role="tab" aria-selected="false">EFT / Bank transfer</button>
                        </div>

                        <!-- CARD FORM -->
                        <div id="method-card" style="margin-top:12px">
                            <div class="card-form">
                                <div class="field">
                                    <label for="cardNumber">Card number</label>
                                    <input id="cardNumber" type="text" inputmode="numeric" maxlength="19" placeholder="0000 0000 0000 0000" autocomplete="cc-number" aria-label="Card number">
                                    <div class="error-message" id="cardNumberError">Please enter a valid card number</div>
                                </div>

                                <div class="grid">
                                    <div class="field">
                                        <label for="cardName">Name on card</label>
                                        <input id="cardName" type="text" placeholder="Name Surname" autocomplete="cc-name">
                                        <div class="error-message" id="cardNameError">Please enter the name on card</div>
                                    </div>
                                    <div class="field">
                                        <label for="cardExpiry">Expiry (MM/YY)</label>
                                        <input id="cardExpiry" type="text" maxlength="5" placeholder="MM/YY" autocomplete="cc-exp">
                                        <div class="error-message" id="cardExpiryError">Please enter a valid expiry date</div>
                                    </div>
                                </div>
                                <div class="grid">
                                    <div class="field">
                                        <label for="cardCvv">CVV</label>
                                        <input id="cardCvv" type="text" maxlength="3" placeholder="123" autocomplete="cc-csc">
                                        <div class="error-message" id="cardCvvError">Please enter a valid CVV</div>
                                    </div>
                                    <div style="display:flex; align-items:end; gap:8px">
                                        <button id="payCardBtn" class="btn" style="width:100%">Pay <span id="payAmountText">R<?php echo number_format($total, 2); ?></span></button>
                                    </div>
                                </div>
                                <div class="foot-note">We use secure payment processing. Card details are not stored.</div>
                            </div>
                        </div>

                        <!-- PAY IN STORE -->
                        <div id="method-store" style="margin-top:12px; display:none">
                            <div class="fcfs-warning">
                                <strong>First Come First Serve:</strong> Your booking is reserved for <strong>24 hours</strong> only. If another customer pays online during this time, your reservation may be cancelled.
                            </div>
                            <div class="notice">
                                Reserve now and pay in store within <strong>24 hours</strong> (by <span id="storeDeadlineStatic"></span>). A reference will be generated for your booking.
                            </div>
                            <div style="margin-top:12px; display:flex; gap:8px">
                                <button id="generateRef" class="btn">Generate payment reference</button>
                            </div>
                            <div id="storeResult" style="margin-top:12px"></div>
                        </div>

                        <!-- EFT -->
                        <div id="method-eft" style="margin-top:12px; display:none">
                            <div class="fcfs-warning">
                                <strong>Immediate Payment Required:</strong> Your booking will be cancelled if payment is not reflected in our account within <strong>2 hours</strong>. Only use this option if you can make an immediate payment.
                            </div>
                            <div class="muted">Transfer the total amount to our bank account and upload proof of payment below.</div>
                            <div class="bank-details" style="margin-top:10px;">
                                <div style="font-weight:700">Ozyde Rentals (PTY)</div>
                                <div class="small">Bank: <strong>Standard Bank</strong></div>
                                <div class="small">Account: <strong>10202732310</strong></div>
                                <div class="small">Branch code: <strong>051001</strong> </div>
                                <div class="small">Reference: use your <strong>Full name + Surname </strong> </div>
                            </div>

                            <div style="margin-top:10px">
                                <label for="proof">Upload proof of payment (jpg/png/pdf) *</label>
                                <input id="proof" type="file" accept=".png,.jpg,.jpeg,.pdf" required>
                                <div id="proofPreview" class="upload-preview" style="display:none"></div>
                                <div style="margin-top:10px; display:flex; gap:8px">
                                    <button id="submitProof" class="btn" disabled>Submit proof</button>
                                    <button id="clearProof" class="btn ghost" style="display:none">Clear</button>
                                </div>
                                <div id="proofMsg" class="small" style="margin-top:8px"></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- finalize -->
            <div style="display:flex; gap:12px; align-items:center; justify-content:flex-end">
                <button id="cancelBtn" class="btn ghost">Cancel</button>
                <button id="finalizeBtn" class="btn">Finalize booking</button>
            </div>

            <div id="statusArea" style="margin-top:8px"></div>
        </section>

    </main>

    <script>
        // Pass PHP data to JavaScript
        const items = <?php echo json_encode($items); ?>;
        const initialSubtotal = <?php echo $subtotal; ?>;
        const initialDeposit = <?php echo $deposit; ?>;
        const initialTotal = <?php echo $total; ?>;

        // Utilities
        const q = (s) => document.querySelector(s);
        const qa = (s) => Array.from(document.querySelectorAll(s));
        const formatR = (n) => 'R' + Number(n).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        const fmtCardNumber = (v) => {
            const digits = v.replace(/\D/g, '').slice(0, 16);
            return digits.replace(/(.{4})/g, '$1 ').trim();
        };
        const fmtExpiry = (v) => {
            const d = v.replace(/\D/g, '').slice(0, 4);
            if (d.length <= 2) return d;
            return d.slice(0, 2) + '/' + d.slice(2, 4);
        };
        const randRef = (len = 6) => {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            let s = 'OZY-';
            for (let i = 0; i < len; i++) s += chars[Math.floor(Math.random() * chars.length)];
            return s;
        };
        const addHours = (d, hours) => {
            const out = new Date(d);
            out.setHours(out.getHours() + hours);
            return out;
        };
        const formatDateShort = (d) => d.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        const formatDateTime = (d) => d.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        // Enhanced validation functions
        const validateEmail = (email) => {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        };

        // FIXED: More flexible phone validation
        const validatePhone = (phone) => {
            // Remove all non-digit characters
            const digitsOnly = phone.replace(/\D/g, '');
            
            // Check if it's a valid South African number
            // +27 followed by 9 digits (total 11) OR
            // 0 followed by 9 digits (total 10) OR
            // Any international number with 10-15 digits
            if (digitsOnly.startsWith('27') && digitsOnly.length === 11) {
                return true; // +27 format
            } else if (digitsOnly.startsWith('0') && digitsOnly.length === 10) {
                return true; // 0XX format
            } else if (digitsOnly.length >= 10 && digitsOnly.length <= 15) {
                return true; // International numbers
            }
            
            return false;
        };

        const validatePostalCode = (postal) => {
            return /^\d{4}$/.test(postal);
        };

        // Enhanced card validation functions
        const validateCardNumber = (number) => {
            const cleanNumber = number.replace(/\s/g, '');
            if (!/^\d{13,19}$/.test(cleanNumber)) return false;
            
            let sum = 0;
            let isEven = false;
            
            for (let i = cleanNumber.length - 1; i >= 0; i--) {
                let digit = parseInt(cleanNumber.charAt(i), 10);
                
                if (isEven) {
                    digit *= 2;
                    if (digit > 9) {
                        digit -= 9;
                    }
                }
                
                sum += digit;
                isEven = !isEven;
            }
            
            return (sum % 10) === 0;
        };

        const validateExpiry = (expiry) => {
            if (!/^\d{2}\/\d{2}$/.test(expiry)) return false;
            
            const [month, year] = expiry.split('/').map(Number);
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear() % 100;
            const currentMonth = currentDate.getMonth() + 1;
            
            if (month < 1 || month > 12) return false;
            if (year < currentYear) return false;
            if (year === currentYear && month < currentMonth) return false;
            
            return true;
        };

        const validateCVV = (cvv) => {
            return /^\d{3,4}$/.test(cvv);
        };

        const validateName = (name) => {
            return name && name.trim().length >= 2;
        };

        // Field validation functions
        function validateField(field, value) {
            const errorElement = q(`#${field}Error`);
            let isValid = true;
            let message = '';

            switch (field) {
                case 'firstName':
                case 'lastName':
                    isValid = validateName(value);
                    message = `Please enter your ${field.replace('Name', '').toLowerCase()} name`;
                    break;
                case 'email':
                    isValid = validateEmail(value);
                    message = 'Please enter a valid email address';
                    break;
                case 'phone':
                    isValid = validatePhone(value);
                    message = 'Please enter a valid phone number (10+ digits)';
                    break;
                case 'address':
                    isValid = value && value.trim().length > 5;
                    message = 'Please enter a valid address';
                    break;
                case 'city':
                    isValid = value && value.trim().length > 0;
                    message = 'Please enter your city';
                    break;
                case 'province':
                    isValid = value && value !== '';
                    message = 'Please select your province';
                    break;
                case 'postal':
                    isValid = validatePostalCode(value);
                    message = 'Please enter a valid 4-digit postal code';
                    break;
                case 'cardNumber':
                    isValid = validateCardNumber(value);
                    message = 'Please enter a valid card number';
                    break;
                case 'cardName':
                    isValid = validateName(value);
                    message = 'Please enter the name on card';
                    break;
                case 'cardExpiry':
                    isValid = validateExpiry(value);
                    message = 'Please enter a valid expiry date (MM/YY)';
                    break;
                case 'cardCvv':
                    isValid = validateCVV(value);
                    message = 'Please enter a valid CVV';
                    break;
            }

            if (errorElement) {
                if (!isValid) {
                    errorElement.textContent = message;
                    errorElement.style.display = 'block';
                    q(`#${field}`).classList.add('error');
                } else {
                    errorElement.style.display = 'none';
                    q(`#${field}`).classList.remove('error');
                }
            }

            return isValid;
        }

        // Real-time validation
        function setupFieldValidation(fieldId, validationType) {
            const field = q(`#${fieldId}`);
            if (field) {
                field.addEventListener('blur', () => {
                    validateField(fieldId, field.value);
                });
                
                field.addEventListener('input', () => {
                    const errorElement = q(`#${fieldId}Error`);
                    if (errorElement) {
                        errorElement.style.display = 'none';
                        field.classList.remove('error');
                    }
                });
            }
        }

        // Phone number formatting and validation
        function setupPhoneValidation() {
            const phoneInput = q('#phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', (e) => {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    // Auto-format based on the number pattern
                    if (value.startsWith('27')) {
                        // +27 format
                        if (value.length > 2) value = '+27 ' + value.substring(2);
                        if (value.length > 6) value = value.substring(0, 6) + ' ' + value.substring(6);
                        if (value.length > 10) value = value.substring(0, 10) + ' ' + value.substring(10);
                    } else if (value.startsWith('0')) {
                        // 0XX format
                        if (value.length > 1) value = value.substring(0, 1) + ' ' + value.substring(1);
                        if (value.length > 5) value = value.substring(0, 5) + ' ' + value.substring(5);
                        if (value.length > 9) value = value.substring(0, 9) + ' ' + value.substring(9);
                    } else {
                        // International format - just add spaces every 3-4 digits
                        if (value.length > 3) value = value.substring(0, 3) + ' ' + value.substring(3);
                        if (value.length > 7) value = value.substring(0, 7) + ' ' + value.substring(7);
                        if (value.length > 11) value = value.substring(0, 11) + ' ' + value.substring(11);
                    }
                    
                    e.target.value = value;
                    
                    const errorElement = q('#phoneError');
                    if (errorElement) {
                        errorElement.style.display = 'none';
                        phoneInput.classList.remove('error');
                    }
                });
                
                phoneInput.addEventListener('blur', () => {
                    validateField('phone', phoneInput.value);
                });
            }
        }

        // Initialize field validations
        ['firstName', 'lastName', 'email', 'phone', 'address', 'city', 'province', 'postal', 
         'cardNumber', 'cardName', 'cardExpiry', 'cardCvv'].forEach(field => {
            setupFieldValidation(field, field);
        });

        // Initialize phone validation
        setupPhoneValidation();

        const VAT = 0.15;
        const DEPOSIT_PCT = 0.20;

        // State
        let deliveryFee = 0;
        let returnFee = 0;
        let proofFile = null;
        let activeMethod = 'card';
        let storeReferenceGenerated = false;

        // Render items & totals
        function renderSummary() {
            const subtotal = initialSubtotal;
            const deposit = initialDeposit;
            const total = subtotal + deposit + deliveryFee + returnFee;

            q('#subtotal').textContent = formatR(subtotal);
            q('#deposit').textContent = formatR(deposit);
            q('#deliveryFee').textContent = formatR(deliveryFee);
            q('#returnFee').textContent = formatR(returnFee);
            q('#totalAmount').textContent = formatR(total);
            q('#payAmountText').textContent = formatR(total);

            q('#totalAmount').dataset.value = String(total);
            q('#subtotal').dataset.value = String(subtotal);
            q('#deposit').dataset.value = String(deposit);
        }
        renderSummary();

        // Delivery options wiring
        const delButtons = [q('#optCollect'), q('#optDeliver')];
        delButtons.forEach(b => {
            b.addEventListener('click', () => {
                delButtons.forEach(x => x.classList.remove('active'));
                b.classList.add('active');
                const t = b.dataset.delivery;
                if (t === 'collect') deliveryFee = 0;
                else if (t === 'standard') deliveryFee = 250;
                renderSummary();
            });
        });

        // Return options
        const returnButtons = [q('#returnDrop'), q('#returnPickup')];
        returnButtons.forEach(b => {
            b.addEventListener('click', () => {
                returnButtons.forEach(x => x.classList.remove('active'));
                b.classList.add('active');
                const method = b.dataset.return;
                if (method === 'drop') returnFee = 0;
                else if (method === 'pickup') returnFee = 120;
                renderSummary();
            });
        });

        // Payment method toggles
        qa('.methods .method').forEach(btn => {
            btn.addEventListener('click', () => {
                qa('.methods .method').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const m = btn.dataset.method;
                showMethod(m);
            });
        });

        function showMethod(m) {
            activeMethod = m || 'card';
            q('#method-card').style.display = (activeMethod === 'card') ? '' : 'none';
            q('#method-store').style.display = (activeMethod === 'store') ? '' : 'none';
            q('#method-eft').style.display = (activeMethod === 'eft') ? '' : 'none';
            q('#statusArea').innerHTML = '';
        }
        showMethod('card');

        // Set static store deadline (24 hours)
        const storeDeadlineStatic = q('#storeDeadlineStatic');
        if (storeDeadlineStatic) {
            const dl = addHours(new Date(), 24);
            storeDeadlineStatic.textContent = formatDateTime(dl);
        }

        // Card preview bindings
        const cardNumberInput = q('#cardNumber');
        const cardNameInput = q('#cardName');
        const cardExpiryInput = q('#cardExpiry');
        const cardCvvInput = q('#cardCvv');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', (e) => {
                e.target.value = fmtCardNumber(e.target.value);
                q('#cardNumberPreview').textContent = e.target.value || '0000 0000 0000 0000';
                validateField('cardNumber', e.target.value);
            });
        }
        if (cardNameInput) {
            cardNameInput.addEventListener('input', (e) => {
                q('#cardNamePreview').textContent = (e.target.value || 'NAME SURNAME').toUpperCase();
                validateField('cardName', e.target.value);
            });
        }
        if (cardExpiryInput) {
            cardExpiryInput.addEventListener('input', (e) => {
                e.target.value = fmtExpiry(e.target.value);
                q('#cardExpiryPreview').textContent = e.target.value || 'MM/YY';
                validateField('cardExpiry', e.target.value);
            });
        }
        if (cardCvvInput) {
            cardCvvInput.addEventListener('focus', () => {
                q('#cardPreview').style.transform = 'translateY(-2px) scale(1.01)';
            });
            cardCvvInput.addEventListener('blur', () => {
                q('#cardPreview').style.transform = '';
                validateField('cardCvv', cardCvvInput.value);
            });
        }

        // Enhanced shipping details validation
        function validateShippingDetails() {
            const fields = [
                'firstName', 'lastName', 'email', 'phone', 
                'address', 'city', 'province', 'postal'
            ];
            
            let isValid = true;
            fields.forEach(field => {
                const value = q(`#${field}`).value;
                if (!validateField(field, value)) {
                    isValid = false;
                }
            });
            
            return isValid;
        }

        // Card pay with actual server processing
        const payCardBtn = q('#payCardBtn');
        if (payCardBtn) {
            payCardBtn.addEventListener('click', (ev) => {
                ev.preventDefault();
                
                if (!validateShippingDetails()) {
                    q('#statusArea').innerHTML = `<div class="error-notice">Please fix the errors in your shipping details before proceeding.</div>`;
                    return;
                }

                const num = (q('#cardNumber') && q('#cardNumber').value.replace(/\s/g, '')) || '';
                const name = (q('#cardName') && q('#cardName').value.trim()) || '';
                const exp = (q('#cardExpiry') && q('#cardExpiry').value) || '';
                const cvv = (q('#cardCvv') && q('#cardCvv').value) || '';
                
                const cardFieldsValid = [
                    validateField('cardNumber', num),
                    validateField('cardName', name),
                    validateField('cardExpiry', exp),
                    validateField('cardCvv', cvv)
                ].every(valid => valid);
                
                if (!cardFieldsValid) {
                    q('#statusArea').innerHTML = `<div class="error-notice">Please fix the errors in your card details before proceeding.</div>`;
                    return;
                }

                payCardBtn.innerHTML = '<span class="spinner"></span> Processing...';
                payCardBtn.classList.add('processing');
                payCardBtn.disabled = true;

                // Actually process the payment and create booking
                const total = Number(q('#totalAmount').dataset.value || 0);
                
                fetch('process_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        method: 'card',
                        total: total,
                        amount_paid: total
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        q('#statusArea').innerHTML = `<div class="success" role="status">Payment successful — booking confirmed. Redirecting to confirmation…</div>`;
                        
                        // Store in sessionStorage for success page
                        const booking = {
                            ref: data.booking_ref,
                            method: 'card',
                            status: 'confirmed',
                            items: items,
                            total: total,
                            name: (q('#firstName') && q('#firstName').value.trim()) || '',
                            email: (q('#email') && q('#email').value.trim()) || '',
                            createdAt: new Date().toISOString()
                        };
                        
                        try {
                            sessionStorage.setItem('ozyde_booking', JSON.stringify(booking));
                        } catch (err) {
                            console.warn('sessionStorage set failed', err);
                        }

                        setTimeout(() => {
                            window.location.href = 'success.php?ref=' + data.booking_ref;
                        }, 1500);
                    } else {
                        throw new Error(data.error || 'Payment failed');
                    }
                })
                .catch(error => {
                    payCardBtn.innerHTML = 'Pay <span id="payAmountText">R' + formatR(total) + '</span>';
                    payCardBtn.classList.remove('processing');
                    payCardBtn.disabled = false;
                    q('#statusArea').innerHTML = `<div class="error-notice">Payment failed: ${error.message}</div>`;
                });
            });
        }

        // Pay-in-store: generate ref & deadline (24 hours)
        const genRefBtn = q('#generateRef');
        if (genRefBtn) {
            genRefBtn.addEventListener('click', () => {
                if (!validateShippingDetails()) {
                    q('#statusArea').innerHTML = `<div class="error-notice">Please complete your shipping details first.</div>`;
                    return;
                }

                const ref = randRef(6);
                const deadline = addHours(new Date(), 24);
                
                const firstName = (q('#firstName') && q('#firstName').value.trim()) || '';
                const lastName = (q('#lastName') && q('#lastName').value.trim()) || '';
                const customerName = `${firstName} ${lastName}`.trim() || 'Customer';
                
                q('#storeResult').innerHTML = `
                    <div class="store-ref-card">
                        <div class="ref-header">
                            <div class="brand-logo">
                                <span class="ozyde-logo">OZYDE</span>
                                <div class="logo-accent"></div>
                            </div>
                            <div class="ref-badge">Payment Reference</div>
                        </div>
                        
                        <div class="ref-content">
                            <div class="ref-number-container">
                                <div class="ref-label">Your Reference Code</div>
                                <div class="ref-number" id="refNumberDisplay">${ref}</div>
                                <div class="ref-subtitle">Present this code when paying in store</div>
                            </div>
                            
                            <div class="deadline-container">
                                <div class="deadline-icon">⏰</div>
                                <div class="deadline-text">
                                    <div class="deadline-label">Payment Due By (24 hours)</div>
                                    <div class="deadline-date">${formatDateTime(deadline)}</div>
                                </div>
                            </div>
                            
                            <div class="ref-note">
                                <div class="note-icon">⚠️</div>
                                <div class="note-text"><strong>First Come First Serve:</strong> Your reservation is held for 24 hours only. If another customer pays online, your booking may be cancelled.</div>
                            </div>
                        </div>
                        
                        <div class="ref-actions">
                            <button id="copyRefBtn" class="btn-action btn-copy">
                                <span class="btn-icon">📋</span>
                                Copy Reference
                            </button>
                            <button id="printRef" class="btn-action btn-print">
                                <span class="btn-icon">🖨️</span>
                                Print / Save
                            </button>
                            <button id="confirmStorePayment" class="btn-action" style="background: #ffb300; color: #000;">
                                <span class="btn-icon">✓</span>
                                Confirm Reservation
                            </button>
                        </div>
                    </div>
                `;

                storeReferenceGenerated = true;

                setTimeout(() => {
                    const copyRefBtn = q('#copyRefBtn');
                    if (copyRefBtn) {
                        copyRefBtn.addEventListener('click', () => {
                            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                                navigator.clipboard.writeText(ref).then(() => {
                                    copyRefBtn.innerHTML = '<span class="btn-icon">✓</span> Copied!';
                                    copyRefBtn.classList.add('copied');
                                    setTimeout(() => {
                                        copyRefBtn.innerHTML = '<span class="btn-icon">📋</span> Copy Reference';
                                        copyRefBtn.classList.remove('copied');
                                    }, 1500);
                                }).catch(() => {
                                    fallbackCopy(ref, copyRefBtn);
                                });
                            } else fallbackCopy(ref, copyRefBtn);
                        });
                    }
                    
                    const printRef = q('#printRef');
                    if (printRef) {
                        printRef.addEventListener('click', () => {
                            const printWindow = window.open('', '_blank');
                            if (printWindow) {
                                printWindow.document.write(`
                                    <!DOCTYPE html>
                                    <html>
                                    <head>
                                        <title>OZYDE Payment Slip</title>
                                        <style>
                                            @media print {
                                                @page { margin: 0.5cm; size: auto; }
                                                body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.4; }
                                                .payment-slip { max-width: 8.5cm; margin: 0 auto; padding: 15px; border: 1px solid #000; }
                                                .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
                                                .logo { font-size: 24px; font-weight: bold; letter-spacing: 2px; margin-bottom: 5px; }
                                                .subtitle { font-size: 12px; color: #666; }
                                                .section { margin-bottom: 15px; }
                                                .section-title { font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 8px; }
                                                .reference { font-family: monospace; font-size: 18px; font-weight: bold; text-align: center; margin: 10px 0; padding: 8px; background: #f5f5f5; border: 1px dashed #ccc; }
                                                .total { font-size: 16px; font-weight: bold; text-align: center; margin: 10px 0; }
                                                .footer { margin-top: 20px; font-size: 10px; color: #666; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; }
                                                .barcode { text-align: center; margin: 10px 0; font-family: monospace; letter-spacing: 3px; }
                                                .warning { background: #fff8e8; padding: 8px; border-radius: 4px; margin: 10px 0; font-size: 12px; }
                                            }
                                        </style>
                                    </head>
                                    <body>
                                        <div class="payment-slip">
                                            <div class="header">
                                                <div class="logo">OZYDE</div>
                                                <div class="subtitle">LUXURY RENTALS</div>
                                            </div>
                                            
                                            <div class="section">
                                                <div class="section-title">PAYMENT REFERENCE</div>
                                                <div class="reference">${ref}</div>
                                            </div>
                                            
                                            <div class="section">
                                                <div class="section-title">CUSTOMER DETAILS</div>
                                                <div><strong>Name:</strong> ${customerName}</div>
                                                <div><strong>Date:</strong> ${new Date().toLocaleDateString()}</div>
                                                <div><strong>Due Date:</strong> ${formatDateTime(deadline)}</div>
                                            </div>
                                            
                                            <div class="warning">
                                                <strong>FIRST COME FIRST SERVE:</strong><br>
                                                Valid for 24 hours only
                                            </div>
                                            
                                            <div class="section">
                                                <div class="section-title">ORDER SUMMARY</div>
                                                ${items.map(item => `<div>${item.title} - ${item.rental_period}</div>`).join('')}
                                                <div class="total">TOTAL: ${formatR(Number(q('#totalAmount').dataset.value || 0))}</div>
                                            </div>
                                            
                                            <div class="barcode">
                                                ||| ${ref} |||
                                            </div>
                                            
                                            <div class="footer">
                                                <div>Please present this slip when paying in store</div>
                                                <div>OZYDE Boutique • 123 Fashion District • Johannesburg</div>
                                                <div>Tel: +27 11 123 4567</div>
                                            </div>
                                        </div>
                                    </body>
                                    </html>
                                `);
                                printWindow.document.close();
                                
                                setTimeout(() => {
                                    printWindow.print();
                                    printWindow.close();
                                }, 250);
                            }
                        });
                    }

                    const confirmStorePayment = q('#confirmStorePayment');
                    if (confirmStorePayment) {
                        confirmStorePayment.addEventListener('click', () => {
                            if (!validateShippingDetails()) {
                                q('#statusArea').innerHTML = `<div class="error-notice">Please complete your shipping details first.</div>`;
                                return;
                            }

                            confirmStorePayment.innerHTML = '<span class="spinner"></span> Processing...';
                            confirmStorePayment.classList.add('processing');
                            
                            const total = Number(q('#totalAmount').dataset.value || 0);
                            const ref = randRef(6);
                            
                            fetch('process_booking.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    method: 'store',
                                    total: total,
                                    amount_paid: 0
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const booking = {
                                        ref: data.booking_ref,
                                        method: 'store',
                                        status: 'pending',
                                        items: items,
                                        subtotal: Number(q('#subtotal').dataset.value || 0),
                                        total: total,
                                        name: customerName,
                                        email: (q('#email') && q('#email').value.trim()) || '',
                                        phone: (q('#phone') && q('#phone').value.trim()) || '',
                                        address: (q('#address') && q('#address').value.trim()) || '',
                                        createdAt: new Date().toISOString(),
                                        deadline: deadline.toISOString(),
                                        expires_at: deadline.toISOString()
                                    };
                                    
                                    try {
                                        sessionStorage.setItem('ozyde_booking', JSON.stringify(booking));
                                    } catch (e) {}
                                    
                                    setTimeout(() => {
                                        window.location.href = 'pending.php?method=store&ref=' + data.booking_ref;
                                    }, 1000);
                                } else {
                                    throw new Error(data.error || 'Booking creation failed');
                                }
                            })
                            .catch(error => {
                                confirmStorePayment.innerHTML = 'Confirm Reservation';
                                confirmStorePayment.classList.remove('processing');
                                q('#statusArea').innerHTML = `<div class="error-notice">Reservation failed: ${error.message}</div>`;
                            });
                        });
                    }
                }, 50);
            });
        }

        function fallbackCopy(text, btn) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                btn.innerHTML = '<span class="btn-icon">✓</span> Copied!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.innerHTML = '<span class="btn-icon">📋</span> Copy Reference';
                    btn.classList.remove('copied');
                }, 1500);
            } catch (e) {
                alert('Could not copy — please copy manually: ' + text);
            }
            document.body.removeChild(ta);
        }

        // EFT proof upload with 2-hour deadline
        const proofInput = q('#proof');
        const proofPreview = q('#proofPreview');
        const submitProof = q('#submitProof');
        const clearProofBtn = q('#clearProof');
        if (proofInput) {
            proofInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                const maxSize = 5 * 1024 * 1024;
                
                if (!validTypes.includes(file.type)) {
                    q('#proofMsg').textContent = 'Please upload a JPG, PNG, or PDF file.';
                    proofInput.value = '';
                    return;
                }
                
                if (file.size > maxSize) {
                    q('#proofMsg').textContent = 'File size must be less than 5MB.';
                    proofInput.value = '';
                    return;
                }
                
                proofFile = file;
                proofPreview.style.display = '';
                proofPreview.innerHTML = '';
                const thumb = document.createElement('div');
                thumb.className = 'thumb';
                if (file.type === 'application/pdf') {
                    thumb.innerHTML = '<div style="text-align:center; padding:10px;"><span style="font-size:24px;">📄</span><div class="small">PDF</div></div>';
                    proofPreview.appendChild(thumb);
                    const meta = document.createElement('div');
                    meta.className = 'meta';
                    meta.innerHTML = `<div>${file.name}</div><div class="small">${Math.round(file.size/1024)} KB</div>`;
                    proofPreview.appendChild(meta);
                } else {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        thumb.innerHTML = `<img src="${ev.target.result}" alt="proof" style="width:100%; height:100%; object-fit:cover;">`;
                        proofPreview.appendChild(thumb);
                        const meta = document.createElement('div');
                        meta.className = 'meta';
                        meta.innerHTML = `<div>${file.name}</div><div class="small">${Math.round(file.size/1024)} KB</div>`;
                        proofPreview.appendChild(meta);
                    };
                    reader.readAsDataURL(file);
                }
                if (submitProof) submitProof.disabled = false;
                if (clearProofBtn) clearProofBtn.style.display = '';
                q('#proofMsg').textContent = '';
            });
        }
        if (submitProof) {
            submitProof.addEventListener('click', () => {
                if (!validateShippingDetails()) {
                    q('#statusArea').innerHTML = `<div class="error-notice">Please complete your shipping details first.</div>`;
                    return;
                }

                if (!proofFile) {
                    q('#proofMsg').textContent = 'Please upload proof of payment before submitting.';
                    return;
                }
                
                submitProof.innerHTML = '<span class="spinner"></span> Submitting...';
                submitProof.disabled = true;
                
                const total = Number(q('#totalAmount').dataset.value || 0);
                
                fetch('process_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        method: 'eft',
                        total: total,
                        amount_paid: 0
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const verificationDeadline = addHours(new Date(), 2);
                        
                        const booking = {
                            ref: data.booking_ref,
                            method: 'eft',
                            status: 'pending_verification',
                            items: items,
                            subtotal: Number(q('#subtotal').dataset.value || 0),
                            total: total,
                            name: (q('#firstName') && q('#firstName').value.trim()) + ' ' + (q('#lastName') && q('#lastName').value.trim()),
                            email: (q('#email') && q('#email').value.trim()) || '',
                            phone: (q('#phone') && q('#phone').value.trim()) || '',
                            address: (q('#address') && q('#address').value.trim()) || '',
                            createdAt: new Date().toISOString(),
                            proofUploaded: true,
                            verification_deadline: verificationDeadline.toISOString(),
                            expires_at: verificationDeadline.toISOString()
                        };
                        
                        try {
                            sessionStorage.setItem('ozyde_booking', JSON.stringify(booking));
                        } catch (e) {}
                        
                        q('#statusArea').innerHTML = `<div class="warning">Proof submitted. Your order is pending verification and will be cancelled if payment is not reflected within 2 hours. Redirecting...</div>`;
                        
                        setTimeout(() => {
                            window.location.href = 'pending.php?method=eft&ref=' + data.booking_ref;
                        }, 1500);
                    } else {
                        throw new Error(data.error || 'Booking creation failed');
                    }
                })
                .catch(error => {
                    submitProof.innerHTML = 'Submit proof';
                    submitProof.disabled = false;
                    q('#statusArea').innerHTML = `<div class="error-notice">Submission failed: ${error.message}</div>`;
                });
            });
        }
        if (clearProofBtn) {
            clearProofBtn.addEventListener('click', () => {
                if (proofInput) proofInput.value = '';
                proofFile = null;
                proofPreview.style.display = 'none';
                if (submitProof) submitProof.disabled = true;
                clearProofBtn.style.display = 'none';
                q('#proofMsg').textContent = '';
            });
        }

        // Finalize booking validation
        q('#finalizeBtn').addEventListener('click', (e) => {
            e.preventDefault();
            
            if (!validateShippingDetails()) {
                q('#statusArea').innerHTML = `<div class="error-notice">Please complete your shipping details before finalizing.</div>`;
                return;
            }
            
            if (activeMethod === 'card') {
                q('#statusArea').innerHTML = `<div class="notice">Please press <strong>Pay</strong> to complete card payment and confirm your booking.</div>`;
                return;
            }
            
            if (activeMethod === 'store') {
                if (!storeReferenceGenerated) {
                    q('#statusArea').innerHTML = `<div class="notice">Please generate a payment reference first to confirm your store payment reservation.</div>`;
                    return;
                }
                q('#statusArea').innerHTML = `<div class="notice">Please click "Confirm Reservation" in the payment reference section to complete your booking.</div>`;
                return;
            }
            
            if (activeMethod === 'eft') {
                if (!proofFile) {
                    q('#statusArea').innerHTML = `<div class="notice">Please upload proof of payment and submit it to confirm your EFT booking.</div>`;
                    return;
                }
                q('#statusArea').innerHTML = `<div class="notice">Please click "Submit proof" to complete your EFT booking.</div>`;
                return;
            }
        });

        q('#cancelBtn').addEventListener('click', () => {
            if (confirm('Cancel checkout and return to shop?')) window.location.href = 'cart.php';
        });

        // ========== GOOGLE MAPS PLACES AUTCOMPLETE IMPLEMENTATION ==========
        
        let autocomplete = null;
        let map = null;
        let marker = null;

        function initAutocomplete() {
            console.log("🚀 Google Maps API (New) loaded successfully");
            
            const addressInput = document.getElementById("address");
            const mapPreview = document.getElementById("mapPreview");

            if (!addressInput) {
                console.error("❌ Address input element not found");
                return;
            }

            // Check if new API is available
            if (typeof google === 'undefined' || !google.maps || !google.maps.places || !google.maps.places.PlaceAutocompleteElement) {
                console.error("❌ New Places API not available");
                showGoogleMapsError("New address suggestions not available. Please enter address manually.");
                return;
            }

            try {
                // Create the new PlaceAutocompleteElement
                const options = {
                    inputElement: addressInput,
                    componentRestrictions: { country: 'za' }
                };

                console.log("🔄 Creating PlaceAutocompleteElement with options:", options);
                
                autocomplete = new google.maps.places.PlaceAutocompleteElement(options);
                
                // Create a container for the autocomplete
                const container = document.createElement('div');
                container.className = 'gmpx-place-autocomplete';
                
                // Insert the container after the address input
                addressInput.parentNode.insertBefore(container, addressInput.nextSibling);
                container.appendChild(autocomplete);

                console.log("✅ PlaceAutocompleteElement created and added to DOM");

                // Initialize map with AdvancedMarkerElement if available
                if (mapPreview) {
                    map = new google.maps.Map(mapPreview, {
                        center: { lat: -26.2041, lng: 28.0473 },
                        zoom: 10,
                        styles: [
                            {
                                featureType: "all",
                                elementType: "geometry",
                                stylers: [{ color: "#f5f5f5" }]
                            }
                        ],
                        disableDefaultUI: true,
                        zoomControl: true,
                        mapId: 'ozyde_map'
                    });

                    // Use AdvancedMarkerElement if available, fallback to regular Marker
                    if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
                        marker = new google.maps.marker.AdvancedMarkerElement({
                            map: map,
                            position: { lat: -26.2041, lng: 28.0473 },
                            title: "Selected location"
                        });
                    } else {
                        // Fallback to regular Marker
                        marker = new google.maps.Marker({ 
                            map: map,
                            position: { lat: -26.2041, lng: 28.0473 },
                            title: "Selected location"
                        });
                    }

                    console.log("✅ Map and marker initialized");
                }

                // Add event listener for place selection
                autocomplete.addEventListener('gmp-placeselect', async (event) => {
                    console.log("📍 Place selected with new API");
                    await onPlaceSelected(event.place);
                });

                // Sync the autocomplete value with the original input and handle input changes
                autocomplete.addEventListener('input', function() {
                    // Update the original input field
                    addressInput.value = autocomplete.value;
                    
                    // Show loading state for longer inputs
                    if (autocomplete.value.length > 2) {
                        addressInput.classList.add('address-loading');
                        addressInput.classList.remove('address-success');
                    } else {
                        addressInput.classList.remove('address-loading', 'address-success');
                        clearAddressFields();
                    }
                    
                    // Hide map when typing
                    if (mapPreview) mapPreview.style.display = 'none';
                    if (marker) {
                        if (marker.setMap) marker.setMap(null);
                        if (marker.map) marker.map = null;
                    }
                    
                    // Clear errors
                    clearAddressError();
                });

                // Hide the original input since we're using the autocomplete element
                addressInput.style.display = 'none';

                console.log("✅ New Google Maps Places API fully initialized");

            } catch (error) {
                console.error("❌ Error initializing new Places API:", error);
                // Fallback: show the original input
                addressInput.style.display = 'block';
                const autocompleteContainer = document.querySelector('.gmpx-place-autocomplete');
                if (autocompleteContainer) {
                    autocompleteContainer.remove();
                }
                setupManualAddressInput();
            }
        }

        async function onPlaceSelected(place) {
            console.log("📍 Place selected:", place);
            
            const addressInput = document.getElementById("address");
            const mapPreview = document.getElementById("mapPreview");

            if (!place) {
                console.log("❌ No place selected");
                return;
            }

            try {
                // Show success state
                addressInput.classList.remove('address-loading');
                addressInput.classList.add('address-success');

                // Fetch additional place details
                console.log("🔄 Fetching place details...");
                const placeWithDetails = await place.fetchFields({
                    fields: ['location', 'formattedAddress', 'addressComponents']
                });

                console.log("📍 Place details fetched:", placeWithDetails);

                // Update the original input with the formatted address
                if (placeWithDetails.formattedAddress && addressInput) {
                    addressInput.value = placeWithDetails.formattedAddress;
                }

                // Update map with location
                if (mapPreview && map && placeWithDetails.location) {
                    mapPreview.style.display = 'block';
                    map.setCenter(placeWithDetails.location);
                    map.setZoom(15);
                    
                    // Update marker position
                    if (marker.position) {
                        marker.position = placeWithDetails.location;
                    } else if (marker.setPosition) {
                        marker.setPosition(placeWithDetails.location);
                    }
                    
                    if (marker.map) {
                        marker.map = map;
                    } else if (marker.setMap) {
                        marker.setMap(map);
                    }
                }

                // Parse address components
                if (placeWithDetails.addressComponents && placeWithDetails.addressComponents.length > 0) {
                    console.log("📍 Using addressComponents:", placeWithDetails.addressComponents);
                    parseNewAddressComponents(placeWithDetails.addressComponents);
                } else if (placeWithDetails.formattedAddress) {
                    console.log("📍 Using formattedAddress:", placeWithDetails.formattedAddress);
                    extractAddressFromFormatted(placeWithDetails.formattedAddress);
                } else if (autocomplete.value) {
                    console.log("📍 Using autocomplete value:", autocomplete.value);
                    extractAddressFromFormatted(autocomplete.value);
                } else {
                    console.log("❌ No address data available");
                }

                // Validate the address field
                validateField('address', addressInput.value);

            } catch (error) {
                console.error("❌ Error processing selected place:", error);
                // Fallback: try to extract from the autocomplete value
                if (autocomplete && autocomplete.value) {
                    console.log("🔄 Fallback: extracting from autocomplete value");
                    extractAddressFromFormatted(autocomplete.value);
                } else if (addressInput && addressInput.value) {
                    console.log("🔄 Fallback: extracting from address input value");
                    extractAddressFromFormatted(addressInput.value);
                }
            }
        }

        function parseNewAddressComponents(components) {
            console.log("📍 Parsing new API address components:", components);
            
            const addressData = {
                streetNumber: '',
                route: '',
                locality: '',
                administrativeArea: '',
                postalCode: '',
                subpremise: ''
            };

            components.forEach(component => {
                const types = component.types;
                
                console.log("📍 Component:", component, "Types:", types);
                
                if (types.includes('street_number')) {
                    addressData.streetNumber = component.longText || component.shortText || '';
                } else if (types.includes('route')) {
                    addressData.route = component.shortText || component.longText || '';
                } else if (types.includes('locality') || types.includes('postal_town')) {
                    addressData.locality = component.longText || '';
                } else if (types.includes('administrative_area_level_1')) {
                    addressData.administrativeArea = component.longText || '';
                } else if (types.includes('postal_code')) {
                    addressData.postalCode = component.longText || '';
                } else if (types.includes('subpremise')) {
                    addressData.subpremise = component.longText || '';
                }
            });

            console.log("📍 Parsed address data:", addressData);
            fillAddressFields(addressData);
        }

        function extractAddressFromFormatted(formattedAddress) {
            if (!formattedAddress) return;
            
            console.log("📍 Extracting from formatted address:", formattedAddress);
            
            const parts = formattedAddress.split(',').map(part => part.trim());
            const addressData = {
                locality: '',
                administrativeArea: '',
                postalCode: ''
            };
            
            console.log("📍 Formatted address parts:", parts);
            
            if (parts.length >= 3) {
                // Typical format: "123 Main St, Johannesburg, Gauteng, 2001, South Africa"
                addressData.locality = parts[1] || '';
                addressData.administrativeArea = parts[2] || '';
                
                // Look for postal code in any part
                for (let part of parts) {
                    const postalMatch = part.match(/\b\d{4}\b/);
                    if (postalMatch) {
                        addressData.postalCode = postalMatch[0];
                        break;
                    }
                }
            } else if (parts.length === 2) {
                // Shorter format: "Johannesburg, Gauteng"
                addressData.locality = parts[0] || '';
                addressData.administrativeArea = parts[1] || '';
            }
            
            console.log("📍 Extracted address data:", addressData);
            fillAddressFields(addressData);
        }

        function fillAddressFields(addressData) {
            const addr2 = document.getElementById('addr2');
            const city = document.getElementById('city');
            const province = document.getElementById('province');
            const postal = document.getElementById('postal');

            console.log("📍 Filling address fields with:", addressData);

            // Set Address Line 2 if there's a subpremise
            if (addressData.subpremise && addr2) {
                addr2.value = `Unit ${addressData.subpremise}`;
            }

            // Set city - editable but with visual indication
            if (addressData.locality && city) {
                city.value = addressData.locality;
                city.style.backgroundColor = '#f9f9f9';
                validateField('city', addressData.locality);
            }

            // Set province - editable but with visual indication
            if (addressData.administrativeArea && province) {
                const provinceMap = {
                    'gauteng': 'Gauteng',
                    'western cape': 'Western Cape', 
                    'kwaZulu-natal': 'KwaZulu-Natal',
                    'kzn': 'KwaZulu-Natal',
                    'eastern cape': 'Eastern Cape',
                    'north west': 'North West',
                    'mpumalanga': 'Mpumalanga',
                    'northern cape': 'Northern Cape',
                    'free state': 'Free State',
                    'limpopo': 'Limpopo'
                };
                
                const provinceLower = addressData.administrativeArea.toLowerCase();
                const mappedProvince = provinceMap[provinceLower] || addressData.administrativeArea;
                province.value = mappedProvince;
                province.style.backgroundColor = '#f9f9f9';
                validateField('province', mappedProvince);
            }

            // Set postal code - editable but with visual indication
            if (addressData.postalCode && postal) {
                postal.value = addressData.postalCode;
                postal.style.backgroundColor = '#f9f9f9';
                validateField('postal', addressData.postalCode);
            }

            console.log("✅ Address fields filled successfully");
        }

        function clearAddressFields() {
            const fields = ['addr2', 'city', 'province', 'postal'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.value = '';
                    // Remove background color when clearing
                    field.style.backgroundColor = '';
                }
            });
        }

        function clearAddressError() {
            const errorElement = document.getElementById('addressError');
            const addressInput = document.getElementById('address');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
            if (addressInput) {
                addressInput.classList.remove('error');
            }
        }

        function showGoogleMapsError(message) {
            const addressHint = document.querySelector('.address-hint');
            if (addressHint) {
                addressHint.innerHTML = message || 'Address suggestions unavailable. Please enter your address manually.';
                addressHint.style.color = '#e74c3c';
            }
            
            const addressInput = document.getElementById('address');
            if (addressInput) {
                addressInput.placeholder = 'Enter your full address manually...';
            }
        }

        function setupManualAddressInput() {
            console.log("🔄 Setting up manual address input as fallback");
            
            const addressInput = document.getElementById("address");
            if (!addressInput) return;

            // Make sure the original input is visible
            addressInput.style.display = 'block';

            addressInput.addEventListener('blur', function() {
                if (this.value.trim().length > 5) {
                    extractAddressFromFormatted(this.value);
                    validateField('address', this.value);
                }
            });

            showGoogleMapsError("Using manual address input. Please enter complete address.");
        }

        // Make function globally available
        window.initAutocomplete = initAutocomplete;

        // Fallback if API fails to load
        setTimeout(() => {
            if (typeof google === 'undefined' || !google.maps) {
                console.warn("Google Maps failed to load, using manual input");
                setupManualAddressInput();
            }
        }, 5000);

    </script>
</body>
</html>
