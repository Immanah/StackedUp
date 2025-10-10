<?php
session_start();
include 'db.php'; // your database connection

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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
        
        .nav-wrap {
            background: var(--nav-bg);
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 120;
            box-shadow: 0 6px 20px rgba(2, 2, 2, 0.12)
        }
        
        .nav {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            gap: 18px;
            justify-content: space-between
        }
        
        .logo {
            display: flex;
            gap: 12px;
            align-items: center;
            font-weight: 800;
            letter-spacing: 1px;
            font-size: 20px
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
            font-size: 16px
        }
        
        .search {
            flex: 1;
            max-width: 640px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0 12px
        }
        
        .search input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 999px 0 0 999px;
            border: 0;
            outline: 0;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.06);
            color: #fff
        }
        
        .search button {
            padding: 10px 12px;
            border-radius: 0 999px 999px 0;
            border: 0;
            background: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center
        }
        
        .icons {
            display: flex;
            gap: 14px;
            align-items: center
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
            cursor: pointer
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #222, #111);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700
        }
        
        .profile-area {
            position: relative
        }
        
        .profile-btn {
            display: flex;
            gap: 10px;
            align-items: center;
            background: transparent;
            border: 0;
            color: #fff;
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 8px
        }
        
        .dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            background: #111;
            border-radius: 10px;
            padding: 8px 6px;
            min-width: 180px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: none
        }
        
        .dropdown a {
            display: block;
            color: #fff;
            padding: 10px 12px;
            text-decoration: none;
            font-size: 14px;
            border-radius: 8px
        }
        
        .dropdown a:hover {
            background: #1a1a1a
        }
        
        .mobile-toggle {
            display: none;
            background: transparent;
            border: 0;
            color: #fff
        }
        
        .mobile-menu {
            display: none;
            padding: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.03)
        }
        
        @media (max-width:880px) {
            .search {
                display: none
            }
            .mobile-toggle {
                display: inline-flex
            }
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
    </style>
</head>

<body>
    <!-- NAVBAR -->
    <header class="nav-wrap" role="banner">
        <div class="nav" role="navigation" aria-label="Main navigation">
            <div style="display:flex;align-items:center;gap:12px">
                <div class="logo" id="brandLink" style="cursor:pointer">
                    <div class="logo-badge" aria-hidden="true">OZ</div>
                    <div>OZYDE</div>
                </div>
            </div>

            <div class="search" role="search" aria-label="Site search">
                <input id="searchInput" type="search" placeholder="Search dresses, designers, collection..." aria-label="Search">
                <button id="searchBtn" aria-label="Search">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none"><path d="M21 21l-4.35-4.35" stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="11" cy="11" r="6" stroke="#111" stroke-width="2"/></svg>
        </button>
            </div>

            <div class="icons" role="group" aria-label="User actions">
                <button class="icon-only" title="Wishlist" aria-label="Wishlist" id="topWishlistBtn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20.8 7.2a5 5 0 0 0-7.07 0L12 8.94l-1.73-1.72a5 5 0 1 0-7.07 7.07L12 21.5l8.8-8.8a5 5 0 0 0 0-7.5z" stroke="white" stroke-width="1.2" fill="none"/></svg>
        </button>

                <button class="icon-only" title="Cart" aria-label="Cart" id="topCartBtn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3h2l1 7h13" stroke="white" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1.5" fill="white"/><circle cx="18" cy="20" r="1.5" fill="white"/></svg>
        </button>

                <div class="profile-area" id="profileArea">
                    <button class="profile-btn" id="profileBtn" aria-haspopup="true" aria-expanded="false" aria-label="Open account menu">
            <div class="profile-avatar" aria-hidden="true">A</div>
            <div style="text-align:left">
              <div style="font-weight:700; font-size:14px">A. Nomvula</div>
              <div style="font-size:12px; color:#bdbdbd;">Member</div>
            </div>
          </button>

                    <div class="dropdown" id="profileDropdown" role="menu" aria-hidden="true">
                        <a href="customerdashboard.html" role="menuitem">My Account</a>
                        <a href="orders.html" role="menuitem">My Orders</a>
                        <a href="wishlist.html" role="menuitem">Wishlist</a>
                        <a href="#" role="menuitem" id="signOutLink">Sign out</a>
                    </div>
                </div>

                <button class="mobile-toggle" id="mobileToggle" aria-expanded="false" aria-label="Open menu">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 7h18M3 12h18M3 17h18" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg>
        </button>
            </div>
        </div>

        <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
            <div style="margin-bottom:10px">
                <input id="mobileSearch" type="search" placeholder="Search dresses..." style="width:100%; padding:10px 12px; border-radius:8px; border:0; background:rgba(255,255,255,0.04); color:#fff">
            </div>
            <div style="display:flex;gap:10px">
                <button class="icon-only" aria-label="Wishlist (mobile)" id="mWishlist"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20.8 7.2a5 5 0 0 0-7.07 0L12 8.94l-1.73-1.72a5 5 0 1 0-7.07 7.07L12 21.5l8.8-8.8a5 5 0 0 0 0-7.5z" stroke="white" stroke-width="1.2" fill="none"/></svg></button>
                <button class="icon-only" aria-label="Cart (mobile)" id="mCart"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3h2l1 7h13" stroke="white" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1.5" fill="white"/><circle cx="18" cy="20" r="1.5" fill="white"/></svg></button>
                <button class="profile-btn" style="background:transparent;border:0;color:#fff" id="mAccountBtn">Account</button>
            </div>
        </div>
    </header>

<main>
    <div class="topline">
        <button id="backBtn" class="back-btn" aria-label="Go back" onclick="window.history.back()">← Back</button>
        <div>
            <h1>Cart</h1>
            <div class="muted">Review your items, remove from cart or proceed to checkout.</div>
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
        $subtotal = 0;
        foreach ($cart_items as $item) {
            $subtotal += $item['price']; // Just the dress price, no days multiplication
        }
        $deposit = $subtotal * 0.20;
        $delivery = 150; // Fixed delivery fee
        $total = $subtotal + $deposit + $delivery;
        ?>
        <div>
            <div class="summary-row">
                <div>Subtotal</div>
                <div>R<?= number_format($subtotal, 2) ?></div>
            </div>
            <div class="summary-row">
                <div>Deposit (20%)</div>
                <div>R<?= number_format($deposit, 2) ?></div>
            </div>
            <div class="summary-row">
                <div>Delivery Fee</div>
                <div>R<?= number_format($delivery, 2) ?></div>
            </div>
            <div class="total-row">
                <div>Total Amount</div>
                <div>R<?= number_format($total, 2) ?></div>
            </div>
        </div>
        <div class="summary-actions">
            <button class="btn primary" id="checkoutBtn" onclick="proceedToCheckout()">Proceed to Checkout</button>
            <button class="btn ghost" id="continueBtn" onclick="window.location.href='catalog.php'">Continue Shopping</button>
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
    // Back button functionality
    document.getElementById('backBtn').addEventListener('click', function() {
        window.history.back();
    });

    // Mobile menu toggle
    document.getElementById('mobileToggle').addEventListener('click', function() {
        const menu = document.getElementById('mobileMenu');
        const isHidden = menu.style.display === 'none' || menu.style.display === '';
        menu.style.display = isHidden ? 'block' : 'none';
        this.setAttribute('aria-expanded', isHidden);
        menu.setAttribute('aria-hidden', !isHidden);
    });

    // Profile dropdown
    document.getElementById('profileBtn').addEventListener('click', function() {
        const dropdown = document.getElementById('profileDropdown');
        const isHidden = dropdown.style.display === 'none' || dropdown.style.display === '';
        dropdown.style.display = isHidden ? 'block' : 'none';
        this.setAttribute('aria-expanded', isHidden);
        dropdown.setAttribute('aria-hidden', !isHidden);
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const profileArea = document.getElementById('profileArea');
        const dropdown = document.getElementById('profileDropdown');
        
        if (!profileArea.contains(event.target)) {
            dropdown.style.display = 'none';
            document.getElementById('profileBtn').setAttribute('aria-expanded', 'false');
            dropdown.setAttribute('aria-hidden', 'true');
        }
    });
</script>
<script>
function proceedToCheckout() {
    const cartItems = [];

    // Adjust these selectors to match your cart page structure
    document.querySelectorAll('.cart-item').forEach(item => {
        const title = item.querySelector('.item-title')?.textContent.trim() || 'Item';
        const priceText = item.querySelector('.item-price')?.textContent.trim() || '0';
        const daysText = item.querySelector('.item-days')?.textContent.trim() || '1';

        // Clean up and convert text
        const price = parseFloat(priceText.replace(/[^\d.]/g, '')) || 0;
        const days = parseInt(daysText.replace(/\D/g, '')) || 1;

        cartItems.push({ title, price, days });
    });

    // Store items for checkout
    sessionStorage.setItem('cart.php', JSON.stringify(cartItems));

    // Go to checkout page
    window.location.href = 'checkout.php';
}
</script>

</body>
</html>