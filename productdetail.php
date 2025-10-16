<?php
session_start();
require 'db.php';

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
if ($product_id <= 0) die("Invalid product ID.");

// Fetch product details with proper joins
$sql = "SELECT p.*, c.category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        WHERE p.product_id = $product_id 
        LIMIT 1";
$result = $conn->query($sql);
if (!$result || $result->num_rows == 0) die("Product not found.");

$product = $result->fetch_assoc();

// Get sizes from the product's size column - FIXED PARSING
$sizes = [];
if (!empty($product['size'])) {
    // Parse the size string format "S:5,M:2,XS:3"
    $size_array = explode(',', $product['size']);
    foreach ($size_array as $size_item) {
        $size_parts = explode(':', trim($size_item));
        if (count($size_parts) >= 2) {
            $size = trim($size_parts[0]);
            $stock = (int)trim($size_parts[1]);
            if (!empty($size) && $stock > 0) {
                $sizes[] = [
                    'size' => $size,
                    'stock' => $stock
                ];
            }
        }
    }
}

// If no sizes found in the size column, use default sizes
if (empty($sizes)) {
    $default_sizes = ['XS', 'S', 'M', 'L', 'XL'];
    foreach ($default_sizes as $size) {
        $sizes[] = [
            'size' => $size,
            'stock' => 1
        ];
    }
}

// FIXED: Use rental_price for rental products, fallback to price
$display_price = !empty($product['rental_price']) ? $product['rental_price'] : $product['price'];

// Prepare variables
$name = htmlspecialchars($product['name']);
$brand = htmlspecialchars($product['brand'] ?? '');
$description = htmlspecialchars($product['description']);
$price = number_format($display_price, 2);
$color = htmlspecialchars($product['color'] ?? '');
$stock = (int)$product['stock'];

// FIXED: Better image path handling
function getProductImage($image_path, $product_name) {
    if (empty($image_path)) {
        return 'images/placeholder.png';
    }
    
    // Check if file exists in multiple possible locations
    $possible_paths = [
        $image_path,
        'uploads/' . basename($image_path),
        'gallery/' . basename($image_path),
        'images/' . basename($image_path),
        '../' . $image_path,
        '../uploads/' . basename($image_path),
        '../gallery/' . basename($image_path)
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path) && is_file($path)) {
            return $path;
        }
    }
    
    return 'images/placeholder.png';
}

$image_main = getProductImage($product['image'], $name);

// Get additional images from gallery
$additional_images = [];
$gallery_sql = "SELECT image_url FROM gallery WHERE product_id = $product_id ORDER BY display_order, is_primary DESC";
$gallery_result = $conn->query($gallery_sql);
if ($gallery_result && $gallery_result->num_rows > 0) {
    while ($row = $gallery_result->fetch_assoc()) {
        $additional_images[] = getProductImage($row['image_url'], $name);
    }
}

