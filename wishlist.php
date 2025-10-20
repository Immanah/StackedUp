<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: register.html");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle wishlist removal via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if ($product_id > 0) {
        $delete_sql = "DELETE FROM wishlist WHERE user_id = ? AND product_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $user_id, $product_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Removed from wishlist']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error removing from wishlist']);
        }
        exit;
    }
}

// Fetch wishlist items from database
$sql = "SELECT w.wishlist_id, p.product_id, p.name, p.image, p.price, p.description, p.stock 
        FROM wishlist w 
        JOIN products p ON w.product_id = p.product_id 
        WHERE w.user_id = ? 
        ORDER BY w.added_at DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wishlist_items = $result->fetch_all(MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Wishlist — Ozyde</title>
    <style>
        /* Ozyde Boutique consistent styling - matching catalog */
        
         :root {
            --bg: #fff;
            --text: #222;
            --muted: #7a7a7a;
            --accent: #111;
            --max-width: 1200px;
            --chip-bg: #f3f3f3;
            --chip-border: #e6e6e6;
            --primary: #111;
            --success: #2fa46b;
            --warning: #f59e0b;
            --danger: #ef4444;
            --airbnb-pink: #FF5A5F;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            font-family: "Helvetica Neue", Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
        }
        
        a {
            color: inherit;
            text-decoration: none;
        }
        
        .container {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 20px;
        }
        /* Header Styles - matching catalog */
        
        .nav-wrap {
            background: #0b0b0b;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 120;
            box-shadow: 0 6px 20px rgba(2, 2, 2, 0.12);
        }
        
        .nav {
            max-width: var(--max-width);
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
        /* Main Content Styles - SIGNIFICANTLY IMPROVED SPACING */
        
        main {
            padding: 80px 0 60px; /* DRAMATICALLY increased top padding */
        }
        
        .content-header {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 24px;
            padding-top: 20px; /* Added more padding at top */
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 8px;
            background: #111;
            color: #fff;
            text-decoration: none;
            transition: background 0.2s ease;
            white-space: nowrap;
            flex-shrink: 0;
            font-size: 15px;
        }
        
        .back-btn:hover {
            background: #333;
        }
        
        .page-title {
            margin: 0;
            font-size: 34px;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: -0.5px;
        }
        
        .lead {
            color: var(--muted);
            margin: 0 0 48px 0;
            font-size: 17px;
            line-height: 1.6;
            max-width: 600px;
        }
        
        /* Wishlist Grid */
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 0;
        }
        
        .wishlist-card {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .wishlist-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .product-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .wishlist-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .remove-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .remove-btn:hover {
            background: white;
            transform: scale(1.1);
        }
        
        .availability-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .availability-badge.available {
            background: #e8f5e8;
            color: var(--success);
        }
        
        .availability-badge.rented {
            background: #f8d7da;
            color: var(--danger);
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-title {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--accent);
        }
        
        .product-designer {
            margin: 0 0 12px 0;
            color: var(--muted);
            font-size: 14px;
        }
        
        .product-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--muted);
        }
        
        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 16px;
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 14px;
            flex: 1;
        }
        
        .btn.primary {
            background: var(--accent);
            color: #fff;
        }
        
        .btn.secondary {
            background: #f5f5f5;
            color: var(--accent);
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            color: var(--muted);
        }
        
        .empty-state h3 {
            margin: 0 0 20px 0;
            font-size: 26px;
            font-weight: 700;
            color: var(--accent);
        }
        
        .empty-state p {
            margin: 0 0 36px 0;
            font-size: 17px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        
        /* Footer Styles - matching catalog */
        
        footer {
            border-top: 1px solid #eee;
            padding: 36px 0;
            margin-top: 28px;
            color: var(--muted);
            background: #fafafa;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 32px;
        }
        
        .footer-grid h4 {
            margin: 0 0 16px 0;
            color: var(--accent);
            font-weight: 600;
        }
        
        .footer-grid ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-grid li {
            margin-bottom: 8px;
        }
        
        .footer-grid a {
            color: var(--muted);
            transition: color 0.2s ease;
        }
        
        .footer-grid a:hover {
            color: var(--accent);
        }
        
        .socials {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        
        .socials a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: #f5f5f5;
            transition: all 0.2s ease;
        }
        
        .socials a:hover {
            background: var(--accent);
        }
        
        .socials a:hover svg path,
        .socials a:hover svg circle {
            stroke: #fff;
            fill: #fff;
        }
        /* Responsive Design */
        
        @media (max-width: 880px) {
            .search {
                order: 3;
                max-width: 100%;
                margin: 15px 0 0 0;
            }
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }
            .wishlist-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
        }
        
        @media (max-width: 640px) {
            .nav {
                flex-wrap: wrap;
            }
            nav ul {
                order: 2;
                width: 100%;
                justify-content: center;
                margin-top: 15px;
            }
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
                margin-bottom: 20px;
            }
            .footer-grid {
                grid-template-columns: 1fr;
            }
            .wishlist-grid {
                grid-template-columns: 1fr;
            }
            .card-actions {
                flex-direction: column;
            }
            .page-title {
                font-size: 28px;
            }
            main {
                padding: 60px 0 48px; /* Adjusted for mobile but still generous */
            }
            .lead {
                font-size: 16px;
                margin-bottom: 36px;
            }
        }
    </style>
