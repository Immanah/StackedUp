<?php
session_start();
include 'db.php'; // your database connection

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle move to wishlist action
if (isset($_POST['move_to_wishlist'])) {
    $cart_id = $_POST['cart_id'];
    $product_id = $_POST['product_id'];
    
    // Check if item already exists in wishlist
    $check_wishlist_sql = "SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?";
    $check_stmt = $conn->prepare($check_wishlist_sql);
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        // Insert into wishlist
        $wishlist_sql = "INSERT INTO wishlist (user_id, product_id, added_date) VALUES (?, ?, NOW())";
        $wishlist_stmt = $conn->prepare($wishlist_sql);
        $wishlist_stmt->bind_param("ii", $user_id, $product_id);
        $wishlist_stmt->execute();
        $wishlist_stmt->close();
    }
    
    // Remove from cart
    $remove_sql = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
    $remove_stmt = $conn->prepare($remove_sql);
    $remove_stmt->bind_param("ii", $cart_id, $user_id);
    $remove_stmt->execute();
    $remove_stmt->close();
    
    $check_stmt->close();
    
    // Refresh the page
    header("Location: cart.php");
    exit;
}

// Fetch cart items for user
$sql = "SELECT 
            c.cart_id, 
            c.product_id, 
            p.name AS product_name, 
            p.image, 
            p.price, 
            c.size, 
            c.start_date, 
            c.end_date,
            c.quantity
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        WHERE c.user_id = $user_id";

