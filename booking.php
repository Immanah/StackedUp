<?php
session_start();
include 'db.php';

// Get product details
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$selected_size = isset($_GET['size']) ? $_GET['size'] : '';

if ($product_id <= 0) {
    header("Location: catalog.php");
    exit;
}

// Fetch product details
$sql = "SELECT * FROM products WHERE product_id = $product_id LIMIT 1";
$result = $conn->query($sql);
if (!$result || $result->num_rows == 0) {
    header("Location: catalog.php");
    exit;
}
$product = $result->fetch_assoc();

// Get unavailable dates from BOOKINGS table (only confirmed bookings)
$unavailable_dates = [];
$unavailable_sql = "SELECT start_date, end_date FROM bookings WHERE product_id = $product_id";
$unavailable_result = $conn->query($unavailable_sql);
if ($unavailable_result && $unavailable_result->num_rows > 0) {
    while ($row = $unavailable_result->fetch_assoc()) {
        $start = new DateTime($row['start_date']);
        $end = new DateTime($row['end_date']);
        $end->modify('+1 day'); // Include end date in the range
        
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($start, $interval, $end);
        
        foreach ($period as $dt) {
            $unavailable_dates[] = $dt->format('Y-m-d');
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $user_id = $_SESSION['user_id'];
    
    // Validate dates
    if (!empty($start_date) && !empty($end_date)) {
        // Check if user already has this product with overlapping dates in THEIR OWN cart
        $check_sql = "SELECT cart_id FROM cart WHERE user_id = ? AND product_id = ? AND 
                     ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR 
                     (? BETWEEN start_date AND end_date) OR (? BETWEEN start_date AND end_date))";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iissssss", $user_id, $product_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            echo "<script>
                alert('You already have this product booked for overlapping dates in your cart. Please select different dates or go to your cart to modify your booking.');
                window.history.back();
            </script>";
        } else {
            // Check if the dates are available (not booked by anyone else in bookings table)
            $availability_sql = "SELECT booking_id FROM bookings WHERE product_id = ? AND 
                               ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR 
                               (? BETWEEN start_date AND end_date) OR (? BETWEEN start_date AND end_date))";
            $availability_stmt = $conn->prepare($availability_sql);
            $availability_stmt->bind_param("issssss", $product_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
            $availability_stmt->execute();
            $availability_stmt->store_result();
            
            if ($availability_stmt->num_rows > 0) {
                echo "<script>
                    alert('Sorry, these dates are no longer available. Please select different dates.');
                    window.history.back();
                </script>";
            } else {
                // Use INSERT IGNORE to handle any potential constraint violations gracefully
                $insert_sql = "INSERT IGNORE INTO cart (user_id, product_id, size, start_date, end_date, quantity, added_at) 
                              VALUES (?, ?, ?, ?, ?, 1, NOW())";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("iisss", $user_id, $product_id, $selected_size, $start_date, $end_date);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo "<script>
                            alert('Rental period selected successfully!');
                            window.location.href = 'cart.php';
                        </script>";
                    } else {
                        // This happens when INSERT IGNORE doesn't insert due to duplicate
                        echo "<script>
                            alert('This item is already in your cart with the same dates.');
                            window.location.href = 'cart.php';
                        </script>";
                    }
                } else {
                    echo "<script>alert('Error adding to cart. Please try again.');</script>";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OZYDE Boutique - Date Selection</title>
    <style>
        /* Your existing CSS styles remain the same */
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
            --airbnb-light: #F7F7F7;
            --airbnb-dark: #484848;
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: var(--airbnb-light);
        }
        
        a {
            color: inherit;
            text-decoration: none;
        }
        
        .container {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }
        
        /* Header Styles */
        .nav-wrap {
            background: #0b0b0b;
            color: #fff;
            position: sticky;
            top: 0;
            width: 100%;
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
        }
        
        .icon-only:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            width: 100%;
        }
        
        .calendar-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }
        
        .calendar-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .calendar-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 800;
            color: var(--accent);
        }
        
        .calendar-header p {
            margin: 0;
            color: var(--muted);
            font-size: 16px;
        }
        
        .product-info {
            margin-bottom: 30px;
            padding: 20px;
            background: var(--airbnb-light);
            border-radius: 8px;
            text-align: center;
        }
        
        .product-details h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .product-details p {
            margin: 4px 0;
            color: var(--muted);
        }
        
        /* Date Inputs */
        .date-inputs {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .date-input {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .date-input label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--muted);
        }
        
        .date-input-field {
            padding: 12px 16px;
            border: 1px solid #e6e6e6;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: border-color 0.2s ease;
            background-color: white;
        }
        
        .date-input-field:hover {
            border-color: #ccc;
        }
        
        .date-input-field:focus {
            outline: none;
            border-color: var(--airbnb-pink);
        }
        
        /* Calendar Styles */
        .calendar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .calendar-header-row {
            background-color: var(--airbnb-light);
        }
        
        .calendar th {
            padding: 15px 0;
            text-align: center;
            font-weight: 600;
            color: var(--airbnb-dark);
            font-size: 14px;
        }
        
        .calendar td {
            padding: 12px 0;
            text-align: center;
            cursor: pointer;
            position: relative;
            transition: background-color 0.2s ease;
        }
        
        .calendar td:hover {
            background-color: #f9f9f9;
        }
        
        .calendar-day {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto;
            font-size: 14px;
        }
        
        .calendar-day.selected {
            background-color: var(--airbnb-pink);
            color: white;
        }
        
        .calendar-day.in-range {
            background-color: rgba(255, 90, 95, 0.1);
        }
        
        .calendar-day.start-date {
            background-color: var(--airbnb-pink);
            color: white;
        }
        
        .calendar-day.end-date {
            background-color: var(--airbnb-pink);
            color: white;
        }
        
        .calendar-day.past-date {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .calendar-day.past-date:hover {
            background-color: transparent;
        }
        
        .calendar-day.unavailable {
            background-color: #f5f5f5;
            color: #ccc;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        .calendar-day.unavailable:hover {
            background-color: #f5f5f5;
        }
        
        .calendar-day.empty {
            background-color: transparent;
            cursor: default;
        }
        
        .calendar-day.empty:hover {
            background-color: transparent;
        }
        
        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-nav button {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background-color 0.2s ease;
        }
        
        .calendar-nav button:hover {
            background-color: var(--airbnb-light);
        }
        
        .calendar-nav h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--airbnb-dark);
        }
        
        /* Action Buttons */
        .calendar-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 16px;
        }
        
        .btn-secondary {
            background: white;
            color: var(--airbnb-dark);
            border: 1px solid #e6e6e6;
        }
        
        .btn-secondary:hover {
            border-color: #ccc;
        }
        
        .btn-primary {
            background: var(--airbnb-pink);
            color: white;
        }
        
        .btn-primary:hover {
            background: #e04e53;
        }
        
        .calendar-legend {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            font-size: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 50%;
        }
        
        /* Footer Styles */
        footer {
            border-top: 1px solid #eee;
            padding: 36px 0;
            color: var(--muted);
            background: #fafafa;
            width: 100%;
            margin-top: auto;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 32px;
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 20px;
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
        @media (max-width: 768px) {
            .date-inputs {
                flex-direction: column;
            }
            
            .calendar-container {
                padding: 20px;
            }
            
            .product-info {
                text-align: center;
            }
            
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }
        }
        
        @media (max-width: 480px) {
            .calendar-day {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
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

            <nav aria-label="Main navigation">
                <ul id="main-nav">
                    <li><a href="about.html">About</a></li>
                    <li><a href="blog.html">Blog</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                    <li><a href="catalog.php">Browse</a></li>
                </ul>
            </nav>

            <div class="icons" role="group" aria-label="User actions">
                <a href="customerdashboard.html" class="icon-only" title="Dashboard" aria-label="Dashboard">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="white" stroke-width="1.2" fill="none"/>
                        <polyline points="9 22 9 12 15 12 15 22" stroke="white" stroke-width="1.2" fill="none"/>
                    </svg>
                </a>
                
                <a href="register.html" class="icon-only" title="Login" aria-label="Login">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                        <polyline points="10 17 15 12 10 7" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="15" y1="12" x2="3" y2="12" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                    </svg>
                </a>
                
                <a href="help.html" class="icon-only" title="Help" aria-label="Help">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="white" stroke-width="1.2" fill="none"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                        <line x1="12" y1="17" x2="12" y2="17" stroke="white" stroke-width="1.2" fill="none" stroke-linecap="round"/>
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <div class="main-content">
        <div class="container">
            <div class="calendar-container">
                <div class="calendar-header">
                    <h1>Select Your Rental Dates</h1>
                    <p>Rental period is 3 days including delivery and return days</p>
                </div>
                
                
                <form method="POST" id="bookingForm">
                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                    <input type="hidden" name="size" value="<?= $selected_size ?>">
                    
                    <div class="date-inputs">
                        <div class="date-input">
                            <label for="start-date">Start Date</label>
                            <input type="text" id="start-date" name="start_date" class="date-input-field" placeholder="Select start date" readonly required>
                        </div>
                        <div class="date-input">
                            <label for="end-date">End Date</label>
                            <input type="text" id="end-date" name="end_date" class="date-input-field" placeholder="Select end date" readonly required>
                        </div>
                    </div>
                    
                    <div class="calendar-nav">
                        <button type="button" id="prev-month">&lt;</button>
                        <h2 id="current-month">June 2024</h2>
                        <button type="button" id="next-month">&gt;</button>
                    </div>
                    
                    <table class="calendar">
                        <thead>
                            <tr class="calendar-header-row">
                                <th>Su</th>
                                <th>Mo</th>
                                <th>Tu</th>
                                <th>We</th>
                                <th>Th</th>
                                <th>Fr</th>
                                <th>Sa</th>
                            </tr>
                        </thead>
                        <tbody id="calendar-body">
                            <!-- Calendar days will be generated by JavaScript -->
                        </tbody>
                    </table>
                    
                    <!-- Legend -->
                    <div class="calendar-legend">
                        <div class="legend-item">
                            <span class="legend-color" style="background: #FF5A5F;"></span>
                            <span>Start / End Date</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background: rgba(255,90,95,0.2);"></span>
                            <span>In Range</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background: #ccc;"></span>
                            <span>Unavailable Dates</span>
                        </div>
                    </div>
                    
                    <div class="calendar-actions">
                        <button type="button" class="btn btn-secondary" id="clear-dates">Clear Dates</button>
                        <button type="submit" class="btn btn-primary" id="apply-dates">Add to Cart</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-grid">
            <div>
                <h4>Ozyde Boutique</h4>
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
                    <li><a href="#">How It Works</a></li>
                    <li><a href="#">Size Guide</a></li>
                    <li><a href="#">Care Instructions</a></li>
                    <li><a href="#">Returns & Policy</a></li>
                    <li><a href="#">Delivery</a></li>
                    <li><a href="#">Help Center</a></li>
                </ul>
            </div>

            <div>
                <h4>Company</h4>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press</a></li>
                    <li><a href="#">Terms</a></li>
                    <li><a href="#">Privacy</a></li>
                </ul>
            </div>

            <div>
                <h4>Support</h4>
                <ul>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="#">Sizing Guide</a></li>
                    <li><a href="#">Cleaning</a></li>
                    <li><a href="#">Partnerships</a></li>
                    <li><a href="#">Affiliate</a></li>
                </ul>
            </div>
        </div>

        <div style="margin-top:24px;text-align:center;padding-top:24px;border-top:1px solid #e6e6e6;color:var(--muted)">
            © 2024 Ozyde Boutique. All rights reserved.
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Calendar state
            let currentDate = new Date();
            let startDate = null;
            let endDate = null;

            // Unavailable dates from PHP (only from bookings table)
            let unavailableDates = <?php echo json_encode($unavailable_dates); ?>;

            // DOM elements
            const startDateInput = document.getElementById('start-date');
            const endDateInput = document.getElementById('end-date');
            const currentMonthElement = document.getElementById('current-month');
            const calendarBody = document.getElementById('calendar-body');
            const prevMonthButton = document.getElementById('prev-month');
            const nextMonthButton = document.getElementById('next-month');
            const clearDatesButton = document.getElementById('clear-dates');
            const applyDatesButton = document.getElementById('apply-dates');

            // Format date as "Month Day, Year"
            function formatDate(date) {
                if (!date) return '';
                return date.toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            }

            // Format date as YYYY-MM-DD
            function formatDateISO(date) {
                if (!date) return '';
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            // Check if a date is unavailable (from confirmed bookings)
            function isDateUnavailable(date) {
                const dateStr = formatDateISO(date);
                return unavailableDates.includes(dateStr);
            }

            // Check if date is in the past
            function isPastDate(date) {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                return date < today;
            }

            // Generate calendar for a given month and year
            function generateCalendar(year, month) {
                calendarBody.innerHTML = '';

                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const daysInMonth = lastDay.getDate();
                const startingDay = firstDay.getDay();

                // Update month display
                const monthName = firstDay.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                currentMonthElement.textContent = monthName;

                let date = 1;
                let calendarHTML = '';

                for (let i = 0; i < 6; i++) {
                    calendarHTML += '<tr>';

                    for (let j = 0; j < 7; j++) {
                        if (i === 0 && j < startingDay) {
                            // Empty cells before the first day of the month
                            calendarHTML += '<td><div class="calendar-day empty"></div></td>';
                        } else if (date > daysInMonth) {
                            // Empty cells after the last day of the month
                            calendarHTML += '<td><div class="calendar-day empty"></div></td>';
                        } else {
                            const currentDateObj = new Date(year, month, date);
                            const isPast = isPastDate(currentDateObj);
                            const isUnavailable = isDateUnavailable(currentDateObj);
                            const dayClass = getDayClass(currentDateObj, isPast, isUnavailable);

                            calendarHTML += `<td data-date="${currentDateObj.toISOString()}">
                                <div class="calendar-day ${dayClass}">${date}</div>
                            </td>`;
                            date++;
                        }
                    }

                    calendarHTML += '</tr>';
                }

                calendarBody.innerHTML = calendarHTML;

                // Add event listeners to calendar days
                const dayElements = calendarBody.querySelectorAll('td');
                dayElements.forEach(dayElement => {
                    const dayDiv = dayElement.querySelector('.calendar-day');
                    if (!dayDiv || dayDiv.classList.contains('empty') || dayDiv.classList.contains('past-date') || dayDiv.classList.contains('unavailable')) return;

                    dayElement.addEventListener('click', function() {
                        const dateString = this.getAttribute('data-date');
                        const clickedDate = new Date(dateString);
                        handleDateClick(clickedDate);
                    });
                });
            }

            // Get CSS class for a day based on selection state
            function getDayClass(date, isPast, isUnavailable) {
                if (isUnavailable) return 'unavailable';
                if (isPast) return 'past-date';

                if (startDate && date.getTime() === startDate.getTime()) return 'start-date';
                if (endDate && date.getTime() === endDate.getTime()) return 'end-date';
                if (startDate && endDate && date > startDate && date < endDate) return 'in-range';

                return '';
            }

            // Handle date selection with 3-day rental policy
            function handleDateClick(date) {
                // Check if the selected date is unavailable (confirmed booking)
                if (isDateUnavailable(date)) {
                    alert('This date is not available due to a confirmed booking. Please select another date.');
                    return;
                }

                // Check if the next 2 days are also available (3-day rental total)
                const day2 = new Date(date);
                day2.setDate(date.getDate() + 1);
                const day3 = new Date(date);
                day3.setDate(date.getDate() + 2);

                if (isDateUnavailable(day2) || isDateUnavailable(day3)) {
                    alert('The 3-day rental period is not available due to confirmed bookings. Please select another start date.');
                    return;
                }

                startDate = date;
                endDate = day3; // 3-day rental total

                updateDateInputs();
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            }

            // Update date input fields
            function updateDateInputs() {
                startDateInput.value = formatDate(startDate);
                endDateInput.value = formatDate(endDate);
                
                // Set hidden values for form submission
                if (startDate) {
                    document.querySelector('input[name="start_date"]').value = formatDateISO(startDate);
                }
                if (endDate) {
                    document.querySelector('input[name="end_date"]').value = formatDateISO(endDate);
                }
            }

            // Clear selected dates
            function clearDates() {
                startDate = null;
                endDate = null;
                startDateInput.value = '';
                endDateInput.value = '';
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            }

            // Apply selected dates
            function applyDates(event) {
                event.preventDefault();
                
                if (!startDate || !endDate) {
                    alert('Please select a start date');
                    return;
                }

                // Show confirmation
                const confirmation = confirm(`Confirm 3-day rental:\nStart: ${formatDate(startDate)}\nEnd: ${formatDate(endDate)}`);
                
                if (confirmation) {
                    // Submit the form
                    document.getElementById('bookingForm').submit();
                }
            }

            // Navigate months
            function goToPreviousMonth() {
                currentDate.setMonth(currentDate.getMonth() - 1);
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            }

            function goToNextMonth() {
                currentDate.setMonth(currentDate.getMonth() + 1);
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            }

            // Initialize calendar
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());

            // Event listeners
            prevMonthButton.addEventListener('click', goToPreviousMonth);
            nextMonthButton.addEventListener('click', goToNextMonth);
            clearDatesButton.addEventListener('click', clearDates);
            applyDatesButton.addEventListener('click', applyDates);
        });
    </script>
</body>
</html>