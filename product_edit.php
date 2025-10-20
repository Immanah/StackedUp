<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ozyde';

// Connect with mysqli
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "Database connection failed: (" . $mysqli->connect_errno . ") " . htmlspecialchars($mysqli->connect_error);
    exit;
}
$mysqli->set_charset('utf8mb4');


// Simple CSRF protection
function csrf() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success_message = '';

// Create/update product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $rental_price = (float)($_POST['rental_price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
        
        // Handle sizes like the working example
        $sizes = $_POST['sizes'] ?? [];
        $stocks = $_POST['stocks'] ?? [];
        
        $size_stock_pairs = [];
        foreach($sizes as $i => $size) {
            $size = trim($size);
            $qty = max(0, intval($stocks[$i] ?? 0));
            if ($size) {
                $size_stock_pairs[] = $size . ':' . $qty;
            }
        }
        $size_str = implode(',', $size_stock_pairs);
        $total_stock = array_sum(array_map('intval', $stocks));

        if ($name === '' || $rental_price <= 0) {
            $errors[] = "Provide name and rental price.";
        }

        if (empty($errors)) {
            // Handle image uploads
            $uploaded_images = [];
            $uploadDir = __DIR__ . '/../gallery/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Handle main image (first image becomes primary)
            if (isset($_FILES['main_image']['name']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['main_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $image_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $file_ext;
                    $target = $uploadDir . $image_name;
                    
                    if (move_uploaded_file($_FILES['main_image']['tmp_name'], $target)) {
                        $uploaded_images[] = 'gallery/' . $image_name;
                    } else {
                        $errors[] = "Failed to upload main image.";
                    }
                } else {
                    $errors[] = "Invalid main image format. Allowed: JPG, JPEG, PNG, GIF, WEBP";
                }
            }
            
            // Handle additional images
            if (isset($_FILES['additional_images']) && is_array($_FILES['additional_images']['name'])) {
                $file_count = count($_FILES['additional_images']['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['additional_images']['error'][$i] === UPLOAD_ERR_OK && $_FILES['additional_images']['size'][$i] > 0) {
                        $file_name = $_FILES['additional_images']['name'][$i];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        if (in_array($file_ext, $allowed_ext)) {
                            $image_name = uniqid() . '_additional_' . $i . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $file_ext;
                            $target = $uploadDir . $image_name;
                            
                            if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], $target)) {
                                $uploaded_images[] = 'gallery/' . $image_name;
                            }
                        }
                    }
                }
            }
            
            // Handle video upload
            $video_path = '';
            if (isset($_FILES['product_video']['name']) && $_FILES['product_video']['error'] === UPLOAD_ERR_OK && $_FILES['product_video']['size'] > 0) {
                $file_name = $_FILES['product_video']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_video_ext = ['mp4', 'mov', 'avi', 'webm'];
                
                if (in_array($file_ext, $allowed_video_ext)) {
                    $video_name = uniqid() . '_video_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $file_ext;
                    $target = $uploadDir . $video_name;
                    
                    if (move_uploaded_file($_FILES['product_video']['tmp_name'], $target)) {
                        $video_path = 'gallery/' . $video_name;
                    }
                }
            }

            // Use first image as primary, or keep existing if no new images
            $image_path = !empty($uploaded_images) ? $uploaded_images[0] : ($product['image'] ?? '');

            if (empty($errors)) {
                if ($id) {
                    // Update existing product
                    if (!empty($uploaded_images) && !empty($video_path)) {
                        $stmt = $mysqli->prepare("UPDATE products SET category_id=?, name=?, brand=?, description=?, size=?, color=?, price=?, rental_price=?, image=?, video_url=?, stock=? WHERE product_id=?");
                        $stmt->bind_param('isssssddssii', $category_id, $name, $brand, $description, $size_str, $color, $price, $rental_price, $image_path, $video_path, $total_stock, $id);
                    } elseif (!empty($uploaded_images)) {
                        $stmt = $mysqli->prepare("UPDATE products SET category_id=?, name=?, brand=?, description=?, size=?, color=?, price=?, rental_price=?, image=?, stock=? WHERE product_id=?");
                        $stmt->bind_param('isssssddssi', $category_id, $name, $brand, $description, $size_str, $color, $price, $rental_price, $image_path, $total_stock, $id);
                    } elseif (!empty($video_path)) {
                        $stmt = $mysqli->prepare("UPDATE products SET category_id=?, name=?, brand=?, description=?, size=?, color=?, price=?, rental_price=?, video_url=?, stock=? WHERE product_id=?");
                        $stmt->bind_param('isssssddssi', $category_id, $name, $brand, $description, $size_str, $color, $price, $rental_price, $video_path, $total_stock, $id);
                    } else {
                        $stmt = $mysqli->prepare("UPDATE products SET category_id=?, name=?, brand=?, description=?, size=?, color=?, price=?, rental_price=?, stock=? WHERE product_id=?");
                        $stmt->bind_param('isssssddii', $category_id, $name, $brand, $description, $size_str, $color, $price, $rental_price, $total_stock, $id);
                    }
                    $stmt->execute();
                    $success_message = "Product updated successfully!";
                } else {
                    // Insert new product - all products are rentals (is_rental=1)
                    $is_rental = 1;
                    $stmt = $mysqli->prepare("INSERT INTO products (category_id, name, brand, description, size, color, price, rental_price, image, video_url, stock, is_rental) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('isssssddssii', $category_id, $name, $brand, $description, $size_str, $color, $price, $rental_price, $image_path, $video_path, $total_stock, $is_rental);
                    $stmt->execute();
                    $id = $mysqli->insert_id;
                    $success_message = "Product added successfully! ID: " . $id;
                }

                // Insert additional images into gallery table
                if (!empty($uploaded_images)) {
                    // Skip first image (already set as primary)
                    $additional_images = array_slice($uploaded_images, 1);
                    $display_order = 1;
                    
                    foreach ($additional_images as $image_path) {
                        $gallery_stmt = $mysqli->prepare("INSERT INTO gallery (product_id, image_url, media_type, display_order, alt_text) VALUES (?, ?, 'image', ?, ?)");
                        $alt_text = "Additional image of " . $name;
                        $gallery_stmt->bind_param("isi", $id, $image_path, $display_order, $alt_text);
                        $gallery_stmt->execute();
                        $gallery_stmt->close();
                        $display_order++;
                    }
                }
                
                // Insert video into gallery if exists
                if (!empty($video_path)) {
                    $gallery_stmt = $mysqli->prepare("INSERT INTO gallery (product_id, image_url, media_type, display_order, alt_text) VALUES (?, ?, 'video', ?, ?)");
                    $alt_text = "Video of " . $name;
                    $display_order = count($uploaded_images); // Place after images
                    $gallery_stmt->bind_param("isi", $id, $video_path, $display_order, $alt_text);
                    $gallery_stmt->execute();
                    $gallery_stmt->close();
                }
                
                // Handle dress styles
                if (isset($_POST['dress_styles']) && is_array($_POST['dress_styles'])) {
                    // First remove existing styles
                    $delete_styles = $mysqli->prepare("DELETE FROM product_styles WHERE product_id = ?");
                    $delete_styles->bind_param("i", $id);
                    $delete_styles->execute();
                    $delete_styles->close();
                    
                    // Insert new styles
                    foreach ($_POST['dress_styles'] as $style_id) {
                        $style_stmt = $mysqli->prepare("INSERT INTO product_styles (product_id, style_id) VALUES (?, ?)");
                        $style_stmt->bind_param("ii", $id, $style_id);
                        $style_stmt->execute();
                        $style_stmt->close();
                    }
                }
                
                // Handle custom style
                if (!empty($_POST['custom_style'])) {
                    $custom_style = trim($_POST['custom_style']);
                    // Insert into dress_styles table
                    $style_stmt = $mysqli->prepare("INSERT INTO dress_styles (style_name, is_custom) VALUES (?, 1)");
                    if ($style_stmt) {
                        $style_stmt->bind_param("s", $custom_style);
                        if ($style_stmt->execute()) {
                            $style_id = $style_stmt->insert_id;
                            // Link to product
                            $link_stmt = $mysqli->prepare("INSERT INTO product_styles (product_id, style_id) VALUES (?, ?)");
                            if ($link_stmt) {
                                $link_stmt->bind_param("ii", $id, $style_id);
                                $link_stmt->execute();
                                $link_stmt->close();
                            }
                        }
                        $style_stmt->close();
                    }
                }
                
                // Redirect to avoid form resubmission
                if ($id) {
                    header("Location: product_edit.php?id=" . $id . "&success=" . urlencode($success_message));
                    exit;
                }
            }
        }
    }
}

