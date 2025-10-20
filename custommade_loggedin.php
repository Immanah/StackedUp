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

// Fetch user data for pre-filling form
$user_query = "SELECT u.first_name, u.last_name, u.email, u.phone, 
                      um.bust, um.waist, um.hips
               FROM users u 
               LEFT JOIN user_measurements um ON u.user_id = um.user_id 
               WHERE u.user_id = ?";
$user_stmt = $mysqli->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Helper function to escape output
function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Made Dresses - OZYDE Boutique</title>
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #222;
            background: #fff;
        }

        /* Navigation Styles - Same as customer dashboard */
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
            text-decoration: none;
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

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 80px 0;
            text-align: center;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .hero h1 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 16px;
            color: #111;
        }

        .hero p {
            font-size: 20px;
            color: #666;
            margin-bottom: 32px;
        }

        /* Slideshow Styles */
        .slideshow-section {
            padding: 60px 0;
            background: #fff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-title {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 48px;
            color: #111;
        }

        .slideshow-container {
            position: relative;
            max-width: 1000px;
            margin: 0 auto;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .slideshow {
            position: relative;
            height: 600px;
            background: #000;
        }

        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }

        .slide.active {
            opacity: 1;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .slide-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: #fff;
            padding: 40px;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.5s ease 0.3s;
        }

        .slide.active .slide-content {
            transform: translateY(0);
            opacity: 1;
        }

        .slide-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .slide-description {
            font-size: 16px;
            opacity: 0.9;
        }

        .slideshow-nav {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            transform: translateY(-50%);
            z-index: 10;
        }

        .slideshow-nav button {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .slideshow-nav button:hover {
            background: #fff;
            transform: scale(1.1);
        }

        .slideshow-dots {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dot.active {
            background: #fff;
            transform: scale(1.2);
        }

        /* Form Section */
        .form-section {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            padding: 48px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .form-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #111;
            text-align: center;
        }

        .form-subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 40px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        input, select, textarea {
            padding: 12px 16px;
            border: 2px solid #e6e6e6;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #111;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: #111;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: #333;
            transform: translateY(-2px);
        }

        .user-info-notice {
            background: #e8f5e8;
            border: 1px solid #2fa46b;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: center;
            color: #2fa46b;
            font-weight: 600;
        }

        /* Footer Styles */
        footer {
            background: #fafafa;
            border-top: 1px solid #eee;
            padding: 48px 0 24px;
        }

        .footer-grid {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 32px;
        }

        .footer-column h4 {
            margin-bottom: 16px;
            color: #111;
            font-weight: 600;
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column li {
            margin-bottom: 8px;
        }

        .footer-column a {
            color: #666;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-column a:hover {
            color: #111;
        }

        .social-links {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: #f5f5f5;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .social-links a:hover {
            background: #111;
        }

        .social-links a:hover svg {
            stroke: #fff;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 20px 0;
            text-align: center;
            color: #666;
            border-top: 1px solid #e6e6e6;
            margin-top: 32px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
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

            .hero h1 {
                font-size: 36px;
            }

            .hero p {
                font-size: 18px;
            }

            .slideshow {
                height: 400px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-container {
                padding: 32px 24px;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        @media (max-width: 480px) {
            .hero {
                padding: 60px 0;
            }

            .hero h1 {
                font-size: 28px;
            }

            .slideshow {
                height: 300px;
            }

            .slide-content {
                padding: 20px;
            }

            .slideshow-nav button {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <header class="nav-wrap">
        <div class="nav">
            <div class="logo" onclick="window.location.href='finalhomepage.php'">
                <div class="logo-badge">✦</div>
                <div>Ozyde</div>
            </div>

            <!-- Search Bar -->
            <div class="search">
                <input type="search" placeholder="Search dresses, designers, collection..." aria-label="Search">
                <button aria-label="Search">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                        <path d="M21 21l-4.35-4.35" stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="11" cy="11" r="6" stroke="#111" stroke-width="2"/>
                    </svg>
                </button>
            </div>

            <nav>
                <ul>
                    <li><a href="finalhomepage.php">Home</a></li>
                    <li><a href="catalog.php">Browse</a></li>
                    <li><a href="custommade_loggedin.php" class="active">Custom Made</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="blog.html">Blog</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                </ul>
            </nav>

            <div class="icons">
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
                        <div class="dropdown-divider"></div>
                        <a href="logout.php">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Custom Made Dresses</h1>
            <p>Your vision, perfectly tailored. Create the dress of your dreams with our expert design team.</p>
        </div>
    </section>

    <!-- Slideshow Section -->
    <section class="slideshow-section">
        <div class="container">
            <h2 class="section-title">Our Custom Creations</h2>
            <div class="slideshow-container">
                <div class="slideshow">
                    <div class="slide active">
                        <img src="https://images.unsplash.com/photo-1539109136881-3be0616acf4b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Elegant Evening Gown">
                        <div class="slide-content">
                            <h3 class="slide-title">Elegant Evening Gown</h3>
                            <p class="slide-description">Custom designed for special occasions</p>
                        </div>
                    </div>
                    <div class="slide">
                        <img src="https://images.unsplash.com/photo-1595777457583-95e059d581b8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Wedding Dress">
                        <div class="slide-content">
                            <h3 class="slide-title">Bridal Masterpiece</h3>
                            <p class="slide-description">Tailored to perfection for your big day</p>
                        </div>
                    </div>
                    <div class="slide">
                        <img src="https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Cocktail Dress">
                        <div class="slide-content">
                            <h3 class="slide-title">Cocktail Elegance</h3>
                            <p class="slide-description">Perfect for parties and special events</p>
                        </div>
                    </div>
                </div>
                <div class="slideshow-nav">
                    <button class="prev-btn">‹</button>
                    <button class="next-btn">›</button>
                </div>
                <div class="slideshow-dots">
                    <div class="dot active"></div>
                    <div class="dot"></div>
                    <div class="dot"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Custom Order Form Section -->
    <section class="form-section">
        <div class="container">
            <div class="form-container">
                <h2 class="form-title">Create Your Custom Dress</h2>
                <p class="form-subtitle">Fill out the form below and our design team will contact you to bring your vision to life</p>
                
                <?php if ($user_data): ?>
                <div class="user-info-notice">
                    Your profile information and measurements have been pre-filled for your convenience
                </div>
                <?php endif; ?>
                
                <form id="customOrderForm" action="submit_custom_order.php" method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fullName">Full Name *</label>
                            <input type="text" id="fullName" name="fullName" value="<?php echo esc($user_data['first_name'] ?? '') . ' ' . esc($user_data['last_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo esc($user_data['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo esc($user_data['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="occasion">Occasion *</label>
                            <select id="occasion" name="occasion" required>
                                <option value="">Select an occasion</option>
                                <option value="wedding">Wedding</option>
                                <option value="matric-dance">Matric Dance</option>
                                <option value="graduation">Graduation</option>
                                <option value="cocktail-party">Cocktail Party</option>
                                <option value="formal-event">Formal Event</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="eventDate">Event Date *</label>
                            <input type="date" id="eventDate" name="eventDate" required>
                        </div>
                        <div class="form-group">
                            <label for="budget">Budget Range *</label>
                            <select id="budget" name="budget" required>
                                <option value="">Select budget range</option>
                                <option value="1000-3000">R1,000 - R3,000</option>
                                <option value="3000-6000">R3,000 - R6,000</option>
                                <option value="6000-10000">R6,000 - R10,000</option>
                                <option value="10000+">R10,000+</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="dressDescription">Dress Description & Inspiration *</label>
                            <textarea id="dressDescription" name="dressDescription" placeholder="Describe the dress you have in mind. Include details about style, fabric, color, and any inspiration images you have..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="bust">Bust Measurement (cm)</label>
                            <input type="number" id="bust" name="bust" step="0.1" value="<?php echo esc($user_data['bust'] ?? ''); ?>" placeholder="e.g., 86">
                        </div>
                        <div class="form-group">
                            <label for="waist">Waist Measurement (cm)</label>
                            <input type="number" id="waist" name="waist" step="0.1" value="<?php echo esc($user_data['waist'] ?? ''); ?>" placeholder="e.g., 68">
                        </div>
                        <div class="form-group">
                            <label for="hips">Hip Measurement (cm)</label>
                            <input type="number" id="hips" name="hips" step="0.1" value="<?php echo esc($user_data['hips'] ?? ''); ?>" placeholder="e.g., 94">
                        </div>
                        <div class="form-group">
                            <label for="height">Height (cm)</label>
                            <input type="number" id="height" name="height" placeholder="e.g., 165">
                        </div>
                        <div class="form-group full-width">
                            <label for="referenceImages">Reference Images (Optional)</label>
                            <input type="file" id="referenceImages" name="referenceImages" multiple accept="image/*">
                        </div>
                        <div class="form-group full-width">
                            <label for="additionalNotes">Additional Notes</label>
                            <textarea id="additionalNotes" name="additionalNotes" placeholder="Any other details, preferences, or special requirements..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="submit-btn">Submit Custom Order Request</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-grid">
            <div class="footer-column">
                <h4>Ozyde</h4>
                <p>Premium dress rentals and custom designs for your special occasions. Quality, style, and affordability combined.</p>
                <div>Address: 5 Liebenberg Rd, Noordwyk, Midrand 1687</div>
                <div class="social-links">
                    <a href="https://www.instagram.com/ozyde_?igsh=NWM0aTd4ZGFmeHVr" target="_blank" aria-label="Instagram">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.2" fill="none"/>
                            <circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.2" fill="none"/>
                            <circle cx="17.5" cy="6.5" r="0.6" fill="currentColor"/>
                        </svg>
                    </a>
                    <a href="https://www.tiktok.com/@ozyde_designs?_t=ZS-8zlyfPi8HHJ&_r=1" target="_blank" aria-label="TikTok">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                        </svg>
                    </a>
                    <a href="mailto:ozydedesigns@gmail.com" aria-label="Email">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="1.2" fill="none"/>
                            <path d="M4 7.5l8 6 8-6" stroke="currentColor" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                        </svg>
                    </a>
                </div>
            </div>
            <div class="footer-column">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="howitworks.html">How It Works</a></li>
                    <li><a href="sizingguide.html">Size Guide</a></li>
                    <li><a href="#">Returns & Policy</a></li>
                    <li><a href="#">Delivery</a></li>
                    <li><a href="help.html">Help Center</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Company</h4>
                <ul>
                    <li><a href="about.html">About Us</a></li>
                    <li><a href="#">Press</a></li>
                    <li><a href="#">Terms</a></li>
                    <li><a href="#">Privacy</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Support</h4>
                <ul>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="cleaning.html">Cleaning & Care Guide</a></li>
                    <li><a href="#">Partnerships</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            © 2024 OZYDE Boutique. All rights reserved.
        </div>
    </footer>

    <script>
        // Slideshow functionality
        class Slideshow {
            constructor() {
                this.slides = document.querySelectorAll('.slide');
                this.dots = document.querySelectorAll('.dot');
                this.prevBtn = document.querySelector('.prev-btn');
                this.nextBtn = document.querySelector('.next-btn');
                this.currentSlide = 0;
                this.slideInterval = null;
                
                this.init();
            }
            
            init() {
                // Event listeners
                this.prevBtn.addEventListener('click', () => this.prevSlide());
                this.nextBtn.addEventListener('click', () => this.nextSlide());
                
                // Dot click events
                this.dots.forEach((dot, index) => {
                    dot.addEventListener('click', () => this.goToSlide(index));
                });
                
                // Auto-advance slides
                this.startAutoSlide();
                
                // Pause on hover
                const slideshow = document.querySelector('.slideshow');
                slideshow.addEventListener('mouseenter', () => this.stopAutoSlide());
                slideshow.addEventListener('mouseleave', () => this.startAutoSlide());
            }
            
            showSlide(index) {
                // Hide all slides
                this.slides.forEach(slide => slide.classList.remove('active'));
                this.dots.forEach(dot => dot.classList.remove('active'));
                
                // Show current slide
                this.slides[index].classList.add('active');
                this.dots[index].classList.add('active');
                
                this.currentSlide = index;
            }
            
            nextSlide() {
                let nextIndex = (this.currentSlide + 1) % this.slides.length;
                this.showSlide(nextIndex);
            }
            
            prevSlide() {
                let prevIndex = (this.currentSlide - 1 + this.slides.length) % this.slides.length;
                this.showSlide(prevIndex);
            }
            
            goToSlide(index) {
                this.showSlide(index);
            }
            
            startAutoSlide() {
                this.stopAutoSlide();
                this.slideInterval = setInterval(() => this.nextSlide(), 5000);
            }
            
            stopAutoSlide() {
                if (this.slideInterval) {
                    clearInterval(this.slideInterval);
                    this.slideInterval = null;
                }
            }
        }
        
        // Initialize slideshow when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new Slideshow();
            
            // Form submission handling
            const form = document.getElementById('customOrderForm');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Basic validation
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#ef4444';
                    } else {
                        field.style.borderColor = '#e6e6e6';
                    }
                });
                
                if (isValid) {
                    // Show success message (in a real app, this would submit to server)
                    alert('Thank you for your custom order request! Our team will contact you within 24 hours.');
                    form.reset();
                    
                    // Restore user data
                    document.getElementById('fullName').value = '<?php echo esc($user_data['first_name'] ?? '') . ' ' . esc($user_data['last_name'] ?? ''); ?>';
                    document.getElementById('email').value = '<?php echo esc($user_data['email'] ?? ''); ?>';
                    document.getElementById('phone').value = '<?php echo esc($user_data['phone'] ?? ''); ?>';
                    document.getElementById('bust').value = '<?php echo esc($user_data['bust'] ?? ''); ?>';
                    document.getElementById('waist').value = '<?php echo esc($user_data['waist'] ?? ''); ?>';
                    document.getElementById('hips').value = '<?php echo esc($user_data['hips'] ?? ''); ?>';
                } else {
                    alert('Please fill in all required fields.');
                }
            });
            
            // Search functionality
            const searchInput = document.querySelector('.search input');
            const searchButton = document.querySelector('.search button');
            
            searchButton.addEventListener('click', () => {
                const query = searchInput.value.trim();
                if (!query) {
                    alert('Please enter a search term');
                    return;
                }
                window.location.href = `catalog.php?search=${encodeURIComponent(query)}`;
            });
            
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    searchButton.click();
                }
            });
        });
    </script>
</body>
</html>