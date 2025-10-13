<?php
session_start();
require 'db.php';


$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $category_id = $_POST['category_id'];
        $name = $_POST['name'];
        $brand = $_POST['brand'] ?? '';
        $description = $_POST['description'];
        $color = $_POST['color'];
        $rental_price = $_POST['rental_price'];
        $security_deposit = 800.00; // Fixed R800 deposit
        $is_rental = 1; // Always rental since it's strictly rental business
        
        // Handle sizes and stock
        $sizes_data = [];
        if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
            foreach ($_POST['sizes'] as $index => $size) {
                if (!empty($size) && isset($_POST['stock'][$index])) {
                    $sizes_data[] = [
                        'size' => $size,
                        'stock' => $_POST['stock'][$index]
                    ];
                }
            }
        }
        
        // Convert sizes data to string format (e.g., "XS:5,S:3,M:2")
        $size_string = '';
        foreach ($sizes_data as $size_data) {
            if (!empty($size_string)) $size_string .= ',';
            $size_string .= $size_data['size'] . ':' . $size_data['stock'];
        }
        
        // Calculate total stock
        $total_stock = array_sum(array_column($sizes_data, 'stock'));
        
        // Handle main image upload
        $image_path = '';
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
            $upload_dir = 'gallery/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_main_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['main_image']['tmp_name'], $target_path)) {
                $image_path = $target_path;
            }
        }
        
        // Handle video upload
        $video_path = '';
        if (isset($_FILES['product_video']) && $_FILES['product_video']['error'] === 0) {
            $upload_dir = 'gallery/';
            $file_extension = pathinfo($_FILES['product_video']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_video_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['product_video']['tmp_name'], $target_path)) {
                $video_path = $target_path;
            }
        }
        
        // Insert product - set price to 0 since we don't sell, only rent
        $price = 0; // Set to 0 since no purchases
        $stmt = $conn->prepare("INSERT INTO products (category_id, name, brand, description, size, color, price, rental_price, security_deposit, image, video_url, stock, is_rental) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssddsssii", $category_id, $name, $brand, $description, $size_string, $color, $price, $rental_price, $security_deposit, $image_path, $video_path, $total_stock, $is_rental);
        
        if ($stmt->execute()) {
            $product_id = $stmt->insert_id;
            
            // Insert main image into gallery as primary
            if (!empty($image_path)) {
                $gallery_stmt = $conn->prepare("INSERT INTO gallery (product_id, image_url, is_primary, media_type, display_order, alt_text) VALUES (?, ?, 1, 'image', 0, ?)");
                $alt_text = "Main image of " . $name;
                $gallery_stmt->bind_param("iss", $product_id, $image_path, $alt_text);
                $gallery_stmt->execute();
                $gallery_stmt->close();
            }
            
            // Handle additional images
            $display_order = 1;
            if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
                foreach ($_FILES['additional_images']['tmp_name'] as $index => $tmp_name) {
                    if ($_FILES['additional_images']['error'][$index] === 0) {
                        $file_extension = pathinfo($_FILES['additional_images']['name'][$index], PATHINFO_EXTENSION);
                        $filename = uniqid() . '_' . $product_id . '_' . $index . '.' . $file_extension;
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $target_path)) {
                            $alt_text = "Additional image of " . $name;
                            $gallery_stmt = $conn->prepare("INSERT INTO gallery (product_id, image_url, is_primary, media_type, display_order, alt_text) VALUES (?, ?, 0, 'image', ?, ?)");
                            $gallery_stmt->bind_param("isis", $product_id, $target_path, $display_order, $alt_text);
                            $gallery_stmt->execute();
                            $gallery_stmt->close();
                            $display_order++;
                        }
                    }
                }
            }
            
            // Insert video into gallery if exists
            if (!empty($video_path)) {
                $gallery_stmt = $conn->prepare("INSERT INTO gallery (product_id, image_url, is_primary, media_type, display_order, alt_text) VALUES (?, ?, 0, 'video', ?, ?)");
                $alt_text = "Video of " . $name;
                $gallery_stmt->bind_param("isis", $product_id, $video_path, $display_order, $alt_text);
                $gallery_stmt->execute();
                $gallery_stmt->close();
            }
            
            // Handle dress styles
            if (isset($_POST['dress_styles']) && is_array($_POST['dress_styles'])) {
                foreach ($_POST['dress_styles'] as $style_id) {
                    $style_stmt = $conn->prepare("INSERT INTO product_styles (product_id, style_id) VALUES (?, ?)");
                    $style_stmt->bind_param("ii", $product_id, $style_id);
                    $style_stmt->execute();
                    $style_stmt->close();
                }
            }
            
            // Handle custom style
            if (!empty($_POST['custom_style'])) {
                $custom_style = $_POST['custom_style'];
                // Insert into dress_styles table
                $style_stmt = $conn->prepare("INSERT INTO dress_styles (style_name, is_custom) VALUES (?, 1)");
                $style_stmt->bind_param("s", $custom_style);
                if ($style_stmt->execute()) {
                    $style_id = $style_stmt->insert_id;
                    // Link to product
                    $link_stmt = $conn->prepare("INSERT INTO product_styles (product_id, style_id) VALUES (?, ?)");
                    $link_stmt->bind_param("ii", $product_id, $style_id);
                    $link_stmt->execute();
                    $link_stmt->close();
                }
                $style_stmt->close();
            }
            
            $success_message = "Rental product added successfully with " . ($display_order - 1) . " additional images!";
            
        } else {
            $error_message = "Error adding product: " . $stmt->error;
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
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
        /* Ozyde consistent styling */
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
        
        .file-upload {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .file-upload:hover {
            border-color: var(--accent);
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
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <!-- Basic Information -->
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select name="category_id" id="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="brand">Brand/Designer</label>
                <input type="text" name="brand" id="brand" class="form-control" placeholder="e.g., Chanel, Valentino">
            </div>
            
            <div class="form-group">
                <label for="description">Description *</label>
                <textarea name="description" id="description" class="form-control" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="color">Color *</label>
                <input type="text" name="color" id="color" class="form-control" required>
            </div>
            
            <!-- Rental Pricing -->
            <div class="rental-fields">
                <h3>Rental Pricing</h3>
                
                <div class="form-group">
                    <label for="rental_price">Rental Price (R) *</label>
                    <input type="number" name="rental_price" id="rental_price" class="form-control" step="0.01" min="0" required placeholder="e.g., 400.00">
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
                       placeholder="Add a custom style if not in the list above">
            </div>
            
            <!-- Sizes and Stock -->
            <div class="form-group">
                <label>Sizes and Stock *</label>
                <div id="sizesContainer">
                    <div class="size-row">
                        <input type="text" name="sizes[]" placeholder="Size (e.g., XS, S, M)" class="form-control" required>
                        <input type="number" name="stock[]" placeholder="Stock" class="form-control" min="0" required>
                        <button type="button" class="btn btn-danger" onclick="removeSize(this)">Remove</button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addSize()" style="margin-top: 10px;">
                    Add Another Size
                </button>
            </div>
            
            <!-- Main Image -->
            <div class="form-group">
                <label for="main_image">Main Product Image *</label>
                <div class="file-upload">
                    <input type="file" name="main_image" id="main_image" accept="image/*" required>
                </div>
                <div class="file-info">Recommended: 800x1000px, JPG or PNG format</div>
            </div>
            
            <!-- Additional Images -->
            <div class="form-group">
                <label for="additional_images">Additional Images (optional)</label>
                <div class="file-upload">
                    <input type="file" name="additional_images[]" id="additional_images" accept="image/*" multiple>
                </div>
                <div class="file-info">You can select multiple images. They will be displayed in the order you select them.</div>
            </div>
            
            <!-- Product Video -->
            <div class="form-group">
                <label for="product_video">Product Video (optional)</label>
                <div class="video-upload">
                    <input type="file" name="product_video" id="product_video" accept="video/*">
                </div>
                <div class="file-info">Recommended: MP4 format, under 50MB</div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Rental Product</button>
                <a href="admindashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </form>
    </div>

    <script>
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
                <input type="number" name="stock[]" placeholder="Stock" class="form-control" min="0" required>
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
        
        // File input feedback
        const mainImageInput = document.getElementById('main_image');
        const additionalImagesInput = document.getElementById('additional_images');
        const videoInput = document.getElementById('product_video');
        
        mainImageInput.addEventListener('change', function() {
            const fileInfo = this.nextElementSibling;
            if (this.files.length > 0) {
                fileInfo.textContent = 'Selected: ' + this.files[0].name;
            }
        });
        
        additionalImagesInput.addEventListener('change', function() {
            const fileInfo = this.nextElementSibling;
            if (this.files.length > 0) {
                fileInfo.textContent = 'Selected ' + this.files.length + ' images';
            }
        });
        
        videoInput.addEventListener('change', function() {
            const fileInfo = this.nextElementSibling;
            if (this.files.length > 0) {
                fileInfo.textContent = 'Selected: ' + this.files[0].name;
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const sizes = document.querySelectorAll('input[name="sizes[]"]');
            let hasValidSize = false;
            
            sizes.forEach(sizeInput => {
                if (sizeInput.value.trim() !== '') {
                    hasValidSize = true;
                }
            });
            
            if (!hasValidSize) {
                e.preventDefault();
                alert('Please add at least one size with stock information.');
            }
        });
    </script>
</body>
</html>