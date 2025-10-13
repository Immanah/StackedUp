<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$user_stmt = $pdo->prepare("
    SELECT u.*, 
           a.address_line1, a.address_line2, a.city, a.province, a.postal_code, a.country,
           um.bust, um.waist, um.hips,
           up.preferred_sizes
    FROM users u 
    LEFT JOIN addresses a ON u.user_id = a.user_id AND a.is_default = 1
    LEFT JOIN user_measurements um ON u.user_id = um.user_id
    LEFT JOIN user_preferences up ON u.user_id = up.user_id
    WHERE u.user_id = ?
");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch user's style preferences
$styles_stmt = $pdo->prepare("
    SELECT ds.style_id, ds.style_name, ds.is_custom 
    FROM dress_styles ds 
    JOIN user_style_preferences usp ON ds.style_id = usp.style_id 
    WHERE usp.user_id = ?
");
$styles_stmt->execute([$user_id]);
$user_styles = $styles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all available dress styles for selection
$all_styles_stmt = $pdo->prepare("SELECT * FROM dress_styles WHERE is_custom = 0 ORDER BY style_name");
$all_styles_stmt->execute();
$all_styles = $all_styles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Format address for display
$full_address = '';
if ($user['address_line1']) {
    $full_address = $user['address_line1'];
    if ($user['address_line2']) $full_address .= ', ' . $user['address_line2'];
    if ($user['city']) $full_address .= ', ' . $user['city'];
    if ($user['province']) $full_address .= ', ' . $user['province'];
    if ($user['postal_code']) $full_address .= ', ' . $user['postal_code'];
    if ($user['country']) $full_address .= ', ' . $user['country'];
}

// Get user stats (you'll need to implement these based on your orders/rentals)
$active_rentals = 0;
$total_orders = 0;
$total_spent = 0;
$average_rating = 0;

// You'll need to implement these queries based on your business logic
/*
$stats_stmt = $pdo->prepare("
    -- Your stats queries here
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
*/
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Dashboard — OZYDE Boutique</title>

    <style>
        /* Your existing CSS styles remain exactly the same */
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
        
        /* ... (ALL your existing CSS styles remain exactly the same) ... */
        
        /* Add loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .success-message {
            background: var(--success);
            color: white;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 16px;
            text-align: center;
            display: none;
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
                    <li><a href="about.php">About</a></li>
                    <li><a href="blog.php">Blog</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="custommade.php">Custom Made</a></li>
                    <li><a href="catalog.php">Browse</a></li>
                </ul>
            </nav>

            <div class="icons" role="group" aria-label="User actions">
                <!-- Help Button -->
                <a href="help.php" class="icon-only" title="Help" aria-label="Help">
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
                        <a href="customer-dashboard.php">Customer Dashboard</a>
                        <a href="my-account.php">My Account</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" id="logoutLink">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ===== Main Dashboard Content ===== -->
    <main>
        <!-- Expanded Welcome Banner -->
        <section class="welcome-banner">
            <div class="welcome-content">
                <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                <p>Ready to find your perfect dress for your next special occasion?</p>
            </div>
        </section>

        <div class="dashboard-content">
            <!-- Success Message Area -->
            <div class="success-message" id="successMessage"></div>

            <!-- Stats Section -->
            <section class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3><?php echo $active_rentals; ?></h3>
                            <p>Active Rentals</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3><?php echo $total_orders; ?></h3>
                            <p>Total Orders</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3>R<?php echo number_format($total_spent, 0); ?></h3>
                            <p>Total Spent</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3><?php echo $average_rating; ?></h3>
                            <p>Average Rating</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Quick Highlights Section -->
            <section class="quick-highlights">
                <h2>Quick Highlights</h2>
                <div class="highlights-grid">
                    <a href="orders.php" class="highlight-card">My Rentals</a>
                    <a href="orders.php" class="highlight-card">Orders</a>
                </div>
            </section>

            <!-- Profile Preferences Section -->
            <section class="profile-preferences-section">
                <h2 class="section-title">Profile & Preferences</h2>
                <div class="preferences-grid">
                    <!-- Measurements Card -->
                    <div class="preference-card measurements-card">
                        <h3>Your Measurements</h3>
                        <p class="preference-subtitle">Keep your measurements updated for better fitting recommendations</p>
                        
                        <div class="measurements-display">
                            <div class="measurement-item">
                                <label>Bust</label>
                                <div class="measurement-value" id="bustDisplay">
                                    <?php echo $user['bust'] ? htmlspecialchars($user['bust']) : '-'; ?>
                                </div>
                                <span class="measurement-unit">cm</span>
                            </div>
                            <div class="measurement-item">
                                <label>Waist</label>
                                <div class="measurement-value" id="waistDisplay">
                                    <?php echo $user['waist'] ? htmlspecialchars($user['waist']) : '-'; ?>
                                </div>
                                <span class="measurement-unit">cm</span>
                            </div>
                            <div class="measurement-item">
                                <label>Hip</label>
                                <div class="measurement-value" id="hipDisplay">
                                    <?php echo $user['hips'] ? htmlspecialchars($user['hips']) : '-'; ?>
                                </div>
                                <span class="measurement-unit">cm</span>
                            </div>
                        </div>

                        <div class="measurements-form" id="measurementsForm" style="display:none;">
                            <form id="measurementsFormData">
                                <div class="form-row">
                                    <div class="form-field">
                                        <label for="bustInput">Bust (cm)</label>
                                        <input type="number" id="bustInput" name="bust" 
                                               value="<?php echo $user['bust'] ? htmlspecialchars($user['bust']) : ''; ?>" 
                                               placeholder="e.g. 86" min="60" max="150" step="0.1">
                                    </div>
                                    <div class="form-field">
                                        <label for="waistInput">Waist (cm)</label>
                                        <input type="number" id="waistInput" name="waist" 
                                               value="<?php echo $user['waist'] ? htmlspecialchars($user['waist']) : ''; ?>" 
                                               placeholder="e.g. 68" min="50" max="120" step="0.1">
                                    </div>
                                    <div class="form-field">
                                        <label for="hipInput">Hip (cm)</label>
                                        <input type="number" id="hipInput" name="hips" 
                                               value="<?php echo $user['hips'] ? htmlspecialchars($user['hips']) : ''; ?>" 
                                               placeholder="e.g. 94" min="70" max="160" step="0.1">
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn-secondary" id="cancelMeasurements">Cancel</button>
                                    <button type="submit" class="btn-primary" id="saveMeasurements">Save Measurements</button>
                                </div>
                            </form>
                        </div>

                        <button class="edit-btn" id="editMeasurements">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Edit Measurements
                        </button>
                    </div>

                    <!-- Style Preferences Card -->
                    <div class="preference-card styles-card">
                        <h3>Style Preferences</h3>
                        <p class="preference-subtitle">Select your preferred dress styles for personalized recommendations</p>
                        
                        <div class="style-chips" id="styleChips">
                            <?php foreach ($user_styles as $style): ?>
                                <div class="style-chip selected" data-style-id="<?php echo $style['style_id']; ?>">
                                    <?php echo htmlspecialchars($style['style_name']); ?>
                                    <button type="button" class="remove" onclick="removeStyle(<?php echo $style['style_id']; ?>)">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="add-style-section">
                            <div class="add-style-form">
                                <select id="styleSelect" class="form-field">
                                    <option value="">Select a style...</option>
                                    <?php foreach ($all_styles as $style): ?>
                                        <option value="<?php echo $style['style_id']; ?>">
                                            <?php echo htmlspecialchars($style['style_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn-secondary" id="addStyleBtn">Add Style</button>
                            </div>
                            <div style="margin-top: 10px; font-size: 12px; color: var(--muted);">
                                Or add custom style:
                            </div>
                            <div class="add-style-form" style="margin-top: 8px;">
                                <input type="text" id="customStyleInput" placeholder="Enter custom style (e.g., Vintage, Boho)">
                                <button type="button" class="btn-secondary" id="addCustomStyleBtn">Add Custom</button>
                            </div>
                        </div>

                        <div class="style-actions">
                            <button class="btn-primary" id="saveStyles">Save Preferences</button>
                        </div>
                    </div>

                    <!-- Contact Info Card -->
                    <div class="preference-card contact-card">
                        <h3>Contact Information</h3>
                        <p class="preference-subtitle">Update your contact details and delivery preferences</p>
                        
                        <div class="contact-info" id="contactInfo">
                            <div class="contact-item">
                                <label>Email</label>
                                <div class="contact-value" id="emailDisplay">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                            </div>
                            <div class="contact-item">
                                <label>Phone</label>
                                <div class="contact-value" id="phoneDisplay">
                                    <?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '-'; ?>
                                </div>
                            </div>
                            <div class="contact-item">
                                <label>Address</label>
                                <div class="contact-value" id="addressDisplay">
                                    <?php echo $full_address ? htmlspecialchars($full_address) : '-'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="contact-form" id="contactForm" style="display:none;">
                            <form id="contactFormData">
                                <div class="form-field">
                                    <label for="emailInput">Email</label>
                                    <input type="email" id="emailInput" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                                           placeholder="you@example.com" required>
                                </div>
                                <div class="form-field">
                                    <label for="phoneInput">Phone</label>
                                    <input type="tel" id="phoneInput" name="phone" 
                                           value="<?php echo $user['phone'] ? htmlspecialchars($user['phone']) : ''; ?>" 
                                           placeholder="+27 72 000 0000">
                                </div>
                                <div class="form-field">
                                    <label for="address_line1">Address Line 1</label>
                                    <input type="text" id="address_line1" name="address_line1" 
                                           value="<?php echo $user['address_line1'] ? htmlspecialchars($user['address_line1']) : ''; ?>" 
                                           placeholder="Street address" required>
                                </div>
                                <div class="form-field">
                                    <label for="address_line2">Address Line 2 (Optional)</label>
                                    <input type="text" id="address_line2" name="address_line2" 
                                           value="<?php echo $user['address_line2'] ? htmlspecialchars($user['address_line2']) : ''; ?>" 
                                           placeholder="Apartment, suite, unit, etc.">
                                </div>
                                <div class="form-row">
                                    <div class="form-field">
                                        <label for="city">City</label>
                                        <input type="text" id="city" name="city" 
                                               value="<?php echo $user['city'] ? htmlspecialchars($user['city']) : ''; ?>" 
                                               placeholder="City" required>
                                    </div>
                                    <div class="form-field">
                                        <label for="province">Province</label>
                                        <input type="text" id="province" name="province" 
                                               value="<?php echo $user['province'] ? htmlspecialchars($user['province']) : ''; ?>" 
                                               placeholder="Province" required>
                                    </div>
                                    <div class="form-field">
                                        <label for="postal_code">Postal Code</label>
                                        <input type="text" id="postal_code" name="postal_code" 
                                               value="<?php echo $user['postal_code'] ? htmlspecialchars($user['postal_code']) : ''; ?>" 
                                               placeholder="Postal code" required>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn-secondary" id="cancelContact">Cancel</button>
                                    <button type="submit" class="btn-primary" id="saveContact">Save Contact Info</button>
                                </div>
                            </form>
                        </div>

                        <button class="edit-btn" id="editContact">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Edit Contact Info
                        </button>
                    </div>
                </div>
            </section>

            <!-- Recommendations Section -->
            <section class="recommendations-section">
                <h2 class="section-title">Recommended for You</h2>
                <div class="recommendations-grid">
                    <!-- This would be populated with real recommendations based on user preferences -->
                    <div class="recommendation-card">
                        <div class="recommendation-image">
                            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#666;font-size:14px;font-weight:600;">Elegant Black Gown</div>
                        </div>
                        <div class="recommendation-content">
                            <h4>Elegant Black Gown</h4>
                            <p>By Chanel</p>
                            <div class="recommendation-price">R180/3 days</div>
                            <button class="rent-now-btn">Rent Now</button>
                        </div>
                    </div>
                    <!-- Add more recommendation cards -->
                </div>
            </section>
        </div>
    </main>

    <!-- ===== Footer ===== -->
    <footer>
        <!-- Your existing footer remains the same -->
        <div class="container">
            <!-- ... footer content ... -->
        </div>
    </footer>

    <!-- ===== JavaScript ===== -->
    <script>
        // User ID for API calls
        const userId = <?php echo $user_id; ?>;

        // Show success message
        function showSuccess(message) {
            const successEl = document.getElementById('successMessage');
            successEl.textContent = message;
            successEl.style.display = 'block';
            setTimeout(() => {
                successEl.style.display = 'none';
            }, 3000);
        }

        // Measurements functionality
        document.getElementById('editMeasurements').addEventListener('click', function() {
            document.getElementById('measurementsForm').style.display = 'block';
            this.style.display = 'none';
        });

        document.getElementById('cancelMeasurements').addEventListener('click', function() {
            document.getElementById('measurementsForm').style.display = 'none';
            document.getElementById('editMeasurements').style.display = 'flex';
        });

        // Save measurements
        document.getElementById('measurementsFormData').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'save_measurements');
            formData.append('user_id', userId);

            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update display values
                    document.getElementById('bustDisplay').textContent = formData.get('bust') || '-';
                    document.getElementById('waistDisplay').textContent = formData.get('waist') || '-';
                    document.getElementById('hipDisplay').textContent = formData.get('hips') || '-';
                    
                    document.getElementById('measurementsForm').style.display = 'none';
                    document.getElementById('editMeasurements').style.display = 'flex';
                    showSuccess('Measurements updated successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving measurements.');
            });
        });

        // Style preferences functionality
        document.getElementById('addStyleBtn').addEventListener('click', function() {
            const styleSelect = document.getElementById('styleSelect');
            const styleId = styleSelect.value;
            const styleName = styleSelect.options[styleSelect.selectedIndex].text;
            
            if (styleId) {
                addStyleToSelection(styleId, styleName, false);
                styleSelect.value = '';
            }
        });

        document.getElementById('addCustomStyleBtn').addEventListener('click', function() {
            const customInput = document.getElementById('customStyleInput');
            const styleName = customInput.value.trim();
            
            if (styleName) {
                // For custom styles, we'll add them directly without an ID initially
                addStyleToSelection(null, styleName, true);
                customInput.value = '';
            }
        });

        function addStyleToSelection(styleId, styleName, isCustom) {
            // Check if already added
            const existing = document.querySelector(`[data-style-id="${styleId}"]`);
            if (existing) return;

            const chip = document.createElement('div');
            chip.className = 'style-chip selected';
            chip.setAttribute('data-style-id', styleId || 'custom');
            chip.innerHTML = `
                ${styleName}
                <button type="button" class="remove" onclick="removeStyle('${styleId || 'custom'}')">×</button>
            `;
            
            document.getElementById('styleChips').appendChild(chip);
        }

        function removeStyle(styleId) {
            const chip = document.querySelector(`[data-style-id="${styleId}"]`);
            if (chip) {
                chip.remove();
            }
        }

        // Save style preferences
        document.getElementById('saveStyles').addEventListener('click', function() {
            const styleChips = document.querySelectorAll('#styleChips .style-chip');
            const styles = Array.from(styleChips).map(chip => ({
                styleId: chip.getAttribute('data-style-id'),
                styleName: chip.textContent.replace('×', '').trim()
            }));

            const formData = new FormData();
            formData.append('action', 'save_styles');
            formData.append('user_id', userId);
            formData.append('styles', JSON.stringify(styles));

            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Style preferences updated successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving style preferences.');
            });
        });

        // Contact information functionality
        document.getElementById('editContact').addEventListener('click', function() {
            document.getElementById('contactForm').style.display = 'block';
            document.getElementById('contactInfo').style.display = 'none';
            this.style.display = 'none';
        });

        document.getElementById('cancelContact').addEventListener('click', function() {
            document.getElementById('contactForm').style.display = 'none';
            document.getElementById('contactInfo').style.display = 'block';
            document.getElementById('editContact').style.display = 'flex';
        });

        // Save contact information
        document.getElementById('contactFormData').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'save_contact');
            formData.append('user_id', userId);

            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update display values
                    document.getElementById('emailDisplay').textContent = formData.get('email');
                    document.getElementById('phoneDisplay').textContent = formData.get('phone') || '-';
                    
                    // Update address display (you might want to format this better)
                    const addressParts = [
                        formData.get('address_line1'),
                        formData.get('address_line2'),
                        formData.get('city'),
                        formData.get('province'),
                        formData.get('postal_code'),
                        'South Africa'
                    ].filter(part => part).join(', ');
                    
                    document.getElementById('addressDisplay').textContent = addressParts;
                    
                    document.getElementById('contactForm').style.display = 'none';
                    document.getElementById('contactInfo').style.display = 'block';
                    document.getElementById('editContact').style.display = 'flex';
                    showSuccess('Contact information updated successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving contact information.');
            });
        });

        // Your existing JavaScript functionality
        document.addEventListener('DOMContentLoaded', () => {
            // ... your existing DOMContentLoaded code ...
        });
    </script>
</body>
</html>