</head>

<body>
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
                    <li><a href="catalog.php">Browse</a></li>
                    <li><a href="custommade_loggedin.php">Custom Made</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="blog.html">Blog</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                    
                    
                </ul>
            </nav>

            <div class="icons" role="group" aria-label="User actions">
                <!-- Help/Support Icon -->
                <a href="help.html" class="icon-only" title="Help & Support" aria-label="Help & Support">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="white" stroke-width="1.2" fill="none"/>
                        <circle cx="12" cy="18" r="0.5" fill="white"/>
                        <path d="M12 16v-2c1.5 0 2-1 2-2s-1-2-2-2-2 1-2 2" stroke="white" stroke-width="1.2" fill="none"/>
                    </svg>
                </a>

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
                        <a href="wishlist.php">My Wishlist</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" id="logoutLink">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="content-header">
            <a class="back-btn" href="catalog.php">← Back to Catalog</a>
            <h2 class="page-title">My Wishlist</h2>
        </div>

        <p class="lead">Items you've saved for later. Move them to your cart when you're ready to rent.</p>

        <div id="wishlistContent">
            <div class="wishlist-grid" id="wishlistGrid">
                <?php if (empty($wishlist_items)): ?>
                    <div class="empty-state" id="emptyWishlist">
                        <h3>Your Wishlist is Empty</h3>
                        <p>You haven't saved any items to your wishlist yet.</p>
                        <a href="catalog.php" class="btn primary">Browse Dresses</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($wishlist_items as $item): 
                        $stock = (int)$item['stock'];
                        $available_class = ($stock > 0) ? 'available' : 'rented';
                        $available_text = ($stock > 0) ? 'Available' : 'Rented';
                    ?>
                    <div class="wishlist-card" data-product-id="<?= $item['product_id'] ?>">
                        <div class="product-image">
                            <img src="<?= htmlspecialchars($item['image']) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 onerror="this.src='gallery/placeholder.png'">
                            <button class="remove-btn" data-product-id="<?= $item['product_id'] ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
                            <span class="availability-badge <?= $available_class ?>"><?= $available_text ?></span>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?= htmlspecialchars($item['name']) ?></h3>
                            <div class="product-details">
                                <span class="rental-period">3-day rental</span>
                            </div>
                            <div class="product-price">R<?= number_format($item['price'], 2) ?></div>
                            <div class="card-actions">
                                <a href="productdetail.php?product_id=<?= $item['product_id'] ?>" class="btn secondary">View Details</a>
                                <?php if ($stock > 0): ?>
                                    <a href="booking.php?product_id=<?= $item['product_id'] ?>" class="btn primary">Book Now</a>
                                <?php else: ?>
                                    <button class="btn primary" disabled>Not Available</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer - matching catalog -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h4>Ozyde</h4>
                    <p>Premium dress rentals for your special occasions. Quality, style, and affordability combined.</p>
                    <div>Address:<br>5 Liebenberg Rd, Noordwyk, Midrand 1687</div>
                    <div class="socials" aria-label="Social media">
                        <a href="https://www.instagram.com/ozyde_?igsh=NWM0aTd4ZGFmeHVr" target="_blank" rel="noopener" aria-label="Instagram">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <rect x="3" y="3" width="18" height="18" rx="5" stroke="#333" stroke-width="1.2" fill="none"/>
                                <circle cx="12" cy="12" r="3.2" stroke="#333" stroke-width="1.2" fill="none"/>
                                <circle cx="17.5" cy="6.5" r="0.6" fill="#333"/>
                            </svg>
                        </a>
                        <a href="https://www.tiktok.com/@ozyde_designs?_t=ZS-8zlyfPi8HHJ&_r=1" target="_blank" rel="noopener" aria-label="TikTok">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z" fill="#333"/>
                            </svg>
                        </a>
                        <a href="mailto:ozydedesigns@gmail.com" aria-label="Email">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <rect x="3" y="6" width="18" height="12" rx="2" stroke="#333" stroke-width="1.2" fill="none"/>
                                <path d="M4 7.5l8 6 8-6" stroke="#333" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <div>
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="howitworks.html">How It Works</a></li>
                        <li><a href="sizingguide.html">Size Guide</a></li>
                        <li><a href="#">Returns & Policy</a></li>
                        <li><a href="#">Delivery</a></li>
                        <li><a href="help.html">Help Center</a></li>
                    </ul>
                </div>

                <div>
                    <h4>Company</h4>
                    <ul>
                        <li><a href="about.html">About Us</a></li>
                        <li><a href="#">Press</a></li>
                        <li><a href="#">Terms</a></li>
                        <li><a href="#">Privacy</a></li>
                    </ul>
                </div>

                <div>
                    <h4>Support</h4>
                    <ul>
                        <li><a href="contact.html">Contact</a></li>
                        <li><a href="cleaning.html">Cleaning & Care Guide</a></li>
                        <li><a href="#">Partnerships</a></li>
                    </ul>
                </div>
            </div>

            <div style="margin-top:24px;text-align:center;padding-top:24px;border-top:1px solid #e6e6e6;color:var(--muted)">
                © 2024 Ozyde. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Logo click to go home
            document.getElementById('brandLink').addEventListener('click', function() {
                window.location.href = 'finalhomepage.html';
            });

            // Search functionality
            const searchBtn = document.getElementById('searchBtn');
            const searchInput = document.getElementById('searchInput');
            
            searchBtn.addEventListener('click', function() {
                const searchTerm = searchInput.value.trim();
                if (searchTerm) {
                    window.location.href = `catalog.php?search=${encodeURIComponent(searchTerm)}`;
                }
            });
            
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchBtn.click();
                }
            });

            // Remove from wishlist functionality
            document.querySelectorAll('.remove-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    const card = this.closest('.wishlist-card');
                    
                    if (confirm('Remove this item from your wishlist?')) {
                        // Send AJAX request to remove from wishlist
                        const formData = new FormData();
                        formData.append('action', 'remove');
                        formData.append('product_id', productId);

                        fetch('wishlist.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove the card with animation
                                card.style.opacity = '0.5';
                                setTimeout(() => {
                                    card.remove();
                                    checkEmptyWishlist();
                                }, 300);
                            } else {
                                alert(data.message || 'Error removing from wishlist');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error removing from wishlist');
                        });
                    }
                });
            });

            // Check if wishlist is empty
            function checkEmptyWishlist() {
                const wishlistGrid = document.getElementById('wishlistGrid');
                const emptyWishlist = document.getElementById('emptyWishlist');
                
                if (wishlistGrid.children.length === 0) {
                    // Create empty state if it doesn't exist
                    if (!emptyWishlist) {
                        const emptyDiv = document.createElement('div');
                        emptyDiv.id = 'emptyWishlist';
                        emptyDiv.className = 'empty-state';
                        emptyDiv.innerHTML = `
                            <h3>Your Wishlist is Empty</h3>
                            <p>You haven't saved any items to your wishlist yet.</p>
                            <a href="catalog.php" class="btn primary">Browse Dresses</a>
                        `;
                        wishlistGrid.appendChild(emptyDiv);
                    } else {
                        emptyWishlist.style.display = 'block';
                    }
                }
            }

            // Initialize empty state check
            checkEmptyWishlist();
        });
    </script>
</body>

</html>
