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
$user_query = "SELECT u.first_name, u.last_name, u.email, u.phone, u.country_code, 
                      u.address_line1, u.address_line2, u.city, u.province, u.postal_code, u.country,
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

// Fetch ALL available dress styles
$all_styles_query = "SELECT style_id, style_name FROM dress_styles WHERE is_custom = 0 ORDER BY style_name";
$all_styles_result = $mysqli->query($all_styles_query);
$all_styles = [];
while ($row = $all_styles_result->fetch_assoc()) {
    $all_styles[] = $row;
}

// Fetch user style preferences
$styles_query = "SELECT ds.style_id, ds.style_name, ds.is_custom 
                 FROM user_style_preferences usp 
                 JOIN dress_styles ds ON usp.style_id = ds.style_id 
                 WHERE usp.user_id = ?
                 UNION
                 SELECT ucs.custom_style_id, ucs.style_name, 1 as is_custom 
                 FROM user_custom_styles ucs 
                 WHERE ucs.user_id = ?";
$styles_stmt = $mysqli->prepare($styles_query);
$styles_stmt->bind_param("ii", $user_id, $user_id);
$styles_stmt->execute();
$styles_result = $styles_stmt->get_result();
$user_styles = [];
while ($row = $styles_result->fetch_assoc()) {
    $user_styles[] = $row;
}
$styles_stmt->close();

// Fetch user size preferences
$sizes_query = "SELECT size_label FROM size_preferences WHERE user_id = ?";
$sizes_stmt = $mysqli->prepare($sizes_query);
$sizes_stmt->bind_param("i", $user_id);
$sizes_stmt->execute();
$sizes_result = $sizes_stmt->get_result();
$user_sizes = [];
while ($row = $sizes_result->fetch_assoc()) {
    $user_sizes[] = $row['size_label'];
}
$sizes_stmt->close();

// Fetch user stats
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'booked') as active_rentals,
    (SELECT COUNT(*) FROM orders WHERE user_id = ?) as total_orders,
    (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = ? AND payment_status = 'paid') as total_spent";
