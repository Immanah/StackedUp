<?php
session_start();

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ozyde';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "Database connection failed: (" . $mysqli->connect_errno . ") " . htmlspecialchars($mysqli->connect_error);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: register.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$user_query = "SELECT u.first_name, u.last_name, u.email, u.phone 
               FROM users u 
               WHERE u.user_id = ?";
$user_stmt = $mysqli->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Fetch custom orders for this user
$orders_query = "SELECT co.custom_order_id, co.description, co.fabric_preference, co.budget,
                        co.status, co.bust, co.waist, co.hips, co.height, co.sleeve_length,
                        co.shoulder_width, co.notes, co.image_url, co.created_at, co.updated_at
                 FROM custom_orders co 
                 WHERE co.user_id = ? 
                 ORDER BY co.created_at DESC";
$orders_stmt = $mysqli->prepare($orders_query);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
$custom_orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $custom_orders[] = $row;
}
$orders_stmt->close();

// Helper function to escape output
function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Helper function to get status badge class
function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return 'status-pending';
        case 'in_consultation':
            return 'status-consultation';
        case 'in_progress':
            return 'status-progress';
        case 'completed':
            return 'status-completed';
        case 'cancelled':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}

// Helper function to get status display text
function getStatusText($status) {
    switch ($status) {
        case 'pending':
            return 'Pending Review';
        case 'in_consultation':
            return 'In Consultation';
        case 'in_progress':
            return 'In Progress';
        case 'completed':
            return 'Completed';
        case 'cancelled':
            return 'Cancelled';
        default:
            return 'Pending';
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Custom Orders — OZYDE Boutique</title>

    <style>
        /* Inherit all the CSS from customer dashboard */
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
            box-sizing: border-box
        }
        
        body {
            margin: 0;
            font-family: "Helvetica Neue", Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
            -webkit-font-smoothing: antialiased
        }
        
        a {
            color: inherit;
            text-decoration: none
        }
        
        .container {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 20px
        }

        /* ===== Consistent Navbar Styles ===== */
        .nav-wrap {
            background: #0b0b0b;
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
            font-size: 16px
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
        
        .search {
            flex: 1;
            max-width: 400px;
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

        /* ===== Main Dashboard Content ===== */
        main {
            padding: 0;
            min-height: calc(100vh - 200px);
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 40px 0;
            margin-bottom: 40px;
            width: 100%;
        }
        
        .welcome-content {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .welcome-content h1 {
            margin: 0 0 16px 0;
            font-size: 32px;
            font-weight: 800;
            color: var(--accent);
            line-height: 1.2;
        }
        
        .welcome-content p {
            margin: 0;
            color: var(--muted);
            font-size: 18px;
        }
        
        /* Dashboard Content Container */
        .dashboard-content {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Section Titles */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 20px 0;
            color: var(--accent);
        }
        
        /* Quick Actions */
        .quick-actions {
            margin: 20px 0 32px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            padding: 12px 24px;
            border: 0;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            background: #333;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            padding: 12px 24px;
            border: 1px solid #e6e6e6;
            border-radius: 8px;
            background: #fff;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        
        /* Orders Section */
        .orders-section {
            margin-bottom: 40px;
        }
        
        .orders-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .orders-count {
            color: var(--muted);
            font-size: 14px;
        }
        
        /* Orders Grid */
        .orders-grid {
            display: grid;
            gap: 24px;
        }
        
        .order-card {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .order-info h3 {
            margin: 0 0 4px 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
        }
        
        .order-id {
            color: var(--muted);
            font-size: 14px;
            margin: 0;
        }
        
        .order-date {
            color: var(--muted);
            font-size: 14px;
            margin: 0;
        }
        
        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fff3e0;
            color: var(--warning);
        }
        
        .status-consultation {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-progress {
            background: #e8f5e8;
            color: var(--success);
        }
        
        .status-completed {
            background: #e8f5e8;
            color: var(--success);
        }
        
        .status-cancelled {
            background: #ffebee;
            color: var(--danger);
        }
        
        .order-details {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
        }
        
        .order-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .order-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .order-image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 14px;
            font-weight: 600;
        }
        
        .order-content {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .order-description {
            margin: 0;
            color: var(--text);
            line-height: 1.5;
        }
        
        .order-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }
        
        .spec-item {
            display: flex;
            flex-direction: column;
        }
        
        .spec-label {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .spec-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--accent);
        }
        
        .order-notes {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            margin-top: 8px;
        }
        
        .notes-label {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .notes-content {
            font-size: 14px;
            color: var(--text);
            line-height: 1.5;
        }
        
        .order-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f5f5f5;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border: 2px dashed #e6e6e6;
            border-radius: 12px;
            margin: 40px 0;
        }
        
        .empty-state-icon {
            width: 64px;
            height: 64px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--muted);
        }
        
        .empty-state h3 {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--accent);
        }
        
        .empty-state p {
            margin: 0 0 24px 0;
            color: var(--muted);
            font-size: 16px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* ===== Consistent Footer Styles ===== */
        footer {
            border-top: 1px solid #eee;
            padding: 36px 0;
            margin-top: 48px;
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
                display: none;
            }
            
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }
            
            .order-details {
                grid-template-columns: 1fr;
            }
            
            .order-image {
                height: 150px;
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
            
            .footer-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .order-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .order-actions {
                flex-direction: column;
            }
            
            .order-specs {
                grid-template-columns: 1fr 1fr;
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
                    <li><a href="about.html">About</a></li>
                    <li><a href="blog.html">Blog</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                    <li><a href="custommade.html">Custom Made</a></li>
                    <li><a href="catalog.php">Browse</a></li>
                </ul>
            </nav>

            <div class="icons" role="group" aria-label="User actions">
                <!-- Help Button -->
                <a href="help.html" class="icon-only" title="Help" aria-label="Help">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="white" stroke-width="1.2" fill="none"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                        <line x1="12" y1="17" x2="12" y2="17" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
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
                        <a href="my-account.html">My Account</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" id="logoutLink">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ===== Main Dashboard Content ===== -->
    <main>
        <!-- Welcome Banner -->
        <section class="welcome-banner">
            <div class="welcome-content">
                <h1>Your Custom Orders</h1>
                <p>Manage and track your custom-made dress orders</p>
            </div>
        </section>

        <div class="dashboard-content">
            <!-- Quick Actions -->
            <section class="quick-actions">
                <div class="action-buttons">
                    <a href="custommade_loggedin.php" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        Create New Custom Order
                    </a>
                    <a href="customerdashboard.php" class="btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M19 12H5M12 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </section>

            <!-- Orders Section -->
            <section class="orders-section">
                <div class="orders-header">
                    <div>
                        <h2 class="section-title">Your Custom Orders</h2>
                        <p class="orders-count"><?php echo count($custom_orders); ?> order(s) found</p>
                    </div>
                </div>

                <?php if (empty($custom_orders)): ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2"/>
                                <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </div>
                        <h3>No Custom Orders Yet</h3>
                        <p>You haven't placed any custom orders yet. Start by creating your first custom-made dress order.</p>
                        <a href="custommade_loggedin.php" class="btn-primary">Create Your First Custom Order</a>
                    </div>
                <?php else: ?>
                    <!-- Orders Grid -->
                    <div class="orders-grid">
                        <?php foreach ($custom_orders as $order): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div class="order-info">
                                        <h3>Custom Dress Order</h3>
                                        <p class="order-id">Order #<?php echo esc($order['custom_order_id']); ?></p>
                                        <p class="order-date">Placed on <?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
                                    </div>
                                    <div class="order-status-badge">
                                        <span class="order-status <?php echo getStatusBadge($order['status']); ?>">
                                            <?php echo getStatusText($order['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="order-details">
                                    <div class="order-image">
                                        <?php if (!empty($order['image_url'])): ?>
                                            <img src="<?php echo esc($order['image_url']); ?>" alt="Custom order reference image">
                                        <?php else: ?>
                                            <div class="order-image-placeholder">
                                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                                    <circle cx="8.5" cy="8.5" r="1.5" stroke="currentColor" stroke-width="2"/>
                                                    <path d="m21 15-5-5L5 21" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="order-content">
                                        <?php if (!empty($order['description'])): ?>
                                            <p class="order-description"><?php echo esc($order['description']); ?></p>
                                        <?php endif; ?>

                                        <div class="order-specs">
                                            <?php if (!empty($order['fabric_preference'])): ?>
                                                <div class="spec-item">
                                                    <span class="spec-label">Fabric</span>
                                                    <span class="spec-value"><?php echo esc($order['fabric_preference']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($order['budget'])): ?>
                                                <div class="spec-item">
                                                    <span class="spec-label">Budget</span>
                                                    <span class="spec-value">R<?php echo number_format($order['budget'], 2); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($order['bust'])): ?>
                                                <div class="spec-item">
                                                    <span class="spec-label">Bust</span>
                                                    <span class="spec-value"><?php echo esc($order['bust']); ?> cm</span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($order['waist'])): ?>
                                                <div class="spec-item">
                                                    <span class="spec-label">Waist</span>
                                                    <span class="spec-value"><?php echo esc($order['waist']); ?> cm</span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($order['hips'])): ?>
                                                <div class="spec-item">
                                                    <span class="spec-label">Hips</span>
                                                    <span class="spec-value"><?php echo esc($order['hips']); ?> cm</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($order['notes'])): ?>
                                            <div class="order-notes">
                                                <div class="notes-label">Additional Notes</div>
                                                <div class="notes-content"><?php echo nl2br(esc($order['notes'])); ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="order-actions">
                                            <?php if ($order['status'] === 'pending' || $order['status'] === 'in_consultation'): ?>
                                                <button class="btn-secondary" onclick="contactSupport(<?php echo $order['custom_order_id']; ?>)">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" stroke="currentColor" stroke-width="2"/>
                                                    </svg>
                                                    Contact Support
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['status'] === 'completed'): ?>
                                                <button class="btn-primary" onclick="reorderDesign(<?php echo $order['custom_order_id']; ?>)">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                        <path d="M20 12H4M4 12l6-6m-6 6l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                    Reorder This Design
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- ===== Footer ===== -->
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
                © 2024 OZYDE Boutique. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- ===== JavaScript ===== -->
    <script>
        // Navigation functionality
        document.getElementById('brandLink')?.addEventListener('click', () => {
            window.location.href = 'finalhomepage.php';
        });

        // Search functionality
        document.getElementById('searchBtn')?.addEventListener('click', () => {
            const query = document.getElementById('searchInput').value.trim();
            if (!query) {
                alert('Please enter a search term');
                return;
            }
            window.location.href = `catalog.php?search=${encodeURIComponent(query)}`;
        });

        document.getElementById('searchInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });

        // Order action functions
        function contactSupport(orderId) {
            alert('Contacting support for order #' + orderId + '. Our team will get in touch with you shortly.');
            // In a real implementation, this could open a chat or contact form
        }

        function reorderDesign(orderId) {
            if (confirm('Would you like to reorder this custom design? We will use your previous measurements and specifications.')) {
                // In a real implementation, this would pre-fill a new custom order form
                window.location.href = 'custommade.html?reorder=' + orderId;
            }
        }

        // Initialize animations
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.order-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>