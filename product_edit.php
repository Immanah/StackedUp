<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success_message = '';

// Handle image removal via AJAX
if (isset($_POST['remove_image']) && !empty($_POST['image_id'])) {
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $image_id = (int)$_POST['image_id'];
    
    // Get image info before deletion
    $stmt = $mysqli->prepare("SELECT filename, product_id FROM product_images WHERE image_id = ?");
    $stmt->bind_param('i', $image_id);
    $stmt->execute();
    $image = $stmt->get_result()->fetch_assoc();
    
    if ($image && $image['product_id'] == $id) {
        // Delete from database
        $delete_stmt = $mysqli->prepare("DELETE FROM product_images WHERE image_id = ?");
        $delete_stmt->bind_param('i', $image_id);
        $delete_stmt->execute();
        
        // Delete file from server
        $file_path = __DIR__ . '/../' . $image['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // If this was the primary image, set a new primary
        $primary_check = $mysqli->prepare("SELECT image_id FROM product_images WHERE product_id = ? LIMIT 1");
        $primary_check->bind_param('i', $id);
        $primary_check->execute();
        $new_primary = $primary_check->get_result()->fetch_assoc();
        
        if ($new_primary) {
            $update_primary = $mysqli->prepare("UPDATE product_images SET is_primary = 1 WHERE image_id = ?");
            $update_primary->bind_param('i', $new_primary['image_id']);
            $update_primary->execute();
            
            // Update product's main image
            $get_primary_path = $mysqli->prepare("SELECT filename FROM product_images WHERE image_id = ?");
            $get_primary_path->bind_param('i', $new_primary['image_id']);
            $get_primary_path->execute();
            $primary_image = $get_primary_path->get_result()->fetch_assoc();
            
            $update_product = $mysqli->prepare("UPDATE products SET image = ? WHERE product_id = ?");
            $update_product->bind_param('si', $primary_image['filename'], $id);
            $update_product->execute();
        } else {
            // No images left, clear product image
            $update_product = $mysqli->prepare("UPDATE products SET image = NULL WHERE product_id = ?");
            $update_product->bind_param('i', $id);
            $update_product->execute();
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Image not found']);
    exit;
}

// Handle image reordering
if (isset($_POST['update_image_order']) && !empty($_POST['image_order'])) {
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $image_order = json_decode($_POST['image_order'], true);
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // First, clear all primary flags
        $clear_primary = $mysqli->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
        $clear_primary->bind_param('i', $id);
        $clear_primary->execute();
        
        // Update order and set primary image
        foreach ($image_order as $index => $image_id) {
            $is_primary = ($index === 0) ? 1 : 0;
            $update_stmt = $mysqli->prepare("UPDATE product_images SET is_primary = ?, display_order = ? WHERE image_id = ? AND product_id = ?");
            $update_stmt->bind_param('iiii', $is_primary, $index, $image_id, $id);
            $update_stmt->execute();
        }
        
        // Update product's main image
        $get_primary = $mysqli->prepare("SELECT filename FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
        $get_primary->bind_param('i', $id);
        $get_primary->execute();
        $primary_result = $get_primary->get_result();
        
        if ($primary_result->num_rows > 0) {
            $primary_image = $primary_result->fetch_assoc();
            $update_product = $mysqli->prepare("UPDATE products SET image = ? WHERE product_id = ?");
            $update_product->bind_param('si', $primary_image['filename'], $id);
            $update_product->execute();
        }
        
        $mysqli->commit();
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to update image order']);
        exit;
    }
}

// Create/update product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['remove_image']) && !isset($_POST['update_image_order'])) {
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
        
        // Handle sizes with DEFAULT STOCK OF 5 for all sizes
        $sizes = $_POST['sizes'] ?? [];
        $stocks = $_POST['stocks'] ?? [];
        
        $size_stock_pairs = [];
        foreach($sizes as $i => $size) {
            $size = trim($size);
            // DEFAULT STOCK: Always set to 5 regardless of input
            $qty = 5;
            if ($size) {
                $size_stock_pairs[] = $size . ':' . $qty;
            }
        }
        $size_str = implode(',', $size_stock_pairs);

        if ($name === '' || $price <= 0) {
            $errors[] = "Provide name and price.";
        }

        // Color validation - only letters and spaces, but make it optional
        if (!empty($color) && !preg_match('/^[a-zA-Z\s]+$/', $color)) {
            $errors[] = "Color can only contain letters and spaces. No numbers or special characters allowed.";
        }

        if (empty($errors)) {
            // Handle multiple image uploads
            $uploaded_images = [];
            $uploadDir = __DIR__ . '/../gallery/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Handle all uploaded images
            if (!empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK && !empty($tmp_name)) {
                        $image_name = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                        $target = $uploadDir . $image_name;
                        
                        if (move_uploaded_file($tmp_name, $target)) {
                            $uploaded_images[] = 'gallery/' . $image_name;
                        } else {
                            $errors[] = "Failed to upload image: " . $_FILES['images']['name'][$key];
                        }
                    }
                }
            }

            if (empty($errors)) {
                if ($id) {
                    // Update existing product - FIXED: Make sure color is properly updated
                    $stmt = $mysqli->prepare("UPDATE products SET category_id=?, name=?, description=?, size=?, color=?, price=? WHERE product_id=?");
                    $stmt->bind_param('isssdsi', $category_id, $name, $description, $size_str, $color, $price, $id);
                    if ($stmt->execute()) {
                        $success_message = "Product updated successfully! Changes will be visible on the catalog and product details pages.";
                        
                        // DEBUG: Check if color was actually updated
                        error_log("DEBUG: Updated product $id with color: " . $color);
                        
                        // IMPORTANT: Reload the product data to reflect ALL changes immediately
                        $reload_stmt = $mysqli->prepare("SELECT * FROM products WHERE product_id = ? LIMIT 1");
                        $reload_stmt->bind_param('i', $id);
                        $reload_stmt->execute();
                        $product = $reload_stmt->get_result()->fetch_assoc();
                        $reload_stmt->close();
                        
                        // Update the $color variable with the value from database to show in form
                        $color = $product['color'] ?? '';
                    } else {
                        $errors[] = "Failed to update product: " . $stmt->error;
                        error_log("ERROR: Failed to update product $id: " . $stmt->error);
                    }
                } else {
                    // Insert new product - all products are rentals (is_rental=1)
                    $stmt = $mysqli->prepare("INSERT INTO products (category_id, name, description, size, color, price, is_rental) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->bind_param('issssd', $category_id, $name, $description, $size_str, $color, $price);
                    if ($stmt->execute()) {
                        $id = $mysqli->insert_id;
                        $success_message = "Product created successfully!";
                        
                        // After creating, load the product to show in form
                        $reload_stmt = $mysqli->prepare("SELECT * FROM products WHERE product_id = ? LIMIT 1");
                        $reload_stmt->bind_param('i', $id);
                        $reload_stmt->execute();
                        $product = $reload_stmt->get_result()->fetch_assoc();
                        $reload_stmt->close();
                        
                        // Update the $color variable with the value from database
                        $color = $product['color'] ?? '';
                    } else {
                        $errors[] = "Failed to create product: " . $stmt->error;
                    }
                }

                // Insert all images into product_images table
                if (!empty($uploaded_images) && empty($errors)) {
                    // Get current max display_order
                    $max_order = 0;
                    if ($id) {
                        $order_stmt = $mysqli->prepare("SELECT MAX(display_order) as max_order FROM product_images WHERE product_id = ?");
                        $order_stmt->bind_param('i', $id);
                        $order_stmt->execute();
                        $order_result = $order_stmt->get_result()->fetch_assoc();
                        $max_order = $order_result['max_order'] ?? 0;
                    }
                    
                    foreach ($uploaded_images as $index => $image_path) {
                        $display_order = $max_order + $index + 1;
                        $is_primary = (empty($existing_images) && $index === 0) ? 1 : 0; // Only primary if no existing images
                        
                        $img_stmt = $mysqli->prepare("INSERT INTO product_images (product_id, filename, is_primary, display_order) VALUES (?, ?, ?, ?)");
                        $img_stmt->bind_param('isii', $id, $image_path, $is_primary, $display_order);
                        if (!$img_stmt->execute()) {
                            $errors[] = "Failed to upload image: " . $img_stmt->error;
                        }
                        $img_stmt->close();
                    }
                    
                    // Update products table with the primary image if no primary exists
                    if (empty($existing_images) && !empty($uploaded_images[0]) && empty($errors)) {
                        $update_stmt = $mysqli->prepare("UPDATE products SET image = ? WHERE product_id = ?");
                        $update_stmt->bind_param('si', $uploaded_images[0], $id);
                        if (!$update_stmt->execute()) {
                            $errors[] = "Failed to update primary image: " . $update_stmt->error;
                        }
                        $update_stmt->close();
                    }
                }

                if (empty($errors)) {
                    // Log activity
                    $log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, ?, ?)");
                    $act = $id ? 'product_updated' : 'product_created';
                    $ctx = json_encode(['product_id'=>$id,'name'=>$name]);
                    $log->bind_param('iss', $_SESSION['admin_id'], $act, $ctx);
                    $log->execute();
                }
            }
        }
    }
}