// If no additional images, use main image and placeholders
if (empty($additional_images)) {
    $additional_images = [
        $image_main,
        'images/placeholder.png',
        'images/placeholder.png',
        'images/placeholder.png'
    ];
} else {
    // Ensure main image is first
    array_unshift($additional_images, $image_main);
    $additional_images = array_slice($additional_images, 0, 4);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $name ?> — OZYDE</title>
  <style>
    /* Ozyde Boutique consistent styling */
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
        padding: 30px 0;
    }
    
    /* Breadcrumb Navigation */
    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 30px;
        font-size: 14px;
        color: var(--muted);
    }
    
    .breadcrumb a {
        color: var(--accent);
        transition: color 0.2s ease;
    }
    
    .breadcrumb a:hover {
        color: var(--dark-gray);
    }
    
    .separator {
        color: var(--medium-gray);
    }
    
    .breadcrumb .current {
        color: var(--muted);
    }
    
    /* Product Section */
    .product-section {
        margin-bottom: 50px;
    }
    
    .product-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
    }
    
    /* Image Section */
    .image-section {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .main-image-container {
        position: relative;
        background: var(--airbnb-light);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .main-image {
        width: 100%;
        height: 500px;
        object-fit: cover;
        display: block;
    }
    
    .thumbnail-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
    }
    
    .thumbnail {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        opacity: 0.7;
        transition: all 0.2s ease;
        border: 2px solid transparent;
    }
    
    .thumbnail:hover, .thumbnail.active {
        opacity: 1;
        border-color: var(--accent);
    }
    
    /* Product Information */
    .product-info {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }
    
    .product-header {
        border-bottom: 1px solid #e6e6e6;
        padding-bottom: 20px;
    }
    
    .product-title {
        font-size: 28px;
        font-weight: 800;
        color: var(--accent);
        margin-bottom: 5px;
    }
    
    .product-designer {
        font-size: 16px;
        color: var(--muted);
        margin-bottom: 15px;
    }
    
    .product-price {
        display: flex;
        align-items: baseline;
        gap: 5px;
    }
    
    .price {
        font-size: 24px;
        font-weight: 700;
        color: var(--accent);
    }
    
    .period {
        font-size: 14px;
        color: var(--muted);
    }
    
    /* Size Section */
    .size-section h3 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--accent);
    }
    
    .size-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .size-btn {
        padding: 12px 5px;
        border: 1px solid #e6e6e6;
        background: white;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
    }
    
    .size-btn.available:hover {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }
    
    .size-btn.available.selected {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }
    
    .size-btn.unavailable {
        background: var(--airbnb-light);
        color: var(--muted);
        cursor: not-allowed;
        opacity: 0.5;
    }
    
    .size-guide-link {
        font-size: 14px;
        color: var(--accent);
        text-decoration: underline;
    }
    
    /* Product Details */
    .product-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .detail-item h4 {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
        color: var(--accent);
    }
    
    .detail-item p {
        font-size: 14px;
        color: var(--muted);
    }
    
    /* Description Section */
    .description-section {
        margin-bottom: 25px;
    }
    
    .description-section h3 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--accent);
    }
    
    .description-content {
        line-height: 1.6;
    }
    
    .description-content p {
        margin-bottom: 15px;
        font-size: 15px;
        color: var(--text);
    }
    
    /* Rental Information */
    .rental-info {
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Product Detail - OZYDE</title>
        </head>
        <body>
            <!-- Your existing HTML content -->
        </body>
        </html>
        margin-bottom: 25px;
    }
    
    .rental-info h3 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--accent);
    }
    
    .rental-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .rental-item {
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }
    
    .rental-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--airbnb-light);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: var(--accent);
        flex-shrink: 0;
    }
    
    .rental-content h4 {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
        color: var(--accent);
    }
    
    .rental-content p {
        font-size: 13px;
        color: var(--muted);
    }
    
    /* Booking Section */
    .booking-section {
        background: var(--airbnb-light);
        padding: 20px;
        border-radius: 12px;
        text-align: center;
    }
    
    .book-now-btn {
        background: var(--accent);
        color: white;
        border: none;
        padding: 15px 30px;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
        margin-bottom: 10px;
    }
    
    .book-now-btn:hover {
        background: #333;
    }
    
    .booking-note {
        font-size: 14px;
        color: var(--muted);
    }
    
    /* Section Titles */
    .section-title {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 20px;
        color: var(--accent);
        padding-bottom: 10px;
        border-bottom: 1px solid #e6e6e6;
    }
    
    /* Care Section */
    .care-section {
        margin-bottom: 50px;
    }
    
    .care-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .care-item {
        display: flex;
        gap: 15px;
        align-items: flex-start;
    }
    
    .care-icon {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        background: var(--airbnb-light);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: var(--accent);
        flex-shrink: 0;
    }
    
    .care-content h4 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
        color: var(--accent);
    }
    
    .care-content p {
        font-size: 14px;
        color: var(--muted);
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
    }

    /* Login Popup Styles */
    .popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .popup-content {
        background: white;
        border-radius: 12px;
        padding: 0;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        animation: popupFadeIn 0.3s ease;
    }

    .popup-header {
        padding: 20px 24px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .popup-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #111;
    }

    .popup-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .popup-close:hover {
        color: #111;
    }

    .popup-body {
        padding: 20px 24px 24px;
    }

    .popup-body p {
        margin: 0 0 20px 0;
        color: #666;
        font-size: 16px;
        line-height: 1.5;
    }

    .popup-actions {
        display: flex;
        gap: 12px;
    }

    .popup-actions .btn {
        flex: 1;
        padding: 12px 16px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .btn-primary {
        background: #111;
        color: white;
    }

    .btn-primary:hover {
        background: #333;
    }

    .btn-secondary {
        background: #f5f5f5;
        color: #333;
        border: 1px solid #e6e6e6;
    }

    .btn-secondary:hover {
        background: #e8e8e8;
    }

    @keyframes popupFadeIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Backdrop blur for modern browsers */
    @supports (backdrop-filter: blur(10px)) {
        .popup-overlay {
            backdrop-filter: blur(10px);
        }
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .nav {
            flex-direction: column;
            gap: 15px;
        }
        
        nav ul {
            order: 2;
            width: 100%;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .icons {
            order: 3;
            width: 100%;
            justify-content: center;
        }
        
        .product-grid {
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        .product-details, .rental-grid {
            grid-template-columns: 1fr;
        }
        
        .size-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .care-grid {
            grid-template-columns: 1fr;
        }
        
        .footer-grid {
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .popup-actions {
            flex-direction: column;
        }
    }
    
    @media (max-width: 480px) {
        .thumbnail-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .product-title {
            font-size: 24px;
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
        <a href="finalhomepage.html" class="icon-only" title="Dashboard" aria-label="Dashboard">
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

  <main class="main-content">
    <div class="container">
      <!-- Breadcrumb Navigation -->
      <nav class="breadcrumb">
        <a href="catalog.php">Home</a>
        <span class="separator">/</span>
        <a href="catalog.php">Browse</a>
        <span class="separator">/</span>
        <span class="current"><?= $name ?></span>
      </nav>

      <!-- Product Section -->
      <section class="product-section">
        <div class="product-grid">

          <!-- Image Section -->
          <div class="image-section">
            <div class="main-image-container">
              <img src="<?= $image_main ?>" alt="<?= $name ?>" class="main-image" id="mainImage" onerror="this.src='images/placeholder.png'">
            </div>
            
            <div class="thumbnail-grid">
              <?php foreach ($additional_images as $index => $image): ?>
                <img src="<?= $image ?>" 
                     alt="<?= $name ?> view <?= $index + 1 ?>" 
                     class="thumbnail <?= $index === 0 ? 'active' : '' ?>" 
                     data-image="<?= $image ?>"
                     onerror="this.src='images/placeholder.png'">
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Product Info -->
          <div class="product-info">
            <div class="product-header">
              <h1 class="product-title"><?= $name ?></h1>
              <?php if ($brand): ?>
                <p class="product-designer">By <?= $brand ?></p>
              <?php endif; ?>
              <div class="product-price">
                <span class="price">R<?= $price ?></span>
                <span class="period">/ 3-day rental</span>
              </div>
            </div>

            <!-- Product Details -->
            <div class="product-details">
              <div class="detail-item">
                <h4>Color</h4>
                <p><?= $color ?: 'Not specified' ?></p>
              </div>
              <?php if (isset($product['category_name'])): ?>
              <div class="detail-item">
                <h4>Category</h4>
                <p><?= htmlspecialchars($product['category_name']) ?></p>
              </div>
              <?php endif; ?>
            </div>

            <!-- Size Selection -->
            <div class="size-section">
              <h3>Select Size</h3>
              <div class="size-grid">
                <?php foreach ($sizes as $size_data): ?>
                  <button class="size-btn <?= $size_data['stock'] > 0 ? 'available' : 'unavailable' ?>" 
                          data-size="<?= $size_data['size'] ?>"
                          <?= $size_data['stock'] <= 0 ? 'disabled' : '' ?>>
                    <?= $size_data['size'] ?>
                    <?php if ($size_data['stock'] <= 0): ?>
                      <br><small>(Out of stock)</small>
                    <?php endif; ?>
                  </button>
                <?php endforeach; ?>
              </div>
              <a href="#" class="size-guide-link">Size Guide</a>
            </div>

            <!-- Description Section -->
            <div class="description-section">
              <h3>Description</h3>
              <div class="description-content">
                <p><?= nl2br($description) ?></p>
              </div>
            </div>

            <!-- Rental Information -->
            <div class="rental-info">
              <h3>Rental Details</h3>
              <div class="rental-grid">
                <div class="rental-item">
                  <div class="rental-icon">D</div>
                  <div class="rental-content">
                    <h4>Delivery</h4>
                    <p>2-3 business days</p>
                  </div>
                </div>
                <div class="rental-item">
                  <div class="rental-icon">R</div>
                  <div class="rental-content">
                    <h4>Easy Returns</h4>
                    <p>Pre-paid return label</p>
                  </div>
                </div>
                <div class="rental-item">
                  <div class="rental-icon">C</div>
                  <div class="rental-content">
                    <h4>Professional Cleaning</h4>
                    <p>Included in rental price</p>
                  </div>
                </div>
                <div class="rental-item">
                  <div class="rental-icon">I</div>
                  <div class="rental-content">
                    <h4>Damage Protection</h4>
                    <p>Minor wear covered</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Book Now Button -->
            <div class="booking-section">
                <button class="book-now-btn" id="bookNowBtn">Book Now - R<?= $price ?></button>
                <p class="booking-note">Select your rental dates in the next step</p>
            </div>
          </div>
        </div>

        <!-- Care Instructions -->
        <section class="care-section">
          <h2 class="section-title">Care Instructions</h2>
          <div class="care-grid">
            <div class="care-item">
              <div class="care-icon">DC</div>
              <div class="care-content">
                <h4>Dry Clean Only</h4>
                <p>Professional cleaning required to maintain fabric quality</p>
              </div>
            </div>
            <div class="care-item">
              <div class="care-icon">H</div>
              <div class="care-content">
                <h4>Hang Carefully</h4>
                <p>Use padded hangers to prevent shoulder marks</p>
              </div>
            </div>
            <div class="care-item">
              <div class="care-icon">S</div>
              <div class="care-content">
                <h4>Steam if Wrinkled</h4>
                <p>Use low heat steam to remove wrinkles gently</p>
              </div>
            </div>
            <div class="care-item">
              <div class="care-icon">PS</div>
              <div class="care-content">
                <h4>Proper Storage</h4>
                <p>Keep in garment bag away from direct sunlight</p>
              </div>
            </div>
          </div>
        </section>
      </section>
    </div>
  </main>

  <!-- Footer -->
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

  <!-- Login Required Popup -->
  <div id="loginPopup" class="popup-overlay">
    <div class="popup-content">
        <div class="popup-header">
            <h3>Login Required</h3>
            <button class="popup-close" onclick="closeLoginPopup()">&times;</button>
        </div>
        <div class="popup-body">
            <p>You need to be logged in to book this dress.</p>
            <div class="popup-actions">
                <button class="btn btn-primary" onclick="window.location.href='register.html'">Login / Register</button>
                <button class="btn btn-secondary" onclick="closeLoginPopup()">Cancel</button>
            </div>
        </div>
    </div>
  </div>

  <script>
    // Handle image thumbnail clicks
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImage = document.getElementById('mainImage');

    thumbnails.forEach(thumbnail => {
      thumbnail.addEventListener('click', () => {
        // Update main image
        mainImage.src = thumbnail.dataset.image;
        
        // Update active state
        thumbnails.forEach(t => t.classList.remove('active'));
        thumbnail.classList.add('active');
      });
    });

    // Handle size selection
    const sizeButtons = document.querySelectorAll('.size-btn.available');
    let selectedSize = null;

    sizeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            sizeButtons.forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            selectedSize = btn.dataset.size;
        });
    });

    // Popup functions
    function showLoginPopup() {
        document.getElementById('loginPopup').style.display = 'flex';
    }

    function closeLoginPopup() {
        document.getElementById('loginPopup').style.display = 'none';
    }

    // Close popup when clicking outside
    document.getElementById('loginPopup').addEventListener('click', function(e) {
        if (e.target === this) {
            closeLoginPopup();
        }
    });

    // Close popup with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLoginPopup();
        }
    });

    // Booking button functionality
    document.getElementById('bookNowBtn').addEventListener('click', () => {
        if (!selectedSize) {
            alert('Please select a size before booking.');
            return;
        }
        
        <?php if (isset($_SESSION['user_id'])): ?>
            // User is logged in - proceed with booking
            const productId = <?= $product_id ?>;
            window.location.href = `booking.php?product_id=${productId}&size=${encodeURIComponent(selectedSize)}`;
        <?php else: ?>
            // User is not logged in - show popup
            showLoginPopup();
        <?php endif; ?>
    });
  </script>
</body>
</html>