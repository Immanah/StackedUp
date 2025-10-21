<?php
session_start();
require 'db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "You must be logged in to view this page.";
    exit;
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];
$user_name  = $_SESSION['user_name'];

// Fetch user's first name for display
$user_first_name = '';
$user_query = "SELECT first_name FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_row = $user_result->fetch_assoc()) {
    $user_first_name = $user_row['first_name'];
}
$user_stmt->close();

// Fetch the items user booked (from cart or selection)
$sql_items = "SELECT c.product_id, c.quantity, p.name, p.price
              FROM cart c
              JOIN products p ON c.product_id = p.product_id
              WHERE c.user_id = ?";
$stmt = $conn->prepare($sql_items);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$subtotal = 0;

while ($row = $result->fetch_assoc()) {
    $items[] = [
        'product_id' => $row['product_id'],
        'title' => $row['name'],
        'quantity' => $row['quantity'],
        'price' => $row['price'] * $row['quantity']
    ];
    $subtotal += $row['price'] * $row['quantity'];
}
$stmt->close();

// Calculate totals
$vat = $subtotal * 0.15; // 15% VAT
$deposit = $subtotal * 0.2; // refundable deposit
$delivery = 150; // fixed delivery fee
$returnFee = 50; // fixed return fee
$total = $subtotal + $vat + $delivery + $returnFee;

// Generate booking reference
$bookingRef = 'OZ' . rand(100000, 999999);

// Insert booking into database
$sql_booking = "INSERT INTO bookings (user_id, product_id, start_date, end_date, status)
                VALUES (?, ?, ?, ?, 'booked')";

$start_date = date('Y-m-d'); // for example today
$end_date = date('Y-m-d', strtotime('+2 days')); // 3-day rental including start/end

$stmt = $conn->prepare($sql_booking);
foreach ($items as $item) {
    $stmt->bind_param("iiss", $user_id, $item['product_id'], $start_date, $end_date);
    $stmt->execute();
}
$stmt->close();

// Send confirmation email
function sendBookingConfirmation($userEmail, $userName, $bookingRef, $items, $total) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'shafee.mmadi@gmail.com';
        $mail->Password   = 'anug yjfi iorp yhll';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('shafee.mmadi@gmail.com', 'OZYDE');
        $mail->addAddress($userEmail, $userName);

        $mail->isHTML(true);
        $mail->Subject = "Booking Confirmation — $bookingRef";
        $mail->Body    = "<h2>Booking Confirmed!</h2>
            <p>Hi $userName,</p>
            <p>Thank you for booking with OZYDE.</p>
            <p><strong>Booking Reference:</strong> $bookingRef</p>
            <ul>";
        foreach ($items as $item) {
            $mail->Body .= "<li>{$item['title']} — Quantity: {$item['quantity']} — R{$item['price']}</li>";
        }
        $mail->Body .= "</ul>
            <p><strong>Total:</strong> R$total</p>
            <p>We look forward to delivering your items on time!</p>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

sendBookingConfirmation($user_email, $user_name, $bookingRef, $items, $total);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Booking confirmed — OZYDE</title>
<style>
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

