<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ozyde';
// -----------------------------------------------------

// Connect with mysqli
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "Database connection failed: (" . $mysqli->connect_errno . ") " . htmlspecialchars($mysqli->connect_error);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Check logged in
$logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Handle wishlist actions via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wishlist_action']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please login to manage wishlist']);
        exit;
    }
    
    if ($_POST['wishlist_action'] === 'add') {
        // Check if already in wishlist
        $check_sql = "SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?";
        $check_stmt = $mysqli->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $product_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Already in wishlist']);
        } else {
            $insert_sql = "INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)";
            $insert_stmt = $mysqli->prepare($insert_sql);
            $insert_stmt->bind_param("ii", $user_id, $product_id);
            
            if ($insert_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding to wishlist']);
            }
        }
    } 
    elseif ($_POST['wishlist_action'] === 'remove') {
        $delete_sql = "DELETE FROM wishlist WHERE user_id = ? AND product_id = ?";
        $delete_stmt = $mysqli->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $user_id, $product_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Removed from wishlist']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error removing from wishlist']);
        }
    }
    
    exit;
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$size_filter = isset($_GET['size']) ? $_GET['size'] : '';
$color_filter = isset($_GET['color']) ? $_GET['color'] : '';
$price_filter = isset($_GET['price']) ? $_GET['price'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8; // Default to 8 products

// Build query with filters - using your actual columns
$query = "SELECT product_id, category_id, name, description, size, color, price, image, stock, is_rental, created_at FROM products WHERE 1=1";
$params = [];
$types = "";

if (!empty($category_filter)) {
    $query .= " AND category_id = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if (!empty($size_filter)) {
    $query .= " AND size = ?";
    $params[] = $size_filter;
    $types .= "s";
}

if (!empty($color_filter)) {
    $query .= " AND color = ?";
    $params[] = $color_filter;
    $types .= "s";
}

if (!empty($price_filter)) {
    if ($price_filter === '0-50') {
        $query .= " AND price BETWEEN 0 AND 50";
    } elseif ($price_filter === '50-100') {
        $query .= " AND price BETWEEN 50 AND 100";
    } elseif ($price_filter === '100-200') {
        $query .= " AND price BETWEEN 100 AND 200";
    } elseif ($price_filter === '200+') {
        $query .= " AND price > 200";
    }
}

// Add sorting
if ($sort_by === 'price-low') {
    $query .= " ORDER BY price ASC";
} elseif ($sort_by === 'price-high') {
    $query .= " ORDER BY price DESC";
} elseif ($sort_by === 'popular') {
    $query .= " ORDER BY stock DESC"; // Using stock as a proxy for popularity
} else {
    $query .= " ORDER BY created_at DESC";
}

// Add limit
$query .= " LIMIT ?";
$params[] = $limit;
$types .= "i";

// Fetch products
$products = [];
if ($stmt = $mysqli->prepare($query)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
} else {
    // fallback: try direct query without filters
    $fallback_query = "SELECT product_id, category_id, name, description, size, color, price, image, stock, is_rental, created_at FROM products ORDER BY created_at DESC LIMIT $limit";
    if ($res = $mysqli->query($fallback_query)) {
        while ($row = $res->fetch_assoc()) $products[] = $row;
        $res->free();
    }
}

// Get wishlist status for each product if user is logged in
$wishlist_status = [];
if ($logged_in) {
    $user_id = $_SESSION['user_id'];
    $wishlist_query = "SELECT product_id FROM wishlist WHERE user_id = ?";
    $wishlist_stmt = $mysqli->prepare($wishlist_query);
    $wishlist_stmt->bind_param("i", $user_id);
    $wishlist_stmt->execute();
    $wishlist_result = $wishlist_stmt->get_result();
    
    while ($row = $wishlist_result->fetch_assoc()) {
        $wishlist_status[$row['product_id']] = true;
    }
    $wishlist_stmt->close();
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM products";
$total_products = 0;
if ($count_stmt = $mysqli->prepare($count_query)) {
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_row = $count_result->fetch_assoc();
    $total_products = $total_row['total'];
    $count_stmt->close();
}

// Get wishlist count for navigation
$wishlist_count = 0;
if ($logged_in) {
    $count_sql = "SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?";
    $count_stmt = $mysqli->prepare($count_sql);
    $count_stmt->bind_param("i", $_SESSION['user_id']);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $wishlist_row = $count_result->fetch_assoc();
    $wishlist_count = $wishlist_row['count'];
    $count_stmt->close();
}

// Helper to safe output
function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dress Catalog — OZYDE</title>
    <style>
        /* (CSS kept exactly as in your layout) */
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
        * { box-sizing: border-box; }
        body { margin:0; font-family:"Helvetica Neue", Arial, sans-serif; color:var(--text); background:var(--bg); -webkit-font-smoothing:antialiased; }
        a { color:inherit; text-decoration:none; }
        .container { max-width:var(--max-width); margin:0 auto; padding:0 20px; }
        .nav-wrap { background:#0b0b0b; color:#fff; position:sticky; top:0; z-index:120; box-shadow:0 6px 20px rgba(2,2,2,0.12); }
        .nav { max-width:var(--max-width); margin:0 auto; padding:10px 18px; display:flex; align-items:center; gap:18px; justify-content:space-between; }
        .logo { display:flex; gap:12px; align-items:center; font-weight:800; letter-spacing:1px; font-size:20px; cursor:pointer; }
        .logo-badge { width:40px; height:40px; border-radius:8px; background:linear-gradient(135deg,#fff2,#fff6); display:flex; align-items:center; justify-content:center; color:#111; font-weight:900; font-size:16px; }
        nav ul { margin:0; padding:0; display:flex; gap:18px; list-style:none; align-items:center; }
        nav a { font-size:14px; color:#fff; display:block; padding:8px 6px; transition:color 0.2s ease; }
        nav a:hover { color:#ddd; }
        .btn-signup { background:var(--accent); color:#fff; padding:8px 14px; border-radius:6px; font-weight:600; font-size:14px; transition:background 0.2s ease; }
        .btn-signup:hover { background:#333; }
        .icons { display:flex; gap:14px; align-items:center; }
        .icon-only { display:inline-flex; width:40px; height:40px; border-radius:8px; align-items:center; justify-content:center; background:transparent; border:0; color:#fff; cursor:pointer; transition:background 0.2s ease; position: relative; }
        .icon-only:hover { background:rgba(255,255,255,0.1); }

        .wishlist-count {
            background: var(--airbnb-pink);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -5px;
            right: -5px;
            font-weight: 600;
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

        .search { flex:1; max-width:400px; display:flex; align-items:center; gap:6px; margin:0 12px; }
        .search input { width:100%; padding:10px 12px; border-radius:999px 0 0 999px; border:0; outline:0; font-size:14px; background:rgba(255,255,255,0.06); color:#fff; }
        .search input::placeholder { color:#aaa; }
        .search button { padding:10px 12px; border-radius:0 999px 999px 0; border:0; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; }

        .hero { background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%); padding:60px 0; text-align:center; margin-bottom:40px; }
        .hero-content h1 { margin:0 0 16px 0; font-size:36px; font-weight:800; color:var(--accent); }
        .hero-content p { margin:0; color:var(--muted); font-size:18px; }

        .user-status { background:#f8f9fa; padding:12px 0; text-align:center; border-bottom:1px solid #e9ecef; margin-bottom:20px; }
        .user-status.logged-out { background:#fff3cd; color:#856404; }
        .user-status a { color:var(--accent); text-decoration:underline; font-weight:600; margin-left:5px; }

        .filters-section { margin-bottom:40px; }
        .filters { display:flex; gap:20px; flex-wrap:wrap; align-items:end; }
        .filter-group { display:flex; flex-direction:column; gap:8px; }
        .filter-group label { font-size:14px; font-weight:600; color:var(--muted); }
        .filter-group select { padding:10px 12px; border:1px solid #e6e6e6; border-radius:6px; font-size:14px; min-width:150px; background:#fff; cursor:pointer; }
        .filter-btn { padding:10px 20px; border:0; border-radius:6px; background:var(--accent); color:#fff; font-weight:600; cursor:pointer; transition:background 0.2s ease; }
        .filter-btn:hover { background:#333; }

        .products-section { margin-bottom:60px; }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; }
        .section-header h2 { margin:0; font-size:24px; font-weight:700; color:var(--accent); }
        .sort-options { display:flex; align-items:center; gap:10px; }
        .sort-options label { font-size:14px; color:var(--muted); }
        .sort-options select { padding:8px 12px; border:1px solid #e6e6e6; border-radius:6px; font-size:14px; background:#fff; cursor:pointer; }

        .view-toggle { display:flex; gap:12px; align-items:center; margin-left:12px; }
        .view-toggle .toggle { background:transparent; border:0; font-weight:700; padding:6px 8px; cursor:pointer; color:var(--muted); position:relative; font-size:14px; }
        .view-toggle .toggle.active { color:var(--accent); }
        .view-toggle .toggle.active::after { content:''; position:absolute; left:6px; right:6px; height:3px; background:var(--accent); bottom:-6px; border-radius:3px; }

        .products-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:30px; margin-bottom:40px; }

        .product-card { background:#fff; border:1px solid #f0f0f0; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05); transition:transform .3s ease, box-shadow .3s ease; position:relative; }
        .product-card:hover { transform:translateY(-5px); box-shadow:0 10px 30px rgba(0,0,0,0.1); }
        .product-image { position:relative; height:300px; overflow:hidden; }
        .product-image img { width:100%; height:100%; object-fit:cover; transition:transform .3s ease; }
        .product-card:hover .product-image img { transform:scale(1.05); }
        .wishlist-btn { position:absolute; top:12px; left:12px; width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.9); border:none; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:10; transition: all 0.3s ease; }
        .wishlist-btn:hover { transform: scale(1.1); }
        .wishlist-btn.active { background:var(--airbnb-pink); }
        .wishlist-btn.active svg { fill: white; stroke: white; }
    
        
        .product-info { padding:20px; }
        .product-title { margin:0 0 8px 0; font-size:18px; font-weight:600; color:var(--accent); }
        .product-designer { margin:0 0 12px 0; color:var(--muted); font-size:14px; }
        .product-details { display:flex; justify-content:space-between; margin-bottom:12px; font-size:13px; color:var(--muted); }
        .product-price { font-size:20px; font-weight:700; color:var(--accent); }
        
        

        .load-more-section { text-align:center; }
        .load-more-btn { padding:12px 30px; border:1px solid #e6e6e6; border-radius:6px; background:#fff; color:var(--accent); font-weight:600; cursor:pointer; }
        .load-more-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; justify-content:center; align-items:center; z-index:1000; opacity:0; visibility:hidden; transition:all .3s ease; }
        .modal-overlay.active { opacity:1; visibility:visible; }
        .modal-content { background:white; border-radius:12px; padding:30px; max-width:400px; width:90%; transform:scale(0.9); transition:transform .3s ease; }
        .modal-overlay.active .modal-content { transform:scale(1); }
        .modal-header { margin-bottom:20px; text-align:center; }
        .modal-header h3 { margin:0 0 10px 0; font-size:24px; font-weight:700; color:var(--accent); }
        .modal-actions { display:flex; flex-direction:column; gap:15px; margin-top:25px; }
        .modal-btn { padding:12px; border-radius:6px; font-weight:600; cursor:pointer; border:none; font-size:16px; }
        .modal-btn.primary { background:var(--accent); color:white; }
        .modal-btn.secondary { background:#f5f5f5; color:var(--accent); }
        footer { border-top:1px solid #eee; padding:36px 0; margin-top:28px; color:var(--muted); background:#fafafa; }
        .footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:32px; }
        @media (max-width:880px) {
            .search { order:3; max-width:100%; margin:15px 0 0 0; }
            .filters { flex-direction:column; align-items:stretch; }
            .filter-group { width:100%; }
            .section-header { flex-direction:column; align-items:flex-start; gap:15px; }
            .products-grid { grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:20px; }
            .footer-grid { grid-template-columns:1fr 1fr; gap:24px; }
        }
        @media (max-width:640px) {
            .nav { flex-wrap:wrap; }
            nav ul { order:2; width:100%; justify-content:center; margin-top:15px; }
            .products-grid { grid-template-columns:1fr; }
            .footer-grid { grid-template-columns:1fr; }
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
                    <li><a href="finalhomepage.html">Home</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="blog.html">Blog</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                    <li><a href="custommade.html">Custom Made</a></li>
                    <li><a href="catalog.php" class="active">Browse</a></li>
                </ul>
            </nav>

            <div class="icons" role="group" aria-label="User actions">
                <!-- Help Button - Added to navigation -->
                <a href="help.html" class="icon-only" title="Help" aria-label="Help">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="white" stroke-width="1.2" fill="none"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                        <line x1="12" y1="17" x2="12" y2="17" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                    </svg>
                </a>

                <a href="wishlist.html" class="icon-only" title="Wishlist" aria-label="Wishlist">
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
                        <a href="customerdashboard.html">Customer Dashboard</a>
                        <a href="my-account.html">My Account</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" id="logoutLink">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- User Status Banner -->
    <div class="user-status <?php echo $logged_in ? '' : 'logged-out'; ?>" id="userStatus">
        <div class="container">
            <?php if ($logged_in): ?>
                Welcome back — you are signed in. <a href="customerdashboard.html">Go to your dashboard</a>.
            <?php else: ?>
                You are browsing as a guest. <a href="register.html" id="loginLink">Sign in or register</a> to access wishlist and quick checkout.
            <?php endif; ?>
        </div>
    </div>

    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Designer Dress Rentals</h1>
                    <p>Luxury dresses for every special occasion</p>
                </div>
            </div>
        </section>

        <!-- Filters Section -->
        <section class="filters-section">
            <div class="container">
                <form method="GET" id="filterForm">
                    <div class="filters">
                        <div class="filter-group">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">All Categories</option>
                                <option value="1" <?php echo ($category_filter === '1') ? 'selected' : ''; ?>>Evening Gowns</option>
                                <option value="2" <?php echo ($category_filter === '2') ? 'selected' : ''; ?>>Cocktail Dresses</option>
                                <option value="3" <?php echo ($category_filter === '3') ? 'selected' : ''; ?>>Formal Wear</option>
                                <option value="4" <?php echo ($category_filter === '4') ? 'selected' : ''; ?>>Wedding Guest</option>
                                <option value="5" <?php echo ($category_filter === '5') ? 'selected' : ''; ?>>Prom Dresses</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="size">Size</label>
                            <select id="size" name="size">
                                <option value="">All Sizes</option>
                                <option value="XS" <?php echo ($size_filter === 'XS') ? 'selected' : ''; ?>>XS</option>
                                <option value="S" <?php echo ($size_filter === 'S') ? 'selected' : ''; ?>>S</option>
                                <option value="M" <?php echo ($size_filter === 'M') ? 'selected' : ''; ?>>M</option>
                                <option value="L" <?php echo ($size_filter === 'L') ? 'selected' : ''; ?>>L</option>
                                <option value="XL" <?php echo ($size_filter === 'XL') ? 'selected' : ''; ?>>XL</option>
                                <option value="XXL" <?php echo ($size_filter === 'XXL') ? 'selected' : ''; ?>>XXL</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="color">Color</label>
                            <select id="color" name="color">
                                <option value="">All Colors</option>
                                <option value="Black" <?php echo ($color_filter === 'Black') ? 'selected' : ''; ?>>Black</option>
                                <option value="Red" <?php echo ($color_filter === 'Red') ? 'selected' : ''; ?>>Red</option>
                                <option value="Blue" <?php echo ($color_filter === 'Blue') ? 'selected' : ''; ?>>Blue</option>
                                <option value="Green" <?php echo ($color_filter === 'Green') ? 'selected' : ''; ?>>Green</option>
                                <option value="Pink" <?php echo ($color_filter === 'Pink') ? 'selected' : ''; ?>>Pink</option>
                                <option value="White" <?php echo ($color_filter === 'White') ? 'selected' : ''; ?>>White</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="price">Price Range</label>
                            <select id="price" name="price">
                                <option value="">All Prices</option>
                                <option value="0-50" <?php echo ($price_filter === '0-50') ? 'selected' : ''; ?>>R0 - R50</option>
                                <option value="50-100" <?php echo ($price_filter === '50-100') ? 'selected' : ''; ?>>R50 - R100</option>
                                <option value="100-200" <?php echo ($price_filter === '100-200') ? 'selected' : ''; ?>>R100 - R200</option>
                                <option value="200+" <?php echo ($price_filter === '200+') ? 'selected' : ''; ?>>R200+</option>
                            </select>
                        </div>
                        <button type="submit" class="filter-btn">Apply Filters</button>
                    </div>
                    <input type="hidden" name="sort" id="sortInput" value="<?php echo esc($sort_by); ?>">
                    <input type="hidden" name="limit" id="limitInput" value="<?php echo $limit; ?>">
                </form>
            </div>
        </section>

        <!-- Products Grid -->
        <section class="products-section">
            <div class="container">
                <div class="section-header">
                    <h2>Available Dresses</h2>

                    <div style="display:flex; align-items:center; gap:12px;">
                        <div class="sort-options">
                            <label for="sort">Sort by:</label>
                            <select id="sort">
                                <option value="newest" <?php echo ($sort_by === 'newest') ? 'selected' : ''; ?>>Newest</option>
                                <option value="price-low" <?php echo ($sort_by === 'price-low') ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price-high" <?php echo ($sort_by === 'price-high') ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="popular" <?php echo ($sort_by === 'popular') ? 'selected' : ''; ?>>Most Popular</option>
                            </select>
                        </div>

                        <div class="view-toggle" role="tablist" aria-label="View toggle">
                            <button class="toggle active" data-view="shop" role="tab" aria-selected="true">Shop</button>
                            <button class="toggle" data-view="for-you" role="tab" aria-selected="false">For you</button>
                        </div>
                    </div>
                </div>

                <div class="products-grid" id="productsGrid">
                    <?php if (empty($products)): ?>
                        <div style="grid-column:1/-1; background:#fff;border:1px solid #f1f1f1;padding:20px;border-radius:8px;text-align:center;">
                            No products found matching your filters.
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $p): 
                            $pid = (int)$p['product_id'];
                            $title = esc($p['name'] ?? 'Untitled');
                            $price = is_numeric($p['price']) ? number_format((float)$p['price'], 2) : '0.00';
                            $img = !empty($p['image']) ? esc($p['image']) : 'gallery/placeholder.png';
                            $stock = isset($p['stock']) ? (int)$p['stock'] : 0;

                            $is_wishlisted = isset($wishlist_status[$pid]);
                        ?>
                        <a href="productdetail.php?product_id=<?php echo $pid; ?>" class="product-link" style="text-decoration:none;">
                            <div class="product-card" data-id="<?php echo $pid; ?>">
                                <div class="product-image">
                                    <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>" onerror="this.onerror=null;this.src='gallery/placeholder.png'">
                                    <button class="wishlist-btn <?php echo $is_wishlisted ? 'active' : ''; ?>" data-product-id="<?php echo $pid; ?>" aria-label="Add to wishlist">
                                        <svg viewBox="0 0 24 24" fill="<?php echo $is_wishlisted ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" width="18" height="18">
                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                        </svg>
                                    </button>

                                </div>
                                <div class="product-info">
                                    <h3 class="product-title"><?php echo $title; ?></h3>
                                    <p class="product-designer">By Designer</p>
                                    <div class="product-details">
                                        <span class="rental-period">3-day rental</span>
                                    </div>
                                    <div class="product-price">R<?php echo $price; ?></div>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="load-more-section">
                    <?php if ($limit < $total_products): ?>
                        <button class="load-more-btn" id="loadMore" data-current-limit="<?php echo $limit; ?>">Load More Dresses</button>
                    <?php else: ?>
                        <p>All products loaded</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- Login Modal -->
    <div class="modal-overlay" id="loginModal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Sign In Required</h3>
                <p>Please sign in to access this feature</p>
            </div>
            <div class="modal-actions">
                <button class="modal-btn primary" id="goToRegister">Sign In / Register</button>
                <button class="modal-btn secondary" id="closeModal">Continue Browsing</button>
            </div>
        </div>
    </div>

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
                        <!-- Replaced generic SVG with a proper TikTok logo image (keeps layout & sizing identical) -->
                        <a href="https://www.tiktok.com/@ozyde_designs?_t=ZS-8zlyfPi8HHJ&_r=1" target="_blank" rel="noopener" aria-label="TikTok">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2f/TikTok_logo.svg/1200px-TikTok_logo.svg.png" alt="TikTok" style="width:18px;height:18px;display:block" />
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
                © 2025 Ozyde. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // Filter form handling
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            // Reset limit when applying new filters
            document.getElementById('limitInput').value = 8;
        });
        
        // Sort handling
        document.getElementById('sort').addEventListener('change', function() {
            document.getElementById('sortInput').value = this.value;
            document.getElementById('filterForm').submit();
        });
        
        // Load More functionality
        const loadMoreBtn = document.getElementById('loadMore');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
                const currentLimit = parseInt(this.getAttribute('data-current-limit'));
                const newLimit = currentLimit + 8;
                
                // Update the limit input and submit the form
                document.getElementById('limitInput').value = newLimit;
                document.getElementById('filterForm').submit();
            });
        }
        
        // Wishlist functionality
        document.querySelectorAll('.wishlist-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = this.getAttribute('data-product-id');
                const isActive = this.classList.contains('active');
                const action = isActive ? 'remove' : 'add';
                
                // Check if user is logged in
                const loggedIn = <?php echo $logged_in ? 'true' : 'false'; ?>;
                
                if (!loggedIn) {
                    document.getElementById('loginModal').classList.add('active');
                    document.getElementById('loginModal').setAttribute('aria-hidden', 'false');
                    return;
                }
                
                // Toggle visual state immediately for better UX
                this.classList.toggle('active');
                this.querySelector('svg').setAttribute('fill', this.classList.contains('active') ? 'currentColor' : 'none');
                
                // Send AJAX request to update wishlist
                const formData = new FormData();
                formData.append('wishlist_action', action);
                formData.append('product_id', productId);
                
                fetch('catalog.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        // Revert visual state if request failed
                        this.classList.toggle('active');
                        this.querySelector('svg').setAttribute('fill', this.classList.contains('active') ? 'currentColor' : 'none');
                        alert(data.message || 'Failed to update wishlist. Please try again.');
                    } else {
                        // Update wishlist count in navigation
                        updateWishlistCount(action);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Revert visual state if request failed
                    this.classList.toggle('active');
                    this.querySelector('svg').setAttribute('fill', this.classList.contains('active') ? 'currentColor' : 'none');
                    alert('Failed to update wishlist. Please try again.');
                });
            });
        });
        
        function updateWishlistCount(action) {
            const wishlistCount = document.querySelector('.wishlist-count');
            let currentCount = parseInt(wishlistCount?.textContent || 0);
            
            if (action === 'add') {
                currentCount++;
            } else if (action === 'remove') {
                currentCount = Math.max(0, currentCount - 1);
            }
            
            if (wishlistCount) {
                if (currentCount > 0) {
                    wishlistCount.textContent = currentCount;
                    wishlistCount.style.display = 'flex';
                } else {
                    wishlistCount.style.display = 'none';
                }
            }
        }
        
        // Search functionality
        document.getElementById('searchBtn').addEventListener('click', function() {
            const q = document.getElementById('searchInput').value.trim();
            if (!q) { 
                alert('Please enter a search term'); 
                return; 
            }
            window.location.href = 'search.html?q=' + encodeURIComponent(q);
        });
        
        // Login modal handling
        const loginModal = document.getElementById('loginModal');
        const goToRegister = document.getElementById('goToRegister');
        const closeModal = document.getElementById('closeModal');

        goToRegister.addEventListener('click', function() {
            window.location.href = 'register.html?redirect=catalog.php';
        });
        
        closeModal.addEventListener('click', function() {
            loginModal.classList.remove('active');
            loginModal.setAttribute('aria-hidden', 'true');
        });
    </script>
</body>
</html>