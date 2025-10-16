<?php
session_start();
require 'db.php';

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user is admin by querying the database
$user_id = $_SESSION['user_id'];
$check_admin = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$check_admin->bind_param("i", $user_id);
$check_admin->execute();
$check_admin->bind_result($role);
$check_admin->fetch();
$check_admin->close();

if ($role !== 'admin') {
    header("Location: access_denied.php");
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission with DUPLICATE PROTECTION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if form was already submitted (basic duplicate protection)
    if (isset($_SESSION['last_product_submit']) && (time() - $_SESSION['last_product_submit']) < 5) {
        $error_message = "Please wait a few seconds before adding another product.";
    } else {
        $_SESSION['last_product_submit'] = time();
        
        try {
            $category_ids = isset($_POST['category_id']) ? (array)$_POST['category_id'] : [];
            $name = trim($_POST['name']);
            $brand = trim($_POST['brand'] ?? '');
            $description = trim($_POST['description']);
            $color = trim($_POST['color']);
            $rental_price = floatval($_POST['rental_price']);
            $security_deposit = 800.00;
            $is_rental = 1;
            
            // Check for duplicate product
            $check_duplicate = $conn->prepare("SELECT product_id FROM products WHERE name = ? AND color = ? AND rental_price = ?");
            $check_duplicate->bind_param("ssd", $name, $color, $rental_price);
            $check_duplicate->execute();
            $check_duplicate->store_result();
            
            if ($check_duplicate->num_rows > 0) {
                $error_message = "A product with the same name, color, and price already exists!";
                $check_duplicate->close();
            } else {
                $check_duplicate->close();
                
                // Handle sizes and stock
                $sizes_data = [];
                if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
                    foreach ($_POST['sizes'] as $index => $size) {
                        $size = trim($size);
                        if (!empty($size) && isset($_POST['stock'][$index])) {
                            $stock = intval($_POST['stock'][$index]);
                            if ($stock > 0) {
                                $sizes_data[] = [
                                    'size' => $size,
                                    'stock' => $stock
                                ];
                            }
                        }
                    }
                }
                
                if (empty($sizes_data)) {
                    $error_message = "Please add at least one size with stock quantity.";
                } else {
                    // Convert sizes data to string format
                    $size_string = '';
                    foreach ($sizes_data as $size_data) {
                        if (!empty($size_string)) $size_string .= ',';
                        $size_string .= $size_data['size'] . ':' . $size_data['stock'];
                    }
                    
                    // Calculate total stock
                    $total_stock = array_sum(array_column($sizes_data, 'stock'));
                    
                    // FIXED: Handle main image upload - SIMPLIFIED AND MORE RELIABLE
                    $image_path = '';
                    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
                        $upload_dir = 'gallery/';
                        
                        // Create gallery directory if it doesn't exist
                        if (!is_dir($upload_dir)) {
                            if (!mkdir($upload_dir, 0755, true)) {
                                $error_message = "Failed to create upload directory. Please check permissions.";
                            }
                        }
                        
                        if (!$error_message) {
                            // Get file info
                            $file_name = $_FILES['main_image']['name'];
                            $file_tmp = $_FILES['main_image']['tmp_name'];
                            $file_size = $_FILES['main_image']['size'];
                            $file_error = $_FILES['main_image']['error'];
                            
                            // Check for upload errors
                            if ($file_error !== UPLOAD_ERR_OK) {
                                switch ($file_error) {
                                    case UPLOAD_ERR_INI_SIZE:
                                    case UPLOAD_ERR_FORM_SIZE:
                                        $error_message = "File is too large. Maximum size is " . ini_get('upload_max_filesize');
                                        break;
                                    case UPLOAD_ERR_PARTIAL:
                                        $error_message = "File was only partially uploaded.";
                                        break;
                                    case UPLOAD_ERR_NO_FILE:
                                        $error_message = "No file was uploaded.";
                                        break;
                                    case UPLOAD_ERR_NO_TMP_DIR:
                                        $error_message = "Missing temporary folder.";
                                        break;
                                    case UPLOAD_ERR_CANT_WRITE:
                                        $error_message = "Failed to write file to disk.";
                                        break;
                                    case UPLOAD_ERR_EXTENSION:
                                        $error_message = "A PHP extension stopped the file upload.";
                                        break;
                                    default:
                                        $error_message = "Unknown upload error.";
                                        break;
                                }
                            } else {
                                // Check file size (limit to 5MB)
                                if ($file_size > 5 * 1024 * 1024) {
                                    $error_message = "File is too large. Maximum size is 5MB.";
                                } else {
                                    // Get file extension
                                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                    
                                    // Allowed extensions
                                    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                    
                                    if (in_array($file_ext, $allowed_ext)) {
                                        // Generate unique filename
                                        $new_filename = uniqid('', true) . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $file_ext;
                                        $target_path = $upload_dir . $new_filename;
                                        
                                        // Move uploaded file
                                        if (move_uploaded_file($file_tmp, $target_path)) {
                                            $image_path = $target_path;
                                            error_log("Image uploaded successfully: " . $target_path);
                                        } else {
                                            $error_message = "Failed to move uploaded file. Check directory permissions.";
                                            error_log("Failed to move uploaded file from $file_tmp to $target_path");
                                        }
                                    } else {
                                        $error_message = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP";
                                    }
                                }
                            }
                        }
                    } else {
                        // Check what specific error occurred
                        if (!isset($_FILES['main_image'])) {
                            $error_message = "No file was uploaded or form encoding may be incorrect.";
                        } elseif ($_FILES['main_image']['error'] !== 0) {
                            switch ($_FILES['main_image']['error']) {
                                case UPLOAD_ERR_INI_SIZE:
                                    $error_message = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                                    break;
                                case UPLOAD_ERR_FORM_SIZE:
                                    $error_message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                                    break;
                                case UPLOAD_ERR_PARTIAL:
                                    $error_message = "The uploaded file was only partially uploaded.";
                                    break;
                                case UPLOAD_ERR_NO_FILE:
                                    $error_message = "No file was uploaded.";
                                    break;
                                case UPLOAD_ERR_NO_TMP_DIR:
                                    $error_message = "Missing a temporary folder.";
                                    break;
                                case UPLOAD_ERR_CANT_WRITE:
                                    $error_message = "Failed to write file to disk.";
                                    break;
                                case UPLOAD_ERR_EXTENSION:
                                    $error_message = "A PHP extension stopped the file upload.";
                                    break;
                                default:
                                    $error_message = "Unknown upload error occurred.";
                                    break;
                            }
                        } else {
                            $error_message = "Main image is required.";
                        }
                    }
                    
                    // Only proceed if no error with image upload
                    if (!$error_message) {
                        // Handle video upload (optional)
                        $video_path = '';
                        if (isset($_FILES['product_video']) && $_FILES['product_video']['error'] === 0 && $_FILES['product_video']['size'] > 0) {
                            $upload_dir = 'gallery/';
                            $file_ext = strtolower(pathinfo($_FILES['product_video']['name'], PATHINFO_EXTENSION));
                            $allowed_video_types = ['mp4', 'mov', 'avi', 'webm'];
                            
                            if (in_array($file_ext, $allowed_video_types)) {
                                $filename = uniqid() . '_video_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $file_ext;
                                $target_path = $upload_dir . $filename;
                                
                                if (move_uploaded_file($_FILES['product_video']['tmp_name'], $target_path)) {
                                    $video_path = $target_path;
                                }
                            }
                        }
                        
                        // Use first category as primary category for products table
                        $primary_category_id = !empty($category_ids) ? $category_ids[0] : null;
                        
                        // Insert product - set price to 0 since we don't sell, only rent
                        $price = 0;
                        $rental_duration = 3;
                        
                        $stmt = $conn->prepare("INSERT INTO products (category_id, name, brand, description, size, color, price, rental_price, rental_duration, security_deposit, image, video_url, stock, is_rental) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        if ($stmt) {
                            $stmt->bind_param("isssssddidsssi", $primary_category_id, $name, $brand, $description, $size_string, $color, $price, $rental_price, $rental_duration, $security_deposit, $image_path, $video_path, $total_stock, $is_rental);
                            
                            if ($stmt->execute()) {
                                $product_id = $stmt->insert_id;
                                
                                // Insert into product_categories table for multiple categories
                                foreach ($category_ids as $category_id) {
                                    $category_stmt = $conn->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
                                    if ($category_stmt) {
                                        $category_stmt->bind_param("ii", $product_id, $category_id);
                                        $category_stmt->execute();
                                        $category_stmt->close();
                                    }
                                }
                                
                                // Insert main image into gallery as primary
                                if (!empty($image_path)) {
                                    $gallery_stmt = $conn->prepare("INSERT INTO gallery (product_id, image_url, is_primary, media_type, display_order, alt_text) VALUES (?, ?, 1, 'image', 0, ?)");
                                    if ($gallery_stmt) {
                                        $alt_text = "Main image of " . $name;
                                        $gallery_stmt->bind_param("iss", $product_id, $image_path, $alt_text);
                                        $gallery_stmt->execute();
                                        $gallery_stmt->close();
                                    }
                                }
                                
                                // Handle additional images
                                $display_order = 1;
                                $additional_images_count = 0;
                                
                                if (isset($_FILES['additional_images']) && is_array($_FILES['additional_images']['name'])) {
                                    $file_count = count($_FILES['additional_images']['name']);
                                    
                                    for ($i = 0; $i < $file_count; $i++) {
                                        if ($_FILES['additional_images']['error'][$i] === 0 && $_FILES['additional_images']['size'][$i] > 0) {
                                            $tmp_name = $_FILES['additional_images']['tmp_name'][$i];
                                            $file_name = $_FILES['additional_images']['name'][$i];
                                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                            
                                            if (in_array($file_ext, $allowed_ext)) {
                                                $filename = uniqid() . '_' . $product_id . '_' . $i . '.' . $file_ext;
                                                $target_path = $upload_dir . $filename;
                                                
                                                if (move_uploaded_file($tmp_name, $target_path)) {
                                                    $alt_text = "Additional image of " . $name;
                                                    $gallery_stmt = $conn->prepare("INSERT INTO gallery (product_id, image_url, is_primary, media_type, display_order, alt_text) VALUES (?, ?, 0, 'image', ?, ?)");
                                                    if ($gallery_stmt) {
                                                        $gallery_stmt->bind_param("isis", $product_id, $target_path, $display_order, $alt_text);
                                                        $gallery_stmt->execute();
                                                        $gallery_stmt->close();
                                                    }
                                                    $display_order++;
                                                    $additional_images_count++;
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                // Insert video into gallery if exists
                                if (!empty($video_path)) {
                                    $gallery_stmt = $conn->prepare("INSERT INTO gallery (product_id, image_url, is_primary, media_type, display_order, alt_text) VALUES (?, ?, 0, 'video', ?, ?)");
                                    if ($gallery_stmt) {
                                        $alt_text = "Video of " . $name;
                                        $gallery_stmt->bind_param("isis", $product_id, $video_path, $display_order, $alt_text);
                                        $gallery_stmt->execute();
                                        $gallery_stmt->close();
                                    }
                                }
                                
                                // Handle dress styles
                                if (isset($_POST['dress_styles']) && is_array($_POST['dress_styles'])) {
                                    foreach ($_POST['dress_styles'] as $style_id) {
                                        $style_stmt = $conn->prepare("INSERT INTO product_styles (product_id, style_id) VALUES (?, ?)");
                                        if ($style_stmt) {
                                            $style_stmt->bind_param("ii", $product_id, $style_id);
                                            $style_stmt->execute();
                                            $style_stmt->close();
                                        }
                                    }
                                }
                                
                                // Handle custom style
                                if (!empty($_POST['custom_style'])) {
                                    $custom_style = trim($_POST['custom_style']);
                                    // Insert into dress_styles table
                                    $style_stmt = $conn->prepare("INSERT INTO dress_styles (style_name, is_custom) VALUES (?, 1)");
                                    if ($style_stmt) {
                                        $style_stmt->bind_param("s", $custom_style);
                                        if ($style_stmt->execute()) {
                                            $style_id = $style_stmt->insert_id;
                                            // Link to product
                                            $link_stmt = $conn->prepare("INSERT INTO product_styles (product_id, style_id) VALUES (?, ?)");
                                            if ($link_stmt) {
                                                $link_stmt->bind_param("ii", $product_id, $style_id);
                                                $link_stmt->execute();
                                                $link_stmt->close();
                                            }
                                        }
                                        $style_stmt->close();
                                    }
                                }
                                
                                $success_message = "Rental product added successfully! Product ID: " . $product_id . " with " . $additional_images_count . " additional images!";
                                
                                // Clear form by redirecting
                                header("Location: add_product.php?success=" . urlencode($success_message));
                                exit;
                                
                            } else {
                                $error_message = "Error adding product: " . $stmt->error;
                            }
                            
                            $stmt->close();
                        } else {
                            $error_message = "Failed to prepare product statement: " . $conn->error;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Check for success message in URL
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Get categories for dropdown
$categories_result = $conn->query("SELECT * FROM categories ORDER BY category_name");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Get dress styles for dropdown
$styles_result = $conn->query("SELECT * FROM dress_styles WHERE is_custom = 0 ORDER BY style_name");
$styles = [];
while ($row = $styles_result->fetch_assoc()) {
    $styles[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Rental Product - Ozyde Admin</title>
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
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: "Helvetica Neue", Arial, sans-serif;
            color: var(--text);
            background-color: #f9f9f9;
            line-height: 1.5;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            margin-bottom: 30px;
            color: var(--accent);
            border-bottom: 2px solid var(--accent);
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--accent);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .size-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .size-row input {
            flex: 1;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        
        .btn-primary:hover {
            background: #333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc3545;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .style-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }
        
        .style-chip {
            background: var(--chip-bg);
            border: 1px solid var(--chip-border);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .style-chip.selected {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .category-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }
        
        .category-chip {
            background: var(--chip-bg);
            border: 1px solid var(--chip-border);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .category-chip.selected {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .file-upload {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 10px;
            background: #fafafa;
        }
        
        .file-upload:hover {
            border-color: var(--accent);
            background: #f0f0f0;
        }
        
        .file-upload.dragover {
            border-color: var(--accent);
            background: #e8f4f8;
        }
        
        .video-upload {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }
        
        .video-upload:hover {
            border-color: var(--accent);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .rental-fields {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--accent);
        }
        
        .rental-fields h3 {
            margin-bottom: 15px;
            color: var(--accent);
        }
        
        select.form-control {
            background: white;
        }
        
        .rental-note {
            background: #e8f5e8;
            border: 1px solid #2fa46b;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .rental-note strong {
            color: #2fa46b;
        }
        
        .multi-select-note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }

        .file-preview {
            margin-top: 10px;
        }

        .file-preview-item {
            display: inline-block;
            margin: 5px;
            padding: 5px 10px;
            background: #f0f0f0;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .required::after {
            content: " *";
            color: var(--danger);
        }
        
        .upload-hint {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add New Rental Product</h1>
        
        <div class="rental-note">
            <strong>Rental Business Only:</strong> All products are available for rental with a standard R800 security deposit.
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <strong>Error:</strong> <?php echo $error_message; ?>
                <div class="upload-hint">
                    <strong>Upload Troubleshooting:</strong>
                    <ul>
                        <li>Make sure the image is less than 5MB</li>
                        <li>Supported formats: JPG, PNG, GIF, WEBP</li>
                        <li>Check that the 'gallery' folder exists and is writable</li>
                        <li>Try a different image file</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" id="productForm" onsubmit="return validateForm()">
            <!-- Basic Information -->
            <div class="form-group">
                <label for="category_id" class="required">Categories</label>
                <div class="category-chips" id="categoryChips">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-chip" data-category-id="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="category_id[]" id="selectedCategories">
                <div class="multi-select-note">You can select multiple categories</div>
            </div>
            
            <div class="form-group">
                <label for="name" class="required">Product Name</label>
                <input type="text" name="name" id="name" class="form-control" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="brand">Brand/Designer</label>
                <input type="text" name="brand" id="brand" class="form-control" placeholder="e.g., Chanel, Valentino" value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="description" class="required">Description</label>
                <textarea name="description" id="description" class="form-control" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="color" class="required">Color</label>
                <input type="text" name="color" id="color" class="form-control" required value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : ''; ?>">
            </div>
            
            <!-- Rental Pricing -->
            <div class="rental-fields">
                <h3>Rental Pricing</h3>
                
                <div class="form-group">
                    <label for="rental_price" class="required">Rental Price (R)</label>
                    <input type="number" name="rental_price" id="rental_price" class="form-control" step="0.01" min="0" required placeholder="e.g., 400.00" value="<?php echo isset($_POST['rental_price']) ? htmlspecialchars($_POST['rental_price']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Security Deposit</label>
                    <input type="text" class="form-control" value="R800 (Standard)" readonly style="background: #f8f9fa;">
                    <input type="hidden" name="security_deposit" value="800.00">
                </div>
            </div>
            
            <!-- Dress Styles -->
            <div class="form-group">
                <label>Dress Styles</label>
                <div class="style-chips" id="styleChips">
                    <?php foreach ($styles as $style): ?>
                        <div class="style-chip" data-style-id="<?php echo $style['style_id']; ?>">
                            <?php echo htmlspecialchars($style['style_name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="dress_styles[]" id="selectedStyles">
            </div>
            
            <div class="form-group">
                <label for="custom_style">Custom Style (optional)</label>
                <input type="text" name="custom_style" id="custom_style" class="form-control" 
                       placeholder="Add a custom style if not in the list above" value="<?php echo isset($_POST['custom_style']) ? htmlspecialchars($_POST['custom_style']) : ''; ?>">
            </div>
            
            <!-- Sizes and Stock -->
            <div class="form-group">
                <label class="required">Sizes and Stock</label>
                <div id="sizesContainer">
                    <div class="size-row">
                        <input type="text" name="sizes[]" placeholder="Size (e.g., XS, S, M)" class="form-control" required>
                        <input type="number" name="stock[]" placeholder="Stock" class="form-control" min="1" required>
                        <button type="button" class="btn btn-danger" onclick="removeSize(this)">Remove</button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addSize()" style="margin-top: 10px;">
                    Add Another Size
                </button>
            </div>
            
            <!-- Main Image -->
            <div class="form-group">
                <label for="main_image" class="required">Main Product Image</label>
                <div class="file-upload" id="mainImageUpload">
                    <div class="file-input-wrapper">
                        <input type="file" name="main_image" id="main_image" accept=".jpg,.jpeg,.png,.gif,.webp" required>
                        <p>📷 Click to upload or drag and drop</p>
                        <p class="file-info">Maximum file size: 5MB • Supported formats: JPG, PNG, GIF, WEBP</p>
                    </div>
                </div>
                <div class="file-preview" id="mainImagePreview"></div>
            </div>
            
            <!-- Additional Images -->
            <div class="form-group">
                <label for="additional_images">Additional Images (optional)</label>
                <div class="file-upload">
                    <div class="file-input-wrapper">
                        <input type="file" name="additional_images[]" id="additional_images" accept=".jpg,.jpeg,.png,.gif,.webp" multiple>
                        <p>🖼️ Click to upload multiple images or drag and drop</p>
                        <p class="file-info">You can select multiple images</p>
                    </div>
                </div>
                <div class="file-preview" id="additionalImagesPreview"></div>
            </div>
            
            <!-- Product Video -->
            <div class="form-group">
                <label for="product_video">Product Video (optional)</label>
                <div class="video-upload">
                    <div class="file-input-wrapper">
                        <input type="file" name="product_video" id="product_video" accept=".mp4,.mov,.avi,.webm">
                        <p>🎥 Click to upload a video</p>
                        <p class="file-info">Supported formats: MP4, MOV, AVI, WEBM</p>
                    </div>
                </div>
                <div class="file-preview" id="videoPreview"></div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitBtn">Add Rental Product</button>
                <a href="admindashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </form>
    </div>

    <script>
        // Handle category selection
        const categoryChips = document.querySelectorAll('.category-chip');
        const selectedCategoriesInput = document.getElementById('selectedCategories');
        let selectedCategories = [];
        
        categoryChips.forEach(chip => {
            chip.addEventListener('click', () => {
                const categoryId = chip.getAttribute('data-category-id');
                const index = selectedCategories.indexOf(categoryId);
                
                if (index > -1) {
                    selectedCategories.splice(index, 1);
                    chip.classList.remove('selected');
                } else {
                    selectedCategories.push(categoryId);
                    chip.classList.add('selected');
                }
                
                selectedCategoriesInput.value = selectedCategories.join(',');
            });
        });
        
        // Handle style selection
        const styleChips = document.querySelectorAll('.style-chip');
        const selectedStylesInput = document.getElementById('selectedStyles');
        let selectedStyles = [];
        
        styleChips.forEach(chip => {
            chip.addEventListener('click', () => {
                const styleId = chip.getAttribute('data-style-id');
                const index = selectedStyles.indexOf(styleId);
                
                if (index > -1) {
                    selectedStyles.splice(index, 1);
                    chip.classList.remove('selected');
                } else {
                    selectedStyles.push(styleId);
                    chip.classList.add('selected');
                }
                
                selectedStylesInput.value = selectedStyles.join(',');
            });
        });
        
        // Handle sizes
        function addSize() {
            const container = document.getElementById('sizesContainer');
            const newRow = document.createElement('div');
            newRow.className = 'size-row';
            newRow.innerHTML = `
                <input type="text" name="sizes[]" placeholder="Size (e.g., XS, S, M)" class="form-control" required>
                <input type="number" name="stock[]" placeholder="Stock" class="form-control" min="1" required>
                <button type="button" class="btn btn-danger" onclick="removeSize(this)">Remove</button>
            `;
            container.appendChild(newRow);
        }
        
        function removeSize(button) {
            const container = document.getElementById('sizesContainer');
            if (container.children.length > 1) {
                button.parentElement.remove();
            }
        }
        
        // File input feedback and preview
        const mainImageInput = document.getElementById('main_image');
        const additionalImagesInput = document.getElementById('additional_images');
        const videoInput = document.getElementById('product_video');
        const mainImagePreview = document.getElementById('mainImagePreview');
        const additionalImagesPreview = document.getElementById('additionalImagesPreview');
        const videoPreview = document.getElementById('videoPreview');
        const mainImageUpload = document.getElementById('mainImageUpload');
        
        // Drag and drop for main image
        mainImageUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            mainImageUpload.classList.add('dragover');
        });
        
        mainImageUpload.addEventListener('dragleave', () => {
            mainImageUpload.classList.remove('dragover');
        });
        
        mainImageUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            mainImageUpload.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                mainImageInput.files = e.dataTransfer.files;
                updateMainImagePreview();
            }
        });
        
        function updateMainImagePreview() {
            mainImagePreview.innerHTML = '';
            if (mainImageInput.files.length > 0) {
                const file = mainImageInput.files[0];
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                
                // Check file size
                if (file.size > 5 * 1024 * 1024) {
                    mainImagePreview.innerHTML = `<div style="color: red;">File too large: ${fileSize} MB (max 5MB)</div>`;
                    mainImageInput.value = '';
                    return;
                }
                
                // Check file type
                const fileExt = file.name.split('.').pop().toLowerCase();
                const allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!allowedExt.includes(fileExt)) {
                    mainImagePreview.innerHTML = `<div style="color: red;">Invalid file type: .${fileExt}</div>`;
                    mainImageInput.value = '';
                    return;
                }
                
                const previewItem = document.createElement('div');
                previewItem.className = 'file-preview-item';
                previewItem.innerHTML = `✅ <strong>${file.name}</strong> (${fileSize} MB)`;
                mainImagePreview.appendChild(previewItem);
                
                // Create image preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgPreview = document.createElement('img');
                    imgPreview.src = e.target.result;
                    imgPreview.style.maxWidth = '200px';
                    imgPreview.style.maxHeight = '200px';
                    imgPreview.style.marginTop = '10px';
                    imgPreview.style.borderRadius = '4px';
                    previewItem.appendChild(imgPreview);
                };
                reader.readAsDataURL(file);
            }
        }
        
        mainImageInput.addEventListener('change', updateMainImagePreview);
        
        additionalImagesInput.addEventListener('change', function() {
            additionalImagesPreview.innerHTML = '';
            if (this.files.length > 0) {
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    const previewItem = document.createElement('div');
                    previewItem.className = 'file-preview-item';
                    previewItem.innerHTML = `✅ <strong>${file.name}</strong> (${fileSize} MB)`;
                    additionalImagesPreview.appendChild(previewItem);
                }
            }
        });
        
        videoInput.addEventListener('change', function() {
            videoPreview.innerHTML = '';
            if (this.files.length > 0) {
                const file = this.files[0];
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const previewItem = document.createElement('div');
                previewItem.className = 'file-preview-item';
                previewItem.innerHTML = `🎥 <strong>${file.name}</strong> (${fileSize} MB)`;
                videoPreview.appendChild(previewItem);
            }
        });
        
        // Form validation
        function validateForm() {
            const submitBtn = document.getElementById('submitBtn');
            
            // Check categories
            if (selectedCategories.length === 0) {
                alert('Please select at least one category.');
                return false;
            }
            
            // Check sizes
            const sizes = document.querySelectorAll('input[name="sizes[]"]');
            let hasValidSize = false;
            
            sizes.forEach(sizeInput => {
                if (sizeInput.value.trim() !== '') {
                    hasValidSize = true;
                }
            });
            
            if (!hasValidSize) {
                alert('Please add at least one size with stock information.');
                return false;
            }
            
            // Check stock values
            const stocks = document.querySelectorAll('input[name="stock[]"]');
            let hasValidStock = true;
            
            stocks.forEach(stockInput => {
                if (parseInt(stockInput.value) < 1) {
                    hasValidStock = false;
                }
            });
            
            if (!hasValidStock) {
                alert('Stock quantity must be at least 1 for all sizes.');
                return false;
            }
            
            // Check main image
            if (!mainImageInput.files.length) {
                alert('Please select a main product image.');
                return false;
            }
            
            // Disable submit button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.textContent = 'Adding Product...';
            
            return true;
        }
        
        // Initialize with one size row
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure at least one size row exists
            const container = document.getElementById('sizesContainer');
            if (container.children.length === 0) {
                addSize();
            }
        });
    </script>
</body>
</html>