/* Header Styles */
.nav-wrap { background:#0b0b0b; color:#fff; position:sticky; top:0; z-index:120; box-shadow:0 6px 20px rgba(2,2,2,0.12); }
.nav { max-width:var(--max-width); margin:0 auto; padding:10px 18px; display:flex; align-items:center; gap:18px; justify-content:space-between; }
.logo { display:flex; gap:12px; align-items:center; font-weight:800; letter-spacing:1px; font-size:20px; cursor:pointer; margin-right: auto; }
.logo-badge { width:40px; height:40px; border-radius:8px; background:linear-gradient(135deg,#fff2,#fff6); display:flex; align-items:center; justify-content:center; color:#111; font-weight:900; font-size:16px; }
nav ul { margin:0; padding:0; display:flex; gap:18px; list-style:none; align-items:center; }
nav a { font-size:14px; color:#fff; display:block; padding:8px 6px; transition:color 0.2s ease; }
nav a:hover { color:#ddd; }

/* Search Bar */
.search { flex:1; max-width:400px; display:flex; align-items:center; gap:6px; margin:0 12px; }
.search input { width:100%; padding:10px 12px; border-radius:999px 0 0 999px; border:0; outline:0; font-size:14px; background:rgba(255,255,255,0.06); color:#fff; }
.search input::placeholder { color:#aaa; }
.search button { padding:10px 12px; border-radius:0 999px 999px 0; border:0; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; }

.icons { display:flex; gap:14px; align-items:center; }
.icon-only { display:inline-flex; width:40px; height:40px; border-radius:8px; align-items:center; justify-content:center; background:transparent; border:0; color:#fff; cursor:pointer; transition:background 0.2s ease; position:relative; }
.icon-only:hover { background:rgba(255,255,255,0.1); }

/* User Welcome */
.user-welcome {
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    margin-right: 8px;
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

/* Main Content Styles */
main { padding: 80px 0 60px; min-height: calc(100vh - 200px); display: flex; align-items: center; justify-content: center; }
.confirmation-container { max-width: 600px; width: 100%; margin: 0 auto; }

.confirmation-card { 
    background: #fff;
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    text-align: center;
}

.confirmation-header { margin-bottom: 30px; }
.confirmation-header h1 { margin: 0 0 12px 0; font-size: 32px; font-weight: 800; color: var(--accent); }
.confirmation-header p { margin: 0; color: var(--muted); font-size: 16px; }

.booking-reference { 
    background: var(--chip-bg);
    border: 1px solid var(--chip-border);
    border-radius: 8px;
    padding: 16px;
    margin: 24px 0;
    text-align: center;
}
.booking-reference .label { font-size: 14px; color: var(--muted); margin-bottom: 4px; }
.booking-reference .ref { font-size: 24px; font-weight: 800; color: var(--accent); }

.items-section { margin: 30px 0; text-align: left; }
.items-section h3 { margin: 0 0 16px 0; font-size: 18px; font-weight: 700; color: var(--accent); }

.item-list { border-radius: 8px; padding: 0; }
.item-row { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 12px 0; 
    border-bottom: 1px solid #f5f5f5; 
}
.item-row:last-child { border-bottom: none; }
.item-info .name { font-weight: 600; color: var(--accent); margin-bottom: 4px; }
.item-info .details { font-size: 14px; color: var(--muted); }
.item-price { font-weight: 700; color: var(--accent); }

.totals-section { 
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin: 24px 0;
    text-align: left;
}
.total-row { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 8px 0; 
    font-size: 14px;
}
.total-row.final { 
    border-top: 1px solid #e6e6e6; 
    margin-top: 12px; 
    padding-top: 16px;
    font-weight: 700;
    font-size: 18px;
    color: var(--accent);
}

.actions { margin-top: 30px; display: flex; gap: 12px; justify-content: center; }
.btn { 
    padding: 12px 24px; 
    border-radius: 6px; 
    font-weight: 600; 
    cursor: pointer; 
    transition: all 0.2s ease; 
    border: none; 
    font-size: 14px; 
    text-decoration: none;
    display: inline-block;
}
.btn.primary { background: var(--accent); color: #fff; }
.btn.secondary { background: #f5f5f5; color: var(--accent); border: 1px solid #e6e6e6; }
.btn:hover { opacity: 0.9; transform: translateY(-1px); }

.footer-note { 
    margin-top: 20px; 
    font-size: 14px; 
    color: var(--muted); 
    line-height: 1.5;
}

/* Footer Styles */
footer { border-top:1px solid #eee; padding:36px 0; margin-top:28px; color:var(--muted); background:#fafafa; }
.footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:32px; }
.footer-grid h4 { margin:0 0 16px 0; color:var(--accent); font-weight:600; }
.footer-grid ul { list-style:none; padding:0; margin:0; }
.footer-grid li { margin-bottom:8px; }
.footer-grid a { color:var(--muted); transition:color 0.2s ease; }
.footer-grid a:hover { color:var(--accent); }
.socials { display:flex; gap:12px; margin-top:16px; }
.socials a { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:6px; background:#f5f5f5; transition:all 0.2s ease; }
.socials a:hover { background:var(--accent); }
.socials a:hover svg path,
.socials a:hover svg circle { stroke:#fff; fill:#fff; }

/* Responsive Design */
@media (max-width: 880px) {
    .footer-grid { grid-template-columns:1fr 1fr; gap:24px; }
    .confirmation-card { padding: 30px 20px; }
    .search { order:3; max-width:100%; margin:15px 0 0 0; }
    .user-welcome { display: none; }
}

@media (max-width: 640px) {
    .nav { flex-wrap:wrap; }
    nav ul { order:2; width:100%; justify-content:center; margin-top:15px; }
    .footer-grid { grid-template-columns:1fr; }
    .actions { flex-direction: column; }
    .confirmation-header h1 { font-size: 28px; }
}
</style>
</head>

<body>
    <!-- ===== Navigation Bar ===== -->
    <header class="nav-wrap" role="banner">
        <div class="nav" role="navigation" aria-label="Main navigation">
            <div class="logo" id="brandLink">
                <div class="logo-badge" aria-hidden="true">✦</div>
                <div>OZYDE</div>
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
                    <li><a href="custommade.html">Custom Made</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="blog.html">Blog</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
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

                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- User Welcome Message -->
                <div class="user-welcome">
                    Hi, <?php echo htmlspecialchars($user_first_name); ?>
                </div>

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
                        <a href="my-account.html">My Account</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" id="logoutLink">Sign Out</a>
                    </div>
                </div>
                <?php else: ?>
                <a href="register.php" class="btn-signup">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <div class="confirmation-container">
            <div class="confirmation-card">
                <div class="confirmation-header">
                    <h1>Booking Confirmed! </h1>
                    <p>Thank you for choosing OZYDE. Your booking has been successfully processed.</p>
                </div>

                <div class="booking-reference">
                    <div class="label">Booking Reference</div>
                    <div class="ref"><?= $bookingRef ?></div>
                </div>

                <div class="items-section">
                    <h3>Your Items</h3>
                    <div class="item-list" id="itemListArea"></div>
                </div>

                <div class="totals-section">
                    <div class="total-row">
                        <span>Subtotal</span>
                        <span id="subtotal">R0.00</span>
                    </div>
                    <div class="total-row">
                        <span>VAT (15%)</span>
                        <span id="vat">R0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Refundable Deposit</span>
                        <span id="deposit">R0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Delivery Fee</span>
                        <span id="delivery">R0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Return Fee</span>
                        <span id="returnFee">R0.00</span>
                    </div>
                    <div class="total-row final">
                        <span>Total Amount</span>
                        <span id="totalVal">R0.00</span>
                    </div>
                </div>

                <div class="footer-note">
                    A confirmation email has been sent to your email address. Please keep your booking reference for any inquiries.
                </div>

                <div class="actions">
                    <a href="catalog.php" class="btn primary">Continue Shopping</a>
                    <a href="customerdashboard.php" class="btn secondary">View Dashboard</a>
                </div>
            </div>
        </div>
    </main>

    <!-- ===== Footer ===== -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h4>OZYDE</h4>
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
                        <li><a href="howitworks.html">How It Works</a></li>
                        <li><a href="sizingguide.html">Size Guide</a></li>
                        <li><a href="policypage.html">Returns & Policy</a></li>
                        <li><a href="delivery.html">Delivery</a></li>
                        <li><a href="help.html">Help Center</a></li>
                    </ul>
                </div>

                <div>
                    <h4>Company</h4>
                    <ul>
                        <li><a href="about.html">About Us</a></li>
                        <li><a href="terms.html">Terms</a></li>
                        <li><a href="privacy.html">Privacy</a></li>
                    </ul>
                </div>

                <div>
                    <h4>Support</h4>
                    <ul>
                        <li><a href="contact.html">Contact</a></li>
                        <li><a href="cleaning.html">Cleaning & Care Guide</a></li>
                        <li><a href="partnership.html">Partnerships</a></li>
                    </ul>
                </div>
            </div>

            <div style="margin-top:24px;text-align:center;padding-top:24px;border-top:1px solid #e6e6e6;color:var(--muted)">
                © 2025 OZYDE. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        const items = <?= json_encode($items) ?>;
        const subtotal = <?= $subtotal ?>;
        const vat = <?= $vat ?>;
        const deposit = <?= $deposit ?>;
        const deliveryFee = <?= $delivery ?>;
        const returnFee = <?= $returnFee ?>;
        const totalVal = <?= $total ?>;

        // Populate items
        const area = document.getElementById('itemListArea');
        area.innerHTML = '';
        items.forEach(it => {
            const row = document.createElement('div');
            row.className = 'item-row';
            row.innerHTML = `
                <div class="item-info">
                    <div class="name">${it.title}</div>
                    <div class="details">Quantity: ${it.quantity}</div>
                </div>
                <div class="item-price">R${Number(it.price).toFixed(2)}</div>
            `;
            area.appendChild(row);
        });

        // Populate totals
        document.getElementById('subtotal').textContent = 'R' + subtotal.toFixed(2);
        document.getElementById('vat').textContent = 'R' + vat.toFixed(2);
        document.getElementById('deposit').textContent = 'R' + deposit.toFixed(2);
        document.getElementById('delivery').textContent = 'R' + deliveryFee.toFixed(2);
        document.getElementById('returnFee').textContent = 'R' + returnFee.toFixed(2);
        document.getElementById('totalVal').textContent = 'R' + totalVal.toFixed(2);

        // Navigation functionality
        document.getElementById('brandLink').addEventListener('click', () => {
            window.location.href = 'finalhomepage.php';
        });

        // Search functionality
        document.getElementById('searchBtn').addEventListener('click', function() {
            const query = document.getElementById('searchInput').value.trim();
            if (!query) {
                alert('Please enter a search term');
                return;
            }
            window.location.href = `catalog.php?search=${encodeURIComponent(query)}`;
        });

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });
    </script>
</body>
</html>