$result = $conn->query($sql);
$cart_items = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Calculate days rented - fix for accurate day calculation
        $start = new DateTime($row['start_date']);
        $end = new DateTime($row['end_date']);
        $days = $start->diff($end)->days;
        
        // Ensure minimum 1 day rental
        $days = max(1, $days);

        $cart_items[] = [
            'cart_id' => $row['cart_id'],
            'product_id' => $row['product_id'],
            'name' => $row['product_name'],
            'image' => $row['image'],
            'price' => (float)$row['price'],
            'size' => $row['size'],
            'quantity' => (int)$row['quantity'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'days' => $days,
        ];
    }
}
$conn->close();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Cart — OZYDE</title>
    <style>
        /* Navbar Styles Only */
        .nav-wrap {
            background: #0b0b0b;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 120;
            box-shadow: 0 6px 20px rgba(2, 2, 2, 0.12);
        }
        
        .nav {
            max-width: 1200px;
            margin: 0 auto;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            gap: 18px;
            justify-content: space-between;
        }
        
        .logo {
            display: flex;
            gap: 12px;
            align-items: center;
            font-weight: 800;
            letter-spacing: 1px;
            font-size: 20px;
            cursor: pointer;
        }
        
        .logo-badge {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, #fff2, #fff6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111;
            font-weight: 900;
            font-size: 16px;
        }
        
        nav ul {
            margin: 0;
            padding: 0;
            display: flex;
            gap: 18px;
            list-style: none;
            align-items: center;
        }
        
        nav a {
            font-size: 14px;
            color: #fff;
            display: block;
            padding: 8px 6px;
            transition: color 0.2s ease;
            text-decoration: none; /* Add this line to remove underline */
        }
        
        nav a.active {
            color: #fff;
            font-weight: 600;
            border-bottom: 2px solid #fff;
        }
        
        nav a:hover {
            color: #ddd;
        }
        
        .icons {
            display: flex;
            gap: 14px;
            align-items: center;
        }
        
        .icon-only {
            display: inline-flex;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 0;
            color: #fff;
            cursor: pointer;
            transition: background 0.2s ease;
            position: relative;
        }
        
        .icon-only:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Profile Dropdown */
        .profile-dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: #0b0b0b;
            border-radius: 8px;
            padding: 8px 0;
            min-width: 180px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .profile-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-menu a {
            display: block;
            padding: 10px 16px;
            color: #fff;
            font-size: 14px;
            transition: background 0.2s ease;
            text-decoration: none;
        }
        
        .dropdown-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .dropdown-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
            margin: 6px 0;
        }
        
        /* Search Bar Styles */
        .search {
            flex: 1;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0 12px;
        }
        
        .search input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 999px 0 0 999px;
            border: 0;
            outline: 0;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }
        
        .search input::placeholder {
            color: #aaa;
        }
        
        .search button {
            padding: 10px 12px;
            border-radius: 0 999px 999px 0;
            border: 0;
            background: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Responsive Navbar */
        @media (max-width: 880px) {
            .nav {
                flex-wrap: wrap;
            }
            nav ul {
                order: 2;
                width: 100%;
                justify-content: center;
                margin-top: 15px;
            }
            .search {
                display: none;
            }
        }

        /* Rest of your existing cart styles */
        :root {
            --bg: #fff;
            --nav-bg: #0b0b0b;
            --muted: #9a9a9a;
            --accent: #111;
            --max-width: 1200px;
        }
        
        * {
            box-sizing: border-box
        }
        
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            color: #111;
            background: var(--bg)
        }
        
        main {
            max-width: var(--max-width);
            margin: 28px auto;
            padding: 0 18px 60px
        }
        
        .topline {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px
        }
        
        .back-btn {
            background: #fff;
            border: 0;
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700
        }
        
        h1 {
            margin: 0
        }
        
        .muted {
            color: var(--muted)
        }
        
        .layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
            margin-top: 18px
        }
        
        @media (min-width: 980px) {
            .layout {
                grid-template-columns: 1fr 360px;
            }
        }
        
        .cart-item {
            background: #fff;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid #eee;
            display: flex;
            gap: 18px;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .thumb {
            width: 120px;
            height: 150px;
            border-radius: 8px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            flex-shrink: 0;
        }
        
        .item-details {
            flex: 1;
        }
        
        .title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .meta {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 8px;
        }
        
        .rental-period {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 12px;
            display: inline-block;
        }
        
        .item-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }
        
        nav a {
    font-size: 14px;
    color: #fff;
    display: block;
    padding: 8px 6px;
    transition: color 0.2s ease;
    text-decoration: none; /* Add this line to remove underline */
}
        .summary {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #eee;
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .summary-title {
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #eee;
            font-size: 16px;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .btn.primary {
            background: var(--accent);
            color: #fff;
        }
        
        .btn.primary:hover {
            background: #333;
        }
        
        .btn.ghost {
            background: #fff;
            border: 1px solid #e6e6e6;
        }
        
        .btn.ghost:hover {
            background: #f8f9fa;
        }
        
        .btn.black {
            background: var(--accent);
            color: #fff;
            border: 1px solid var(--accent);
        }
        
        .btn.black:hover {
            background: #333;
            border-color: #333;
        }
        
        .btn.wishlist {
            background: #fff;
            color: #1f1d1dff;
            border: 1px solid #22201fff;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
        }
        
        .btn.wishlist:hover {
            background: #392b2aff;
            color: #fff;
        }
        
        .summary-actions {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .empty-cart {
            background: #fff;
            padding: 60px 30px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #eee;
        }
        
        .empty-cart h2 {
            margin: 0 0 12px 0;
            color: var(--accent);
        }
        
        .empty-cart p {
            color: var(--muted);
            margin-bottom: 24px;
        }
        
        .cart-layout {
            display: flex;
            gap: 40px;
            align-items: flex-start;
        }

        .cart-items {
            flex: 1;
        }

        .summary {
            width: 380px;
            position: sticky;
            top: 20px;
        }

        .price-breakdown {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 24px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn.confirm {
            background: #e74c3c;
            color: white;
        }
        
        .btn.cancel {
            background: #95a5a6;
            color: white;
        }
    </style>
</head>

<body>
    <!-- Move to Wishlist Confirmation Modal -->
    <div id="wishlistModal" class="modal">
        <div class="modal-content">
            <h3 style="margin: 0 0 12px 0;">Move to Wishlist</h3>
            <p style="color: var(--muted); margin-bottom: 20px;">Are you sure you want to move this item from your cart to your wishlist?</p>
            <div class="modal-actions">
                <form id="wishlistForm" method="post" style="display: inline;">
                    <input type="hidden" name="cart_id" id="modalCartId">
                    <input type="hidden" name="product_id" id="modalProductId">
                    <input type="hidden" name="move_to_wishlist" value="1">
                    <button type="submit" class="btn confirm">Yes, Move to Wishlist</button>
                </form>
                <button class="btn cancel" onclick="closeWishlistModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- ===== Navigation Bar ===== -->
    <header class="nav-wrap" role="banner">
        <div class="nav" role="navigation" aria-label="Main navigation">
            <div class="logo" id="brandLink">
                <div class="logo-badge" aria-hidden="true">✦</div>
                <div>Ozyde</div>
            </div>

            <!-- Search Bar -->
            <div class="search" role="search" aria-label="Site search">
                <input id="searchInput" type="search" placeholder="Search dresses, designers, collection..." aria-label="Search">
                <button id="searchBtn" aria-label="Search">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M21 21l-4.35-4.35" stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="11" cy="11" r="6" stroke="#111" stroke-width="2"/>
                    </svg>
                </button>
            </div>

            <nav aria-label="Main navigation">
                <ul id="main-nav">
                    <li><a href="finalhomepage.php">Home</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="blog.html">Blog</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                    <li><a href="custommade_loggedin.php">Custom Made</a></li>
                    <li><a href="catalog.php">Browse</a></li>
                </ul>
            </nav>

            <div class="icons" role="group" aria-label="User actions">
                <a href="wishlist.php" class="icon-only" title="Wishlist" aria-label="Wishlist">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="white" stroke-width="1.2" fill="none"/>
                    </svg>
                </a>

                <a href="cart.php" class="icon-only" title="Shopping Cart" aria-label="Shopping Cart">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <circle cx="9" cy="21" r="1" stroke="white" stroke-width="1.2" fill="none"/>
                        <circle cx="20" cy="21" r="1" stroke="white" stroke-width="1.2" fill="none"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" stroke="white" stroke-width="1.2" fill="none"/>
                    </svg>
                </a>

                <div class="profile-dropdown">
                    <button class="icon-only" title="My Account" aria-label="My Account">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="white" stroke-width="1.2" fill="none"/>
                            <circle cx="12" cy="7" r="4" stroke="white" stroke-width="1.2" fill="none"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu">
                        <a href="customerdashboard.php">Customer Dashboard</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" id="logoutLink">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

<main>
    <div class="topline">
        <button id="backBtn" class="back-btn" aria-label="Go back" onclick="window.history.back()">← Back</button>
        <div>
            <h1>Cart</h1>
            <div class="muted">Review your items, remove from cart or proceed to checkout</div>
        </div>
    </div>

    <div class="layout">
    <div id="itemsCol">
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <h2>Your cart is empty</h2>
                <p>Browse our collection and add items to your cart</p>
                <button class="btn primary" onclick="window.location.href='catalog.php'">Continue Shopping</button>
            </div>
        <?php else: ?>
            <?php foreach ($cart_items as $it): 
                // Calculate total price for this item
                $daily_price = $it['price'];
                $total_price = $daily_price * $it['days'];
                
                // Format dates for display
                $start_date = date('M j, Y', strtotime($it['start_date']));
                $end_date = date('M j, Y', strtotime($it['end_date']));
            ?>
                <div class="cart-item">
                    <div class="thumb">
                        <img src="<?= htmlspecialchars($it['image']) ?>" 
                             alt="<?= htmlspecialchars($it['name']) ?>" 
                             style="width:100%;height:100%;object-fit:cover;border-radius:8px">
                    </div>
                    <div class="item-details">
                        <div class="title"><?= htmlspecialchars($it['name']) ?></div>
                        <div class="meta">
                            Size: <?= htmlspecialchars($it['size']) ?>
                        </div>
                        <div class="rental-period">
                            Rental Period <?= date('j M Y', strtotime($start_date)) ?> to <?= date('j M Y', strtotime($end_date)) ?>
                        </div>
                        <div class="item-actions">
                            <form method="post" action="remove_cart.php" style="display:inline;">
                                <input type="hidden" name="cart_id" value="<?= $it['cart_id'] ?>">
                                <button class="btn black" type="submit">Remove</button>
                            </form>
                            <button class="btn wishlist" onclick="openWishlistModal('<?= $it['cart_id'] ?>', '<?= $it['product_id'] ?>')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="M20.8 7.2a5 5 0 0 0-7.07 0L12 8.94l-1.73-1.72a5 5 0 1 0-7.07 7.07L12 21.5l8.8-8.8a5 5 0 0 0 0-7.5z" stroke="currentColor" stroke-width="1.2" fill="none"/>
                                </svg>
                                Move to Wishlist
                            </button>
                        </div>
                    </div>
                    <div style="font-weight:800; font-size:16px; text-align: right;">
                        R<?= number_format($daily_price, 2) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($cart_items)): ?>
    <aside class="summary">
        <div class="summary-title">Order Summary</div>
        <?php
        // FIXED: Calculate totals - no multiplication by days
       // Calculate PHP totals - MATCHING YOUR CART.PHP LOGIC
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'];
}
$vat = $subtotal * 0.15;
$deposit = 800; // Fixed deposit as in your cart
$deliveryFee = 0;
$returnFee = 0;
$total = $subtotal + $deposit; // No VAT in total as per your cart.php
        ?>
        <div>
            <div class="summary-row">
                <div>Subtotal</div>
                <div>R<?= number_format($subtotal, 2) ?></div>
            </div>
            <div class="summary-row">
                <div>Deposit</div>
                <div>R<?= number_format($deposit, 2) ?></div>
            </div>
            
            <div class="total-row">
                <div>Total Amount</div>
                <div>R<?= number_format($total, 2) ?></div>
            </div>
        </div>
        <div class="summary-actions">
    <a href="checkout.php" class="btn primary" style="text-decoration: none; display: block; text-align: center;">
        Proceed to Checkout
    </a>
    <button class="btn ghost" onclick="window.location.href='catalog.php'">Continue Shopping</button>
</div>
        <div class="muted" style="margin-top:16px;font-size:13px; text-align: center;">
            Deposit refunded after inspection if items returned on time and undamaged.
        </div>
    </aside>
    <?php endif; ?>
</div>
    </div>
</main>

<script>
    // Wishlist modal functions
    function openWishlistModal(cartId, productId) {
        document.getElementById('modalCartId').value = cartId;
        document.getElementById('modalProductId').value = productId;
        document.getElementById('wishlistModal').style.display = 'block';
    }

    function closeWishlistModal() {
        document.getElementById('wishlistModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('wishlistModal');
        if (event.target == modal) {
            closeWishlistModal();
        }
    }

    // Back button functionality
    document.getElementById('backBtn').addEventListener('click', function() {
        window.history.back();
    });
</script>

</body>
</html>