// Load product if editing - This should happen AFTER form processing to show updated values
$product = null;
$sizes = [];
$existing_images = [];
if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM products WHERE product_id = ? LIMIT 1");
    $stmt->bind_param('i', $id); 
    $stmt->execute(); 
    $product = $stmt->get_result()->fetch_assoc();
    
    // Parse sizes from the size string (format: "S:10,M:5,L:8")
    if ($product && !empty($product['size'])) {
        $pairs = explode(',', $product['size']);
        foreach ($pairs as $pair) {
            $parts = explode(':', $pair);
            if (count($parts) === 2) {
                $sizes[] = [
                    'size' => $parts[0],
                    'stock' => $parts[1]
                ];
            }
        }
    }
    
    // Get existing product images with ordering
    $img_stmt = $mysqli->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY display_order ASC, is_primary DESC");
    $img_stmt->bind_param('i', $id);
    $img_stmt->execute();
    $existing_images_result = $img_stmt->get_result();
    while ($image = $existing_images_result->fetch_assoc()) {
        $existing_images[] = $image;
    }
    $img_stmt->close();
}

// Get categories
$catRes = $mysqli->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= $id ? 'Edit Product' : 'Add Product' ?> — OZYDE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #fff;
            --nav-bg: #111;
            --muted: #9a9a9a;
            --accent: #000;
            --max-width: 1200px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            color: #111;
            background: var(--bg);
        }
        main {
            max-width: var(--max-width);
            margin: 28px auto;
            padding: 0 18px 60px;
        }
        h1 { margin-bottom: 16px; }
        label { display:block; margin-top:12px; font-weight:700; }
        input[type=text], input[type=number], textarea, select {
            width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; margin-top:4px;
        }
        .size-stock { display:flex; gap:12px; align-items:center; margin-top:4px; }
        .size-stock input { width:60px; }
        .size-stock input[name="stocks[]"] { 
            background-color: #f0f0f0; 
            color: #666; 
            cursor: not-allowed; 
        }
        button { margin-top:16px; padding:10px 14px; border:0; border-radius:8px; background:#000; color:#fff; cursor:pointer; }
        button:disabled { opacity:0.5; cursor:not-allowed; }
        .muted { font-size:13px; color:var(--muted); }
        .errors { color: #b91c1c; background:#fef2f2; padding:1rem; border-radius:6px; margin-bottom:1.5rem; border:1px solid #fecaca; }
        .success { color: #065f46; background:#d1fae5; padding:1rem; border-radius:6px; margin-bottom:1.5rem; border:1px solid #a7f3d0; }
        
        .image-previews {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .image-preview {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
            cursor: move;
        }
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-preview .primary-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            background: #000;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        .image-preview .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .image-preview.dragging {
            opacity: 0.5;
            border: 2px dashed #000;
        }
        .existing-images {
            margin-top: 15px;
        }
        .existing-images h4 {
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--muted);
        }
        .sortable-helper {
            z-index: 1000;
            transform: rotate(5deg);
        }
        
        /* Loading state for remove button */
        .remove-btn.loading {
            background: #6b7280;
            cursor: not-allowed;
        }
        .remove-btn.loading i {
            animation: spin 1s linear infinite;
        }
        
        /* Color input validation styling */
        .color-input.valid {
            border-color: #10b981;
            background-color: #f0fdf4;
        }
        .color-input.invalid {
            border-color: #ef4444;
            background-color: #fef2f2;
        }
        .color-hint {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }
        
        .stock-info {
            background: #e8f4fd;
            padding: 8px 12px;
            border-radius: 6px;
            margin: 8px 0;
            font-size: 13px;
            border-left: 3px solid #1890ff;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<main>
    <h1><?= $id ? 'Edit Product' : 'Add Product' ?></h1>
    
    <?php if ($success_message): ?>
        <div class="success">
            <strong>Success!</strong> <?= e($success_message) ?>
            <?php if ($id && isset($product['color'])): ?>
                <div style="margin-top: 8px;">
                    <strong>Current Color:</strong> <?= e($product['color']) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($errors): ?>
        <div class="errors">
            <strong>Please fix the following errors:</strong>
            <?php foreach ($errors as $error): ?>
                <div style="margin-top:0.5rem;">• <?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="productForm">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        
        <label>Category</label>
        <select name="category_id" required>
            <option value="">-- Select Category --</option>
            <?php foreach($categories as $c): ?>
                <option value="<?= $c['category_id'] ?>" <?= (!empty($product['category_id']) && $product['category_id']==$c['category_id'])?'selected':'' ?>>
                    <?= e($c['category_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Product Name *</label>
        <input type="text" name="name" value="<?= e($product['name'] ?? '') ?>" required />

        <label>Description</label>
        <textarea name="description" rows="4"><?= e($product['description'] ?? '') ?></textarea>

        <label>Color</label>
        <input type="text" 
               name="color" 
               id="colorInput" 
               class="color-input" 
               value="<?= e($product['color'] ?? '') ?>" 
               onkeypress="return validateColorInput(event)" />
        <div class="color-hint">Only letters and spaces allowed. Examples: Red, Navy Blue, Emerald Green</div>

        <label>Price (ZAR) *</label>
        <input type="number" name="price" min="0" step="0.01" value="<?= e($product['price'] ?? '0') ?>" required />

        <label>Sizes & Stock</label>
        <div class="stock-info">
            <strong>Note:</strong> All sizes will automatically have a stock quantity of 5. The stock input is disabled as this is managed automatically.
        </div>
        <div id="sizesContainer">
            <?php if (!empty($sizes)): ?>
                <?php foreach($sizes as $size): ?>
                    <div class="size-stock">
                        <input type="text" name="sizes[]" placeholder="S/M/L/XL" value="<?= e($size['size']) ?>" />
                        <input type="number" name="stocks[]" placeholder="Qty" value="5" min="0" readonly disabled />
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="size-stock">
                    <input type="text" name="sizes[]" placeholder="S/M/L/XL" />
                    <input type="number" name="stocks[]" placeholder="Qty" value="5" min="0" readonly disabled />
                </div>
            <?php endif; ?>
        </div>
        <button type="button" onclick="addSizeField()">Add Another Size</button>

        <label>Images</label>
        <input type="file" name="images[]" accept="image/*" multiple id="imageInput" />
        <div class="muted">You can select multiple images. First image will be used as primary. Allowed formats: JPG, PNG, GIF. Max 10MB per image.</div>
        
        <!-- Image previews for new uploads -->
        <div class="image-previews" id="imagePreviews"></div>
        
        <!-- Existing images display with drag & drop -->
        <?php if ($id && !empty($existing_images)): ?>
            <div class="existing-images">
                <h4>Current Images (drag to reorder, first image is primary):</h4>
                <div class="image-previews" id="existingImages">
                    <?php foreach ($existing_images as $image): ?>
                        <div class="image-preview" data-image-id="<?= $image['image_id'] ?>">
                            <img src="../<?= e($image['filename']) ?>" alt="Product image" onerror="this.src='../images/placeholder.png'">
                            <?php if ($image['is_primary']): ?>
                                <div class="primary-badge">Primary</div>
                            <?php endif; ?>
                            <button type="button" class="remove-btn" onclick="removeImage(<?= $image['image_id'] ?>, this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($id && !empty($product['image'])): ?>
            <div class="existing-images">
                <h4>Current Primary Image (new uploads will be added to existing images):</h4>
                <div class="image-previews">
                    <div class="image-preview">
                        <img src="../<?= e($product['image']) ?>" alt="Current image">
                        <div class="primary-badge">Primary</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <button type="submit" id="submitBtn"><?= $id ? 'Update Product' : 'Add Product' ?></button>
        <a href="products_list.php" style="margin-left:12px; color:#666; text-decoration:none;">Cancel</a>
        
        <?php if ($id): ?>
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
            <strong>Preview:</strong> 
            <a href="../productdetail.php?product_id=<?= $id ?>" target="_blank" style="color: #000; text-decoration: underline;">
                View this product on the catalog
            </a>
            <?php if (!empty($product['color'])): ?>
                <div style="margin-top: 8px;">
                    <strong>Current Color in Database:</strong> <?= e($product['color']) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
function addSizeField() {
    const div = document.createElement('div');
    div.className = 'size-stock';
    div.innerHTML = '<input type="text" name="sizes[]" placeholder="S/M/L/XL" /><input type="number" name="stocks[]" placeholder="Qty" value="5" min="0" readonly disabled />';
    document.getElementById('sizesContainer').appendChild(div);
}

// STRICT color input validation - prevents typing numbers and special characters
function validateColorInput(event) {
    const char = String.fromCharCode(event.keyCode || event.which);
    // Only allow letters and space
    if (!/^[a-zA-Z\s]$/.test(char)) {
        event.preventDefault();
        return false;
    }
    return true;
}

// Real-time color validation
const colorInput = document.getElementById('colorInput');
colorInput.addEventListener('input', function() {
    const colorValue = this.value.trim();
    // Remove any numbers or special characters that might have been pasted
    const cleanValue = colorValue.replace(/[^a-zA-Z\s]/g, '');
    if (colorValue !== cleanValue) {
        this.value = cleanValue;
    }
    
    // Update validation styling
    if (cleanValue === '') {
        this.classList.remove('valid', 'invalid');
    } else if (/^[a-zA-Z\s]+$/.test(cleanValue)) {
        this.classList.remove('invalid');
        this.classList.add('valid');
    } else {
        this.classList.remove('valid');
        this.classList.add('invalid');
    }
});

// Form validation - REMOVED strict color validation to allow empty color
document.getElementById('productForm').addEventListener('submit', function(e) {
    const colorInput = document.getElementById('colorInput');
    const colorValue = colorInput.value.trim();
    
    // Only validate if color is provided, but allow empty
    if (colorValue !== '' && !/^[a-zA-Z\s]+$/.test(colorValue)) {
        e.preventDefault();
        alert('Please enter a valid color name. Only letters and spaces are allowed.\n\nExamples: Red, Navy Blue, Emerald Green\n\nOr leave it empty if no color specified.');
        colorInput.focus();
        colorInput.classList.add('invalid');
        return;
    }
    
    const imageInput = document.getElementById('imageInput');
    const files = imageInput.files;
    
    // Check file sizes (max 10MB per image)
    for (let i = 0; i < files.length; i++) {
        if (files[i].size > 10 * 1024 * 1024) {
            e.preventDefault();
            alert('File "' + files[i].name + '" is too large. Maximum size is 10MB per image.');
            return;
        }
    }
    
    // Show loading state on submit button
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    submitBtn.disabled = true;
});

// Image preview functionality for new uploads
document.getElementById('imageInput').addEventListener('change', function(e) {
    const previewsContainer = document.getElementById('imagePreviews');
    previewsContainer.innerHTML = '';
    
    const files = e.target.files;
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (file.type.match('image.*')) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = 'image-preview';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    ${i === 0 ? '<div class="primary-badge">Primary</div>' : ''}
                `;
                previewsContainer.appendChild(preview);
            }
            
            reader.readAsDataURL(file);
        }
    }
});

// Improved image removal functionality
function removeImage(imageId, button) {
    if (!confirm('Are you sure you want to remove this image?')) {
        return;
    }
    
    // Show loading state
    button.classList.add('loading');
    button.innerHTML = '<i class="fas fa-spinner"></i>';
    button.disabled = true;
    
    const formData = new FormData();
    formData.append('remove_image', '1');
    formData.append('image_id', imageId);
    formData.append('_csrf', document.querySelector('input[name="_csrf"]').value);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remove the image element from DOM with fade effect
            const imageElement = document.querySelector(`[data-image-id="${imageId}"]`);
            if (imageElement) {
                imageElement.style.opacity = '0';
                imageElement.style.transition = 'opacity 0.3s ease';
                
                setTimeout(() => {
                    imageElement.remove();
                    
                    // If no images left, hide the existing images section
                    const existingImages = document.getElementById('existingImages');
                    if (existingImages && existingImages.children.length === 0) {
                        const existingImagesSection = existingImages.closest('.existing-images');
                        if (existingImagesSection) {
                            existingImagesSection.remove();
                        }
                    }
                    
                    // Update any primary badges if needed
                    updatePrimaryBadges();
                }, 300);
            }
        } else {
            // Reset button state
            button.classList.remove('loading');
            button.innerHTML = '<i class="fas fa-times"></i>';
            button.disabled = false;
            
            alert('Failed to remove image: ' + (data.error || 'Unknown error'));
            console.error('Image removal error:', data.error);
        }
    })
    .catch(error => {
        // Reset button state
        button.classList.remove('loading');
        button.innerHTML = '<i class="fas fa-times"></i>';
        button.disabled = false;
        
        console.error('Error:', error);
        alert('Failed to remove image. Please check your connection and try again.');
    });
}

// Helper function to update primary badges
function updatePrimaryBadges() {
    const existingImages = document.getElementById('existingImages');
    if (!existingImages) return;
    
    Array.from(existingImages.children).forEach((img, index) => {
        const primaryBadge = img.querySelector('.primary-badge');
        if (index === 0) {
            if (!primaryBadge) {
                const badge = document.createElement('div');
                badge.className = 'primary-badge';
                badge.textContent = 'Primary';
                img.appendChild(badge);
            }
        } else if (primaryBadge) {
            primaryBadge.remove();
        }
    });
}

// Initialize drag & drop for existing images
document.addEventListener('DOMContentLoaded', function() {
    const existingImages = document.getElementById('existingImages');
    if (existingImages) {
        new Sortable(existingImages, {
            animation: 150,
            ghostClass: 'dragging',
            dragClass: 'sortable-helper',
            onEnd: function(evt) {
                updateImageOrder();
            }
        });
    }
    
    // Initialize color input validation on page load
    if (colorInput.value.trim() !== '') {
        colorInput.dispatchEvent(new Event('input'));
    }
});

// Update image order after drag & drop
function updateImageOrder() {
    const existingImages = document.getElementById('existingImages');
    if (!existingImages) return;
    
    const imageOrder = Array.from(existingImages.children).map(img => img.dataset.imageId);
    
    const formData = new FormData();
    formData.append('update_image_order', '1');
    formData.append('image_order', JSON.stringify(imageOrder));
    formData.append('_csrf', document.querySelector('input[name="_csrf"]').value);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Failed to update image order:', data.error);
        }
        
        // Update primary badges
        updatePrimaryBadges();
    })
    .catch(error => {
        console.error('Error updating image order:', error);
    });
}

// Auto-scroll to top when form is submitted to show success message
document.getElementById('productForm').addEventListener('submit', function() {
    setTimeout(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, 100);
});
</script>
</body>
</html>