// Check for success message in URL
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Load product if editing
$product = null;
$sizes = [];
$selected_styles = [];
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
    
    // Get selected styles
    $styles_stmt = $mysqli->prepare("SELECT style_id FROM product_styles WHERE product_id = ?");
    $styles_stmt->bind_param("i", $id);
    $styles_stmt->execute();
    $styles_result = $styles_stmt->get_result();
    while ($row = $styles_result->fetch_assoc()) {
        $selected_styles[] = $row['style_id'];
    }
    $styles_stmt->close();
}

// Get categories
$catRes = $mysqli->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

// Get dress styles
$stylesRes = $mysqli->query("SELECT style_id, style_name FROM dress_styles WHERE is_custom = 0 ORDER BY style_name ASC");
$styles = $stylesRes ? $stylesRes->fetch_all(MYSQLI_ASSOC) : [];

// Get existing gallery images for this product
$gallery_images = [];
$gallery_video = null;
if ($id) {
    $gallery_stmt = $mysqli->prepare("SELECT image_url, media_type FROM gallery WHERE product_id = ? ORDER BY display_order ASC");
    $gallery_stmt->bind_param("i", $id);
    $gallery_stmt->execute();
    $gallery_result = $gallery_stmt->get_result();
    while ($row = $gallery_result->fetch_assoc()) {
        if ($row['media_type'] === 'image') {
            $gallery_images[] = $row['image_url'];
        } elseif ($row['media_type'] === 'video') {
            $gallery_video = $row['image_url'];
        }
    }
    $gallery_stmt->close();
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= $id ? 'Edit Product' : 'Add Product' ?> — OZYDE</title>
    <style>
        :root {
            --bg: #fff;
            --nav-bg: #111;
            --muted: #9a9a9a;
            --accent: #000;
            --max-width: 1200px;
            --success: #2fa46b;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            color: #111;
            background: var(--bg);
            padding: 20px;
        }
        .container {
            max-width: var(--max-width);
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { margin-bottom: 16px; }
        label { display:block; margin-top:12px; font-weight:700; }
        input[type=text], input[type=number], textarea, select {
            width:100%; padding:8px; border:1px solid #ccc; border-radius:6px; margin-top:4px;
        }
        .size-stock { display:flex; gap:12px; align-items:center; margin-top:4px; }
        .size-stock input { width:60px; }
        button { margin-top:16px; padding:10px 14px; border:0; border-radius:8px; background:#000; color:#fff; cursor:pointer; }
        button:disabled { opacity:0.5; cursor:not-allowed; }
        .muted { font-size:13px; color:var(--muted); }
        .errors { color: #b91c1c; background:#fef2f2; padding:1rem; border-radius:6px; margin-bottom:1.5rem; border:1px solid #fecaca; }
        .success { color: var(--success); background:#f0f9f4; padding:1rem; border-radius:6px; margin-bottom:1.5rem; border:1px solid #bbf7d0; }
        
        .style-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }
        
        .style-chip {
            background: #f3f3f3;
            border: 1px solid #e6e6e6;
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
            background: #fafafa;
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
        
        .existing-media {
            margin: 10px 0;
        }
        
        .media-item {
            display: inline-block;
            margin: 5px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .media-item img {
            max-width: 100px;
            max-height: 100px;
            display: block;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 8px;
            display: inline-block;
        }
        
        .required::after {
            content: " *";
            color: var(--danger);
        }
    </style>
</head>
<body>
<div class="container">
    <h1><?= $id ? 'Edit Product' : 'Add Product' ?></h1>
    
    <?php if ($success_message): ?>
        <div class="success">
            <?= e($success_message) ?>
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

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        
        <label class="required">Category</label>
        <select name="category_id" required>
            <option value="">-- Select Category --</option>
            <?php foreach($categories as $c): ?>
                <option value="<?= $c['category_id'] ?>" <?= (!empty($product['category_id']) && $product['category_id']==$c['category_id'])?'selected':'' ?>>
                    <?= e($c['category_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="required">Product Name</label>
        <input type="text" name="name" value="<?= e($product['name'] ?? '') ?>" required />

        <label>Brand/Designer</label>
        <input type="text" name="brand" value="<?= e($product['brand'] ?? '') ?>" />

        <label>Description</label>
        <textarea name="description" rows="4"><?= e($product['description'] ?? '') ?></textarea>

        <label>Color</label>
        <input type="text" name="color" value="<?= e($product['color'] ?? '') ?>" />

        <label>Purchase Price (ZAR)</label>
        <input type="number" name="price" min="0" step="0.01" value="<?= e($product['price'] ?? '0') ?>" />

        <label class="required">Rental Price (ZAR)</label>
        <input type="number" name="rental_price" min="0" step="0.01" value="<?= e($product['rental_price'] ?? '0') ?>" required />

        <label>Dress Styles</label>
        <div class="style-chips" id="styleChips">
            <?php foreach ($styles as $style): ?>
            <div class="style-chip <?= in_array($style['style_id'], $selected_styles) ? 'selected' : '' ?>" 
                 data-style-id="<?= $style['style_id'] ?>">
                <?= e($style['style_name']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="dress_styles[]" id="selectedStyles" value="<?= implode(',', $selected_styles) ?>">

        <label for="custom_style">Custom Style (optional)</label>
        <input type="text" name="custom_style" id="custom_style" class="form-control" 
               placeholder="Add a custom style if not in the list above">

        <label class="required">Sizes & Stock</label>
        <div id="sizesContainer">
            <?php if (!empty($sizes)): ?>
                <?php foreach($sizes as $size): ?>
                    <div class="size-stock">
                        <input type="text" name="sizes[]" placeholder="S/M/L/XL" value="<?= e($size['size']) ?>" />
                        <input type="number" name="stocks[]" placeholder="Qty" min="0" value="<?= e($size['stock']) ?>" />
                        <button type="button" class="btn-danger" onclick="removeSize(this)">Remove</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="size-stock">
                    <input type="text" name="sizes[]" placeholder="S/M/L/XL" />
                    <input type="number" name="stocks[]" placeholder="Qty" min="0" />
                    <button type="button" class="btn-danger" onclick="removeSize(this)">Remove</button>
                </div>
            <?php endif; ?>
        </div>
        <button type="button" onclick="addSizeField()">Add Another Size</button>

        <label class="required">Main Image</label>
        <div class="file-upload">
            <input type="file" name="main_image" accept="image/*" <?= !$id ? 'required' : '' ?> />
            <div class="muted">First image will be used as primary display image</div>
        </div>
        
        <?php if ($id && !empty($product['image'])): ?>
            <div class="existing-media">
                <strong>Current Main Image:</strong>
                <div class="media-item">
                    <img src="../<?= e($product['image']) ?>" alt="Current main image">
                    <div class="muted"><?= basename($product['image']) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <label>Additional Images</label>
        <div class="file-upload">
            <input type="file" name="additional_images[]" accept="image/*" multiple />
            <div class="muted">You can select multiple additional images</div>
        </div>
        
        <?php if (!empty($gallery_images)): ?>
            <div class="existing-media">
                <strong>Current Additional Images:</strong>
                <?php foreach($gallery_images as $image): ?>
                    <div class="media-item">
                        <img src="../<?= e($image) ?>" alt="Additional image">
                        <div class="muted"><?= basename($image) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label>Product Video</label>
        <div class="file-upload">
            <input type="file" name="product_video" accept="video/*" />
            <div class="muted">Supported formats: MP4, MOV, AVI, WEBM</div>
        </div>
        
        <?php if (!empty($gallery_video)): ?>
            <div class="existing-media">
                <strong>Current Video:</strong>
                <div class="media-item">
                    <video width="200" controls>
                        <source src="../<?= e($gallery_video) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    <div class="muted"><?= basename($gallery_video) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit"><?= $id ? 'Update Product' : 'Add Product' ?></button>
            <a href="products_list.php" class="btn-secondary">Back to Products</a>
        </div>
    </form>
</div>

<script>
// Size field management
function addSizeField() {
    const container = document.getElementById('sizesContainer');
    const div = document.createElement('div');
    div.className = 'size-stock';
    div.innerHTML = `
        <input type="text" name="sizes[]" placeholder="S/M/L/XL" />
        <input type="number" name="stocks[]" placeholder="Qty" min="0" />
        <button type="button" class="btn-danger" onclick="removeSize(this)">Remove</button>
    `;
    container.appendChild(div);
}

function removeSize(button) {
    const container = document.getElementById('sizesContainer');
    if (container.children.length > 1) {
        button.parentElement.remove();
    } else {
        alert('You need at least one size!');
    }
}

// Style selection
const styleChips = document.querySelectorAll('.style-chip');
const selectedStylesInput = document.getElementById('selectedStyles');
let selectedStyles = selectedStylesInput.value ? selectedStylesInput.value.split(',') : [];

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

// File preview functionality
const mainImageInput = document.querySelector('input[name="main_image"]');
const additionalImagesInput = document.querySelector('input[name="additional_images[]"]');
const videoInput = document.querySelector('input[name="product_video"]');

function createPreview(file, container) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const previewItem = document.createElement('div');
        previewItem.className = 'file-preview-item';
        
        if (file.type.startsWith('image/')) {
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="Preview" style="max-width: 100px; max-height: 100px;">
                <div>${file.name}</div>
            `;
        } else if (file.type.startsWith('video/')) {
            previewItem.innerHTML = `
                <video width="200" controls>
                    <source src="${e.target.result}" type="${file.type}">
                </video>
                <div>${file.name}</div>
            `;
        }
        
        container.appendChild(previewItem);
    };
    reader.readAsDataURL(file);
}

mainImageInput.addEventListener('change', function() {
    const container = this.parentElement.querySelector('.file-preview') || (function() {
        const div = document.createElement('div');
        div.className = 'file-preview';
        this.parentElement.appendChild(div);
        return div;
    }.bind(this)());
    
    container.innerHTML = '';
    if (this.files.length > 0) {
        createPreview(this.files[0], container);
    }
});

additionalImagesInput.addEventListener('change', function() {
    const container = this.parentElement.querySelector('.file-preview') || (function() {
        const div = document.createElement('div');
        div.className = 'file-preview';
        this.parentElement.appendChild(div);
        return div;
    }.bind(this)());
    
    container.innerHTML = '';
    for (let file of this.files) {
        createPreview(file, container);
    }
});

videoInput.addEventListener('change', function() {
    const container = this.parentElement.querySelector('.file-preview') || (function() {
        const div = document.createElement('div');
        div.className = 'file-preview';
        this.parentElement.appendChild(div);
        return div;
    }.bind(this)());
    
    container.innerHTML = '';
    if (this.files.length > 0) {
        createPreview(this.files[0], container);
    }
});

// Ensure at least one size field exists
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('sizesContainer');
    if (container.children.length === 0) {
        addSizeField();
    }
});
</script>
</body>
</html>