$stats_stmt = $mysqli->prepare($stats_query);
$stats_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$user_stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_measurements':
                $bust = !empty($_POST['bust']) ? (float)$_POST['bust'] : null;
                $waist = !empty($_POST['waist']) ? (float)$_POST['waist'] : null;
                $hips = !empty($_POST['hips']) ? (float)$_POST['hips'] : null;
                
                // Check if measurements exist
                $check_measurements = $mysqli->prepare("SELECT measurement_id FROM user_measurements WHERE user_id = ?");
                $check_measurements->bind_param("i", $user_id);
                $check_measurements->execute();
                $check_measurements->store_result();
                
                if ($check_measurements->num_rows > 0) {
                    // Update existing
                    $update_stmt = $mysqli->prepare("UPDATE user_measurements SET bust = ?, waist = ?, hips = ? WHERE user_id = ?");
                    $update_stmt->bind_param("dddi", $bust, $waist, $hips, $user_id);
                    $update_stmt->execute();
                } else {
                    // Insert new
                    $insert_stmt = $mysqli->prepare("INSERT INTO user_measurements (user_id, bust, waist, hips) VALUES (?, ?, ?, ?)");
                    $insert_stmt->bind_param("iddd", $user_id, $bust, $waist, $hips);
                    $insert_stmt->execute();
                }
                break;
                
            case 'update_contact':
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $address_line1 = $_POST['address_line1'];
                $address_line2 = $_POST['address_line2'];
                $city = $_POST['city'];
                $province = $_POST['province'];
                $postal_code = $_POST['postal_code'];
                $country = $_POST['country'];
                
                $update_contact = $mysqli->prepare("UPDATE users SET email = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, province = ?, postal_code = ?, country = ? WHERE user_id = ?");
                $update_contact->bind_param("ssssssssi", $email, $phone, $address_line1, $address_line2, $city, $province, $postal_code, $country, $user_id);
                $update_contact->execute();
                break;
                
            case 'add_style':
                $style_name = trim($_POST['style_name']);
                if (!empty($style_name)) {
                    // Check if it's a predefined style
                    $check_style = $mysqli->prepare("SELECT style_id FROM dress_styles WHERE style_name = ? AND is_custom = 0");
                    $check_style->bind_param("s", $style_name);
                    $check_style->execute();
                    $check_style->store_result();
                    
                    if ($check_style->num_rows > 0) {
                        $check_style->bind_result($style_id);
                        $check_style->fetch();
                        // Add to user preferences
                        $add_style = $mysqli->prepare("INSERT IGNORE INTO user_style_preferences (user_id, style_id) VALUES (?, ?)");
                        $add_style->bind_param("ii", $user_id, $style_id);
                        $add_style->execute();
                    } else {
                        // Add as custom style
                        $add_custom = $mysqli->prepare("INSERT INTO user_custom_styles (user_id, style_name) VALUES (?, ?)");
                        $add_custom->bind_param("is", $user_id, $style_name);
                        $add_custom->execute();
                    }
                }
                break;

            case 'add_predefined_style':
                $style_id = (int)$_POST['style_id'];
                if ($style_id > 0) {
                    $add_style = $mysqli->prepare("INSERT IGNORE INTO user_style_preferences (user_id, style_id) VALUES (?, ?)");
                    $add_style->bind_param("ii", $user_id, $style_id);
                    $add_style->execute();
                }
                break;
                
            case 'remove_style':
                $style_id = (int)$_POST['style_id'];
                $is_custom = (int)$_POST['is_custom'];
                
                if ($is_custom) {
                    $remove_stmt = $mysqli->prepare("DELETE FROM user_custom_styles WHERE custom_style_id = ? AND user_id = ?");
                    $remove_stmt->bind_param("ii", $style_id, $user_id);
                } else {
                    $remove_stmt = $mysqli->prepare("DELETE FROM user_style_preferences WHERE user_id = ? AND style_id = ?");
                    $remove_stmt->bind_param("ii", $user_id, $style_id);
                }
                $remove_stmt->execute();
                break;
                
            case 'update_sizes':
                // Clear existing sizes
                $clear_sizes = $mysqli->prepare("DELETE FROM size_preferences WHERE user_id = ?");
                $clear_sizes->bind_param("i", $user_id);
                $clear_sizes->execute();
                
                // Add new sizes
                if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
                    $insert_size = $mysqli->prepare("INSERT INTO size_preferences (user_id, size_label) VALUES (?, ?)");
                    foreach ($_POST['sizes'] as $size) {
                        $insert_size->bind_param("is", $user_id, $size);
                        $insert_size->execute();
                    }
                }
                break;
        }
        
        // Refresh data after update
        header("Location: customerdashboard.php");
        exit;
    }
}

