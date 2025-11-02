<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session
error_log("Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));

$logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Get wishlist count for navigation if logged in
$wishlist_count = 0;
if ($logged_in) {
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'ozyde';
    
    try {
        $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if (!$mysqli->connect_errno) {
            $count_sql = "SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?";
            $count_stmt = $mysqli->prepare($count_sql);
            if ($count_stmt) {
                $count_stmt->bind_param("i", $_SESSION['user_id']);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                if ($count_result) {
                    $wishlist_row = $count_result->fetch_assoc();
                    $wishlist_count = $wishlist_row['count'];
                }
                $count_stmt->close();
            }
        }
        $mysqli->close();
    } catch (Exception $e) {
        error_log("Wishlist count error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Ozyde — Luxury Dress Rentals</title>
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
            --hero-height: 420px;
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
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.5;
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
        
        .btn-signup {
            background: var(--accent);
            color: #fff;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s ease;
        }
        
        .btn-signup:hover {
            background: #333;
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

        /* Search Bar - Only for logged-in users */
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
            background: rgba(255,255,255,0.06);
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
        
        /* HERO - VIDEO ONLY (No slideshow) */
        
        .hero {
            position: relative;
            height: var(--hero-height);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
        }
        
        .hero-video {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translateX(-50%) translateY(-50%);
            object-fit: cover;
            z-index: 1;
        }
        
        /* hero text */
        .hero-inner {
            position: relative;
            z-index: 3;
            max-width: 720px;
            padding: 40px 20px;
            background: transparent;
            border-radius: 8px;
            box-shadow: none;
        }
        
        .hero h1 {
            margin: 0 0 8px;
            font-size: 36px;
            letter-spacing: 0.2px;
            color: #fff;
            text-shadow: 0 6px 18px rgba(0, 0, 0, 0.45)
        }
        
        .hero p.lead {
            margin: 0 0 18px;
            color: #fff;
            font-weight: 500;
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.35)
        }
        
        .hero .cta {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap
        }
        
        .btn {
            padding: 10px 18px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-weight: 600;
            backdrop-filter: blur(4px);
        }
        
        .btn.primary {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent)
        }
        
        .hero .subtext {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 8px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.25)
        }
        /* subtle gradient overlay to help legibility */
        
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.18) 0%, rgba(0, 0, 0, 0.35) 100%);
            z-index: 2;
            pointer-events: none;
        }
        /* Sections */
        
        .section {
            padding: 48px 0
        }
        
        .center {
            text-align: center
        }
        /* Reviews */
        
        .reviews {
            display: flex;
            gap: 18px;
            align-items: flex-start;
            justify-content: center;
        }
        
        .review-card {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            max-width: 760px;
            width: 100%;
            transition: opacity .6s ease, transform .6s ease;
            opacity: 0;
            transform: translateX(20px);
        }
        
        .review-card.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .profile-thumb {
            width: 120px;
            height: 120px;
            border-radius: 6px;
            background: #eee;
            flex: 0 0 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid #ddd;
        }
        
        .profile-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }
        
        .review-body {
            flex: 1
        }
        
        .review-meta {
            font-weight: 700;
            margin-bottom: 6px
        }
        
        .review-location {
            color: var(--muted);
            font-weight: 600;
            font-size: 13px
        }
        
        .review-text {
            color: #333;
            margin-top: 8px
        }
        
        .stars {
            margin-top: 10px
        }
        /* Collections (circular) */
        
        .collections {
            display: flex;
            gap: 40px;
            justify-content: center;
            align-items: center;
            margin-top: 28px;
            flex-wrap: wrap;
        }
        
        .collection-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        
        .collection {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 6px solid #e6e6e6;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            font-weight: 600;
            color: transparent;
            text-align: center;
            padding: 10px;
            background-size: cover;
            background-position: center;
            transition: transform .25s ease, box-shadow .25s ease;
        }
        
        .collection:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06)
        }
        
        .collection-label {
            font-weight: 600;
            color: var(--accent);
            font-size: 14px;
            text-align: center;
        }
        /* How it works */
        
        .how-section {
            position: relative;
        }
        
        .how-arrows {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 40px;
            margin: 20px 0;
        }
        
        .arrow {
            font-size: 24px;
            color: var(--muted);
        }
        
        .how {
            display: flex;
            gap: 40px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .how-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            max-width: 200px;
        }
        
        .how .how-btn {
            background: #111;
            color: #fff;
            padding: 14px 28px;
            border-radius: 4px;
            font-weight: 600;
            min-width: 120px;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .how-description {
            font-size: 14px;
            color: var(--muted);
            margin-top: 8px;
        }
        /* FAQ (3 columns) */
        
        .faq-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 18px;
        }
        
        .faq-item h4 {
            margin: 0 0 6px;
            font-size: 15px
        }
        
        .faq-item p {
            margin: 0;
            color: var(--muted);
            font-size: 14px
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
            .faq-grid {
                grid-template-columns: repeat(2, 1fr)
            }
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }
            .how-arrows {
                gap: 20px;
            }
            .how {
                gap: 20px;
            }
            .search {
                order: 3;
                max-width: 100%;
                margin: 15px 0 0 0;
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
            .hero h1 {
                font-size: 26px
            }
            .faq-grid {
                grid-template-columns: 1fr
            }
            .profile-thumb {
                width: 88px;
                height: 88px;
                flex: 0 0 88px
            }
            .collection {
                width: 100px;
                height: 100px
            }
            .footer-grid {
                grid-template-columns: 1fr;
            }
            .how-arrows {
                gap: 10px;
            }
            .how {
                gap: 10px;
            }
            .arrow {
                font-size: 18px;
            }
        }
        
        .muted {
            color: var(--muted)
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

            <?php if ($logged_in): ?>
            <!-- Search Bar - Only for logged-in users -->
            <div class="search" role="search" aria-label="Site search">
                <input id="searchInput" type="search" placeholder="Search dresses, designers, collection..." aria-label="Search">
                <button id="searchBtn" aria-label="Search">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M21 21l-4.35-4.35" stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="11" cy="11" r="6" stroke="#111" stroke-width="2"/>
                    </svg>
                </button>
            </div>
            <?php endif; ?>

            <!-- UPDATED NAVIGATION ORDER -->
            <nav aria-label="Main navigation">
                <ul id="main-nav">
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="catalog.php">Browse</a></li>
                    <li><a href="custommade_loggedin.php">Custom Made</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="blog.php">Blog</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                </ul>
            </nav>

            <div class="icons" role="group" aria-label="User actions">
                <?php if ($logged_in): ?>
                    <!-- Help Button -->
                    <a href="help.html" class="icon-only" title="Help" aria-label="Help">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="white" stroke-width="1.2" fill="none"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                            <line x1="12" y1="17" x2="12" y2="17" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                        </svg>
                    </a>

                    <!-- Wishlist -->
                    <a href="wishlist.php" class="icon-only" title="Wishlist" aria-label="Wishlist">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="white" stroke-width="1.2" fill="none"/>
                        </svg>
                        <?php if ($wishlist_count > 0): ?>
                            <span class="wishlist-count"><?php echo $wishlist_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Cart -->
                    <a href="cart.php" class="icon-only" title="Shopping Cart" aria-label="Shopping Cart">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <circle cx="9" cy="21" r="1" stroke="white" stroke-width="1.2" fill="none"/>
                            <circle cx="20" cy="21" r="1" stroke="white" stroke-width="1.2" fill="none"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" stroke="white" stroke-width="1.2" fill="none"/>
                        </svg>
                    </a>

                    <!-- Profile Dropdown -->
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
                            <a href="logout.php" id="logoutLink">Sign Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Guest View - Show login/register option -->
                    <a href="register.html" class="btn-signup">Sign Up / Login</a>
                    <a href="help.html" class="icon-only" title="Help" aria-label="Help">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="white" stroke-width="1.2" fill="none"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                            <line x1="12" y1="17" x2="12" y2="17" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- HERO - VIDEO ONLY (No slideshow) -->
    <section class="hero" aria-label="Hero section">
        <!-- Video plays continuously -->
        <video class="hero-video" autoplay muted loop playsinline id="heroVideo">
            <source src="SAPPHIRE.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>

        <div class="hero-inner container">
            <h1>Be Best Dressed</h1>
            <p class="lead">Rent our luxury exquisite items that will have you looking and feeling like the best dressed in any occasion</p>
            <div class="cta">
                <?php if ($logged_in): ?>
                    <a href="catalog.php" class="btn primary">Browse Dresses</a>
                <?php else: ?>
                    <a href="register.html" class="btn primary">Sign Up</a>
                <?php endif; ?>
                <a href="magazine.pdf" class="btn" target="_blank" rel="noopener">View Magazine</a>
            </div>
            <div class="subtext">View our Magazine with our best looks and reserve items.</div>
        </div>
    </section>

    <!-- Shop By Collections -->
    <section class="section center">
        <div class="container">
            <h3>Shop By Collections</h3>
            <div class="collections" aria-label="Collections">
                <div class="collection-item">
                    <a href="<?php echo $logged_in ? 'catalog.php?category=wedding' : 'register.html'; ?>" class="collection" id="col-1" style="background-image:url('PIC10.jpg');"></a>
                    <div class="collection-label">Wedding</div>
                </div>
                <div class="collection-item">
                    <a href="<?php echo $logged_in ? 'catalog.php?category=matric' : 'register.html'; ?>" class="collection" id="col-2" style="background-image:url('PIC1.jpg');"></a>
                    <div class="collection-label">Matric Dance</div>
                </div>
                <div class="collection-item">
                    <a href="<?php echo $logged_in ? 'catalog.php?category=birthday' : 'register.html'; ?>" class="collection" id="col-3" style="background-image:url('PIC4.jpg');"></a>
                    <div class="collection-label">Birthday</div>
                </div>
                <div class="collection-item">
                    <a href="<?php echo $logged_in ? 'custommade.html' : 'register.html'; ?>" class="collection" id="col-4" style="background-image:url('PIC6.jpg');"></a>
                    <div class="collection-label">Custom-made Dresses</div>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section class="section center how-section">
        <div class="container">
            <h4>How It Works</h4>

            <div class="how-arrows">
                <div class="how-step">
                    <div class="how-btn">Browse</div>
                    <div class="how-description">Explore our collection of designer dresses for any occasion</div>
                </div>
                <div class="arrow">→</div>
                <div class="how-step">
                    <div class="how-btn">Book</div>
                    <div class="how-description">Select your dress, choose dates, and complete your reservation</div>
                </div>
                <div class="arrow">→</div>
                <div class="how-step">
                    <div class="how-btn">Return</div>
                    <div class="how-description">Wear, enjoy, and return - we handle cleaning and maintenance</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Client Reviews -->
    <section class="section container center" aria-labelledby="reviews-heading">
        <h3 id="reviews-heading">Client Reviews</h3>

        <div id="reviews" class="reviews" aria-live="polite">
            <div class="review-card" id="review-0" role="article" aria-hidden="true">
                <div class="profile-thumb"><img src="profile1.jpg" alt="Client profile image"></div>
                <div class="review-body">
                    <div class="review-meta">Clara Clark <span class="review-location"> — Rivonia, Sandton</span></div>
                    <div class="review-text">The dress was a show stopper and fit just like a glove! I am extremely impressed with this business — all processes were smooth, keep it up!</div>
                    <div class="stars" aria-hidden="true">⭐️⭐️⭐️⭐️⭐️</div>
                </div>
            </div>

            <div class="review-card" id="review-1" role="article" aria-hidden="true">
                <div class="profile-thumb"><img src="profile2.jpg" alt="Client profile image"></div>
                <div class="review-body">
                    <div class="review-meta">M. Dlamini <span class="review-location"> — Durban</span></div>
                    <div class="review-text">Amazing collection and timely delivery. The rental process was simple and the dress looked brand new.</div>
                    <div class="stars" aria-hidden="true">⭐️⭐️⭐️⭐️⭐️</div>
                </div>
            </div>

            <div class="review-card" id="review-2" role="article" aria-hidden="true">
                <div class="profile-thumb"><img src="profile3.jpg" alt="Client profile image"></div>
                <div class="review-body">
                    <div class="review-meta">S. Patel <span class="review-location"> — Cape Town</span></div>
                    <div class="review-text">Perfect fit and customer service was very friendly. Will definitely rent again!</div>
                    <div class="stars" aria-hidden="true">⭐️⭐️⭐️⭐️⭐️</div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section container" aria-labelledby="faq-heading">
        <h3 id="faq-heading">FAQ</h3>
        <div class="faq-grid" role="list">
            <div class="faq-item" role="listitem">
                <h4>How long can I rent a dress?</h4>
                <p>Rental durations vary — typical rentals are 3–7 days. You can request longer if needed.</p>
            </div>
            <div class="faq-item" role="listitem">
                <h4>What if the dress doesn't fit?</h4>
                <p>We offer simple exchange or alteration guidance. Reach out to our support for options.</p>
            </div>
            <div class="faq-item" role="listitem">
                <h4>Are dresses cleaned between rentals?</h4>
                <p>Yes. All items are professionally cleaned and inspected between rentals for hygiene and quality.</p>
            </div>

            <div class="faq-item" role="listitem">
                <h4>Is delivery available nationwide?</h4>
                <p>We deliver across major cities; some remote areas may have limited service.</p>
            </div>
            <div class="faq-item" role="listitem">
                <h4>Do you accept returns late?</h4>
                <p>Late returns may incur a small fee. Check our terms for the exact policy.</p>
            </div>
            <div class="faq-item" role="listitem">
                <h4>How do I sign up?</h4>
                <p>Click the Sign Up button and your team will be able to implement the flow on the separate signup page.</p>
            </div>
        </div>
    </section>

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
                        <!-- UPDATED: Proper TikTok SVG Icon -->
                        <a href="https://www.tiktok.com/@ozyde_designs?_t=ZS-8zlyfPi8HHJ&_r=1" target="_blank" rel="noopener" aria-label="TikTok">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#333">
                                <path d="M19.589 6.686a4.793 4.793 0 0 1-3.77-4.245V2h-3.445v13.672a2.896 2.896 0 0 1-5.201 1.743l-.002-.001.002.001a2.895 2.895 0 0 1 3.183-4.51v-3.5a6.329 6.329 0 0 0-5.394 10.692 6.33 6.33 0 0 0 10.857-4.424V8.687a8.182 8.182 0 0 0 4.773 1.526V6.79a4.831 4.831 0 0 1-1.003-.104z"/>
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
                © 2025 Ozyde. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        /**********************
         * Reviews rotation logic
         **********************/
        (function reviewsCarousel() {
            const reviewCards = Array.from(document.querySelectorAll('.review-card'));
            let idx = 0;

            function showIndex(i) {
                reviewCards.forEach((card, n) => {
                    if (n === i) {
                        card.classList.add('show');
                        card.setAttribute('aria-hidden', 'false');
                    } else {
                        card.classList.remove('show');
                        card.setAttribute('aria-hidden', 'true');
                    }
                });
            }
            if (reviewCards.length === 0) return;
            showIndex(idx);
            setInterval(() => {
                idx = (idx + 1) % reviewCards.length;
                showIndex(idx);
            }, 5000);
        })();

        // Search functionality for logged-in users
        <?php if ($logged_in): ?>
        document.getElementById('searchBtn').addEventListener('click', function() {
            const q = document.getElementById('searchInput').value.trim();
            if (!q) { 
                alert('Please enter a search term'); 
                return; 
            }
            // Redirect to catalog with search parameter
            window.location.href = 'catalog.php?search=' + encodeURIComponent(q);
        });

        // Enter key support for search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });
        <?php endif; ?>

        // Logo click handler
        document.getElementById('brandLink').addEventListener('click', function() {
            window.location.href = 'index.php';
        });

        // Video error handling
        document.getElementById('heroVideo').addEventListener('error', function(e) {
            console.error('Video failed to load:', e);
        });

        // Video loaded successfully
        document.getElementById('heroVideo').addEventListener('loadeddata', function() {
            console.log('Video loaded successfully');
        });
    </script>
</body>
</html>