// Helper function to escape output
function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
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
        
        /* Expanded Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 60px 0;
            margin-bottom: 40px;
            width: 100%;
        }
        
        .welcome-content {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 20px;
            text-align: center;
        }
        
        .welcome-content h1 {
            margin: 0 0 16px 0;
            font-size: 42px;
            font-weight: 800;
            color: var(--accent);
            line-height: 1.2;
        }
        
        .welcome-content p {
            margin: 0;
            color: var(--muted);
            font-size: 20px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Dashboard Content Container */
        .dashboard-content {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), #333);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-info h3 {
            margin: 0 0 4px 0;
            font-size: 24px;
            font-weight: 800;
            color: var(--accent);
        }
        
        .stat-info p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }
        
        /* Section Titles */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 20px 0;
            color: var(--accent);
        }
        
        /* Quick Actions Grid */
        .quick-highlights {
            margin: 20px 0;
        }
        
        .quick-highlights h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 16px 0;
            color: var(--accent);
        }
        
        .highlights-grid {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .highlight-card {
            flex: 1;
            min-width: 140px;
            background: #f3f3f3;
            padding: 16px;
            text-align: center;
            border-radius: 12px;
            text-decoration: none;
            color: #111;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            border: 1px solid #e6e6e6;
        }
        
        .highlight-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            background: #fff;
        }
        
        /* Activity List */
        .activity-list {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 24px;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--chip-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .activity-content p {
            margin: 0 0 4px 0;
            color: var(--muted);
            font-size: 14px;
        }
        
        .activity-time {
            font-size: 12px;
            color: var(--muted);
        }
        
        .activity-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .activity-status.delivered {
            background: #e8f5e8;
            color: var(--success);
        }
        
        .activity-status.paid {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .activity-status.confirmed {
            background: #fff3e0;
            color: var(--warning);
        }
        
        .view-all-activity {
            text-align: center;
            padding: 16px;
        }
        
        .view-all-btn {
            padding: 10px 24px;
            border-radius: 8px;
            border: 1px solid #e6e6e6;
            background: #fff;
            color: var(--accent);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .view-all-btn:hover {
            background: var(--accent);
            color: #fff;
        }
        
        /* Recommendations Grid */
        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
        }
        
        .recommendation-card {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .recommendation-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
        }
        
        .recommendation-image {
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .recommendation-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .recommendation-content {
            padding: 20px;
        }
        
        .recommendation-content h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .recommendation-content p {
            margin: 0 0 12px 0;
            color: var(--muted);
            font-size: 14px;
        }
        
        .recommendation-price {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 16px;
        }
        
        .rent-now-btn {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 0;
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .rent-now-btn:hover {
            background: #333;
            transform: translateY(-1px);
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
        
        /* Profile Preferences Styles */
        .profile-preferences-section {
            margin-bottom: 32px;
        }

        .preferences-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .preference-card {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .preference-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .preference-card h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
        }

        .preference-subtitle {
            margin: 0 0 20px 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        /* Measurements Styles */
        .measurements-display {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .measurement-item {
            text-align: center;
            padding: 16px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .measurement-item:hover {
            border-color: #e6e6e6;
        }

        .measurement-item label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .measurement-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 4px;
        }

        .measurement-unit {
            font-size: 12px;
            color: var(--muted);
        }

        .measurements-form {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
        }

        .form-field label {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 6px;
            font-weight: 600;
        }

        .form-field input,
        .form-field textarea,
        .form-field select {
            padding: 10px 12px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }

        .form-field input:focus,
        .form-field textarea:focus,
        .form-field select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(17, 17, 17, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* Button Styles */
        .edit-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            background: #fff;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .edit-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-primary {
            padding: 10px 16px;
            border: 0;
            border-radius: 6px;
            background: var(--accent);
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #333;
            transform: translateY(-1px);
        }

        .btn-secondary {
            padding: 10px 16px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            background: #fff;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Style Preferences */
        .style-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
            min-height: 40px;
        }

        .style-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 20px;
            background: var(--chip-bg);
            border: 1px solid var(--chip-border);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .style-chip:hover {
            border-color: #ccc;
        }

        .style-chip.selected {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .style-chip .remove {
            background: transparent;
            border: 0;
            color: inherit;
            cursor: pointer;
            padding: 2px;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            line-height: 1;
        }

        .style-chip .remove:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .add-style-section {
            margin-bottom: 20px;
        }

        .add-style-form {
            display: flex;
            gap: 8px;
        }

        .add-style-form input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            font-size: 14px;
        }

        .style-actions {
            text-align: right;
        }

        /* Contact Information */
        .contact-info {
            margin-bottom: 20px;
        }

        .contact-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-item label {
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            min-width: 60px;
        }

        .contact-value {
            flex: 1;
            text-align: right;
            color: var(--accent);
            font-weight: 500;
        }

        .contact-form {
            margin-bottom: 20px;
        }

        .contact-form .form-field {
            margin-bottom: 16px;
        }

        /* Size Preferences */
        .size-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .size-option {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .size-option.selected {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .size-option input {
            display: none;
        }

        /* Style Selection Grid */
        .style-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
            margin-bottom: 16px;
        }

        .style-selection-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 12px;
            border-radius: 20px;
            background: var(--chip-bg);
            border: 1px solid var(--chip-border);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .style-selection-chip:hover {
            border-color: #ccc;
        }

        .style-selection-chip.selected {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        /* Responsive Design */
        @media (max-width: 880px) {
            .search {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }
            
            .highlights-grid {
                flex-direction: column;
            }
            
            .highlight-card {
                min-width: auto;
            }
            
            .welcome-content h1 {
                font-size: 32px;
            }
            
            .welcome-content p {
                font-size: 18px;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
            }
            
            .preferences-grid {
                grid-template-columns: 1fr;
            }
            
            .measurements-display {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .welcome-content h1 {
                font-size: 28px;
            }
            
            .welcome-content p {
                font-size: 16px;
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
        <!-- Expanded Welcome Banner -->
        <section class="welcome-banner">
            <div class="welcome-content">
                <h1>Welcome back, <?php echo esc($user_data['first_name'] ?? 'User'); ?>!</h1>
                <p>Ready to find your perfect dress for your next special occasion?</p>
            </div>
        </section>

        <div class="dashboard-content">
            <!-- Stats Section -->
            <section class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3><?php echo $user_stats['active_rentals'] ?? 0; ?></h3>
                            <p>Active Rentals</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3><?php echo $user_stats['total_orders'] ?? 0; ?></h3>
                            <p>Total Orders</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3>R<?php echo number_format($user_stats['total_spent'] ?? 0, 0); ?></h3>
                            <p>Total Spent</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"></div>
                        <div class="stat-info">
                            <h3>4.8</h3>
                            <p>Average Rating</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Quick Highlights Section -->
            <section class="quick-highlights">
                <h2>Quick Highlights</h2>
                <div class="highlights-grid">
                    <a href="orders.html" class="highlight-card">My Rentals</a>
                    <a href="orders.html" class="highlight-card">Orders</a>
                    <a href="wishlist.php" class="highlight-card">Wishlist</a>
                    <a href="custom_orders.php" class="highlight-card">Custom Orders</a>
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
                                    <?php echo !empty($user_data['bust']) ? esc($user_data['bust']) : '-'; ?>
                                </div>
                                <span class="measurement-unit">cm</span>
                            </div>
                            <div class="measurement-item">
                                <label>Waist</label>
                                <div class="measurement-value" id="waistDisplay">
                                    <?php echo !empty($user_data['waist']) ? esc($user_data['waist']) : '-'; ?>
                                </div>
                                <span class="measurement-unit">cm</span>
                            </div>
                            <div class="measurement-item">
                                <label>Hip</label>
                                <div class="measurement-value" id="hipDisplay">
                                    <?php echo !empty($user_data['hips']) ? esc($user_data['hips']) : '-'; ?>
                                </div>
                                <span class="measurement-unit">cm</span>
                            </div>
                        </div>

                        <form method="POST" class="measurements-form" id="measurementsForm" style="display:none;">
                            <input type="hidden" name="action" value="update_measurements">
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="bustInput">Bust (cm)</label>
                                    <input type="number" id="bustInput" name="bust" placeholder="e.g. 86" step="0.1" 
                                           value="<?php echo esc($user_data['bust'] ?? ''); ?>">
                                </div>
                                <div class="form-field">
                                    <label for="waistInput">Waist (cm)</label>
                                    <input type="number" id="waistInput" name="waist" placeholder="e.g. 68" step="0.1"
                                           value="<?php echo esc($user_data['waist'] ?? ''); ?>">
                                </div>
                                <div class="form-field">
                                    <label for="hipInput">Hip (cm)</label>
                                    <input type="number" id="hipInput" name="hips" placeholder="e.g. 94" step="0.1"
                                           value="<?php echo esc($user_data['hips'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn-secondary" id="cancelMeasurements">Cancel</button>
                                <button type="submit" class="btn-primary">Save Measurements</button>
                            </div>
                        </form>

                        <button class="edit-btn" id="editMeasurements">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Edit Measurements
                        </button>
                    </div>

                    <!-- Size Preferences Card -->
                    <div class="preference-card sizes-card">
                        <h3>Size Preferences</h3>
                        <p class="preference-subtitle">Select the sizes you're interested in for personalized recommendations</p>
                        
                        <form method="POST" id="sizesForm">
                            <input type="hidden" name="action" value="update_sizes">
                            <div class="size-options">
                                <?php
                                $all_sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
                                foreach ($all_sizes as $size):
                                    $is_selected = in_array($size, $user_sizes);
                                ?>
                                <label class="size-option <?php echo $is_selected ? 'selected' : ''; ?>">
                                    <input type="checkbox" name="sizes[]" value="<?php echo esc($size); ?>" 
                                           <?php echo $is_selected ? 'checked' : ''; ?>>
                                    <?php echo esc($size); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="style-actions">
                                <button type="submit" class="btn-primary">Save Size Preferences</button>
                            </div>
                        </form>
                    </div>

                    <!-- Style Preferences Card -->
                    <div class="preference-card styles-card">
                        <h3>Style Preferences</h3>
                        <p class="preference-subtitle">Select your preferred dress styles for personalized recommendations</p>
                        
                        <!-- User's Selected Styles -->
                        <div class="style-chips" id="styleChips">
                            <?php foreach ($user_styles as $style): ?>
                            <div class="style-chip" data-style-id="<?php echo esc($style['style_id']); ?>" data-is-custom="<?php echo esc($style['is_custom']); ?>">
                                <?php echo esc($style['style_name']); ?>
                                <button type="button" class="remove" onclick="removeStyle(<?php echo esc($style['style_id']); ?>, <?php echo esc($style['is_custom']); ?>)">×</button>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Available Styles to Select -->
                        <div class="style-selection-section">
                            <h4 style="margin: 0 0 12px 0; font-size: 14px; color: var(--muted);">Select from available styles:</h4>
                            <div class="style-selection-grid">
                                <?php 
                                $user_style_ids = array_column($user_styles, 'style_id');
                                foreach ($all_styles as $style): 
                                    $is_selected = in_array($style['style_id'], $user_style_ids);
                                ?>
                                <div class="style-selection-chip <?php echo $is_selected ? 'selected' : ''; ?>" 
                                     data-style-id="<?php echo esc($style['style_id']); ?>"
                                     onclick="toggleStyle(<?php echo esc($style['style_id']); ?>, this)">
                                    <?php echo esc($style['style_name']); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Add Custom Style -->
                        <form method="POST" class="add-style-section">
                            <input type="hidden" name="action" value="add_style">
                            <div class="add-style-form">
                                <input type="text" name="style_name" placeholder="Or add custom style (e.g., Vintage, Boho)" required>
                                <button type="submit" class="btn-secondary">Add Custom</button>
                            </div>
                        </form>
                    </div>

                    <!-- Contact Info Card -->
                    <div class="preference-card contact-card">
                        <h3>Contact Information</h3>
                        <p class="preference-subtitle">Update your contact details and delivery preferences</p>
                        
                        <div class="contact-info" id="contactInfo">
                            <div class="contact-item">
                                <label>Email</label>
                                <div class="contact-value" id="emailDisplay">
                                    <?php echo esc($user_data['email'] ?? '-'); ?>
                                </div>
                            </div>
                            <div class="contact-item">
                                <label>Phone</label>
                                <div class="contact-value" id="phoneDisplay">
                                    <?php 
                                    $phone = $user_data['phone'] ?? '';
                                    $country_code = $user_data['country_code'] ?? '+27';
                                    echo $phone ? esc($country_code . ' ' . $phone) : '-';
                                    ?>
                                </div>
                            </div>
                            <div class="contact-item">
                                <label>Address</label>
                                <div class="contact-value" id="addressDisplay">
                                    <?php
                                    $address_parts = [];
                                    if (!empty($user_data['address_line1'])) $address_parts[] = $user_data['address_line1'];
                                    if (!empty($user_data['address_line2'])) $address_parts[] = $user_data['address_line2'];
                                    if (!empty($user_data['city'])) $address_parts[] = $user_data['city'];
                                    if (!empty($user_data['province'])) $address_parts[] = $user_data['province'];
                                    if (!empty($user_data['postal_code'])) $address_parts[] = $user_data['postal_code'];
                                    
                                    echo $address_parts ? esc(implode(', ', $address_parts)) : '-';
                                    ?>
                                </div>
                            </div>
                        </div>

                        <form method="POST" class="contact-form" id="contactForm" style="display:none;">
                            <input type="hidden" name="action" value="update_contact">
                            <div class="form-field">
                                <label for="emailInput">Email</label>
                                <input type="email" id="emailInput" name="email" placeholder="you@example.com" 
                                       value="<?php echo esc($user_data['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-field">
                                <label for="phoneInput">Phone</label>
                                <input type="tel" id="phoneInput" name="phone" placeholder="+27 72 000 0000"
                                       value="<?php echo esc($user_data['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-field">
                                <label for="addressInput">Address Line 1</label>
                                <input type="text" id="addressInput" name="address_line1" placeholder="Street address"
                                       value="<?php echo esc($user_data['address_line1'] ?? ''); ?>">
                            </div>
                            <div class="form-field">
                                <label for="addressInput2">Address Line 2 (Optional)</label>
                                <input type="text" id="addressInput2" name="address_line2" placeholder="Apartment, suite, etc."
                                       value="<?php echo esc($user_data['address_line2'] ?? ''); ?>">
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="cityInput">City</label>
                                    <input type="text" id="cityInput" name="city" placeholder="City"
                                           value="<?php echo esc($user_data['city'] ?? ''); ?>">
                                </div>
                                <div class="form-field">
                                    <label for="provinceInput">Province</label>
                                    <input type="text" id="provinceInput" name="province" placeholder="Province"
                                           value="<?php echo esc($user_data['province'] ?? ''); ?>">
                                </div>
                                <div class="form-field">
                                    <label for="postalCodeInput">Postal Code</label>
                                    <input type="text" id="postalCodeInput" name="postal_code" placeholder="Postal code"
                                           value="<?php echo esc($user_data['postal_code'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-field">
                                <label for="countryInput">Country</label>
                                <select id="countryInput" name="country">
                                    <option value="South Africa" <?php echo ($user_data['country'] ?? 'South Africa') === 'South Africa' ? 'selected' : ''; ?>>South Africa</option>
                                    <!-- Add other countries as needed -->
                                </select>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn-secondary" id="cancelContact">Cancel</button>
                                <button type="submit" class="btn-primary">Save Contact Info</button>
                            </div>
                        </form>

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
                    <!-- These would be dynamically generated based on user preferences -->
                    <div class="recommendation-card">
                        <div class="recommendation-image">
                            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#666;font-size:14px;font-weight:600;">Based on your style preferences</div>
                        </div>
                        <div class="recommendation-content">
                            <h4>Personalized Recommendation</h4>
                            <p>Selected just for you</p>
                            <div class="recommendation-price">From R150/3 days</div>
                            <button class="rent-now-btn" onclick="window.location.href='catalog.php'">Browse More</button>
                        </div>
                    </div>
                    <div class="recommendation-card">
                        <div class="recommendation-image">
                            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#666;font-size:14px;font-weight:600;">New Arrivals</div>
                        </div>
                        <div class="recommendation-content">
                            <h4>Latest Collection</h4>
                            <p>Fresh styles added weekly</p>
                            <div class="recommendation-price">From R120/3 days</div>
                            <button class="rent-now-btn" onclick="window.location.href='catalog.php?sort=newest'">View New Arrivals</button>
                        </div>
                    </div>
                    <div class="recommendation-card">
                        <div class="recommendation-image">
                            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#666;font-size:14px;font-weight:600;">Popular Choices</div>
                        </div>
                        <div class="recommendation-content">
                            <h4>Customer Favorites</h4>
                            <p>Highly rated by members</p>
                            <div class="recommendation-price">From R180/3 days</div>
                            <button class="rent-now-btn" onclick="window.location.href='catalog.php?sort=popular'">See Popular</button>
                        </div>
                    </div>
                </div>
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
        // Edit Measurements functionality
        document.getElementById('editMeasurements').addEventListener('click', function() {
            document.getElementById('measurementsForm').style.display = 'block';
            this.style.display = 'none';
        });

        document.getElementById('cancelMeasurements').addEventListener('click', function() {
            document.getElementById('measurementsForm').style.display = 'none';
            document.getElementById('editMeasurements').style.display = 'flex';
        });

        // Edit Contact functionality
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

        // Size selection functionality
        document.querySelectorAll('.size-option').forEach(option => {
            option.addEventListener('click', function() {
                const checkbox = this.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                this.classList.toggle('selected', checkbox.checked);
            });
        });

        // Style selection functionality
        function toggleStyle(styleId, element) {
            if (element.classList.contains('selected')) {
                // Remove style
                removeStyle(styleId, 0);
            } else {
                // Add style
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'add_predefined_style';
                form.appendChild(actionInput);
                
                const styleIdInput = document.createElement('input');
                styleIdInput.name = 'style_id';
                styleIdInput.value = styleId;
                form.appendChild(styleIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Remove style function
        function removeStyle(styleId, isCustom) {
            if (confirm('Are you sure you want to remove this style?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'remove_style';
                form.appendChild(actionInput);
                
                const styleIdInput = document.createElement('input');
                styleIdInput.name = 'style_id';
                styleIdInput.value = styleId;
                form.appendChild(styleIdInput);
                
                const isCustomInput = document.createElement('input');
                isCustomInput.name = 'is_custom';
                isCustomInput.value = isCustom;
                form.appendChild(isCustomInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

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

        // Initialize dashboard with animations
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.stat-card, .recommendation-card, .preference-card');
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

        // Auto-save sizes when changed
        document.getElementById('sizesForm').addEventListener('change', function() {
            // You could implement auto-save here or keep the save button
        });
    </script>
</body>
</html>