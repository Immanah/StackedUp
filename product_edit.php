<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// Create/update product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $color = trim($_POST['color'] ?? '');
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

        if ($name === '' || $price <= 0) {
            $errors[] = "Provide name and price.";
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
                    // Update existing product
                    $stmt = $mysqli->prepare("UPDATE products SET category_id=?, name=?, description=?, size=?, color=?, price=? WHERE product_id=?");
                    $stmt->bind_param('isssdsi', $category_id, $name, $description, $size_str, $color, $price, $id);
                    $stmt->execute();
                    
                    // Only delete existing images if new ones are uploaded
                    if (!empty($uploaded_images)) {
                        $delete_stmt = $mysqli->prepare("DELETE FROM product_images WHERE product_id = ?");
                        $delete_stmt->bind_param('i', $id);
                        $delete_stmt->execute();
                        $delete_stmt->close();
                    }
                } else {
                    // Insert new product - all products are rentals (is_rental=1)
                    $stmt = $mysqli->prepare("INSERT INTO products (category_id, name, description, size, color, price, is_rental) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->bind_param('issssd', $category_id, $name, $description, $size_str, $color, $price);
                    $stmt->execute();
                    $id = $mysqli->insert_id;
                }

                // Insert all images into product_images table
                if (!empty($uploaded_images)) {
                    foreach ($uploaded_images as $index => $image_path) {
                        $is_primary = ($index === 0) ? 1 : 0; // First image is primary
                        $img_stmt = $mysqli->prepare("INSERT INTO product_images (product_id, filename, is_primary) VALUES (?, ?, ?)");
                        $img_stmt->bind_param('isi', $id, $image_path, $is_primary);
                        $img_stmt->execute();
                        $img_stmt->close();
                    }
                    
                    // Update products table with the primary image
                    if (!empty($uploaded_images[0])) {
                        $update_stmt = $mysqli->prepare("UPDATE products SET image = ? WHERE product_id = ?");
                        $update_stmt->bind_param('si', $uploaded_images[0], $id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                }

                // Log activity
                $log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, ?, ?)");
                $act = $id ? 'product_updated' : 'product_created';
                $ctx = json_encode(['product_id'=>$id,'name'=>$name]);
                $log->bind_param('iss', $_SESSION['admin_id'], $act, $ctx);
                $log->execute();
                
                header("Location: products_list.php");
                exit;
            }
        }
    }
}

// Load product if editing
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
    
    // Get existing product images
    $img_stmt = $mysqli->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
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
        button { margin-top:16px; padding:10px 14px; border:0; border-radius:8px; background:#000; color:#fff; cursor:pointer; }
        button:disabled { opacity:0.5; cursor:not-allowed; }
        .muted { font-size:13px; color:var(--muted); }
        .errors { color: #b91c1c; background:#fef2f2; padding:1rem; border-radius:6px; margin-bottom:1.5rem; border:1px solid #fecaca; }
        
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
        .existing-images {
            margin-top: 15px;
        }
        .existing-images h4 {
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--muted);
        }
    </style>
</head>
<body>
<main>
    <h1><?= $id ? 'Edit Product' : 'Add Product' ?></h1>
    
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
        <input type="text" name="color" value="<?= e($product['color'] ?? '') ?>" />

        <label>Price (ZAR) *</label>
        <input type="number" name="price" min="0" step="0.01" value="<?= e($product['price'] ?? '0') ?>" required />

        <label>Sizes & Stock (Admin Only)</label>
        <div class="muted">Stock quantities are for admin reference only and won't be shown to customers.</div>
        <div id="sizesContainer">
            <?php if (!empty($sizes)): ?>
                <?php foreach($sizes as $size): ?>
                    <div class="size-stock">
                        <input type="text" name="sizes[]" placeholder="S/M/L/XL" value="<?= e($size['size']) ?>" />
                        <input type="number" name="stocks[]" placeholder="Qty" min="0" value="<?= e($size['stock']) ?>" />
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="size-stock">
                    <input type="text" name="sizes[]" placeholder="S/M/L/XL" />
                    <input type="number" name="stocks[]" placeholder="Qty" min="0" />
                </div>
            <?php endif; ?>
        </div>
        <button type="button" onclick="addSizeField()">Add Another Size</button>

        <label>Images</label>
        <input type="file" name="images[]" accept="image/*" multiple id="imageInput" />
        <div class="muted">You can select multiple images. First image will be used as primary. Allowed formats: JPG, PNG, GIF. Max 10MB per image.</div>
        
        <!-- Image previews for new uploads -->
        <div class="image-previews" id="imagePreviews"></div>
        
        <!-- Existing images display -->
        <?php if ($id && !empty($existing_images)): ?>
            <div class="existing-images">
                <h4>Current Images (new uploads will replace these):</h4>
                <div class="image-previews">
                    <?php foreach ($existing_images as $image): ?>
                        <div class="image-preview">
                            <img src="../<?= e($image['filename']) ?>" alt="Product image" onerror="this.src='../images/placeholder.png'">
                            <?php if ($image['is_primary']): ?>
                                <div class="primary-badge">Primary</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($id && !empty($product['image'])): ?>
            <div class="existing-images">
                <h4>Current Primary Image (new uploads will replace this):</h4>
                <div class="image-previews">
                    <div class="image-preview">
                        <img src="../<?= e($product['image']) ?>" alt="Current image">
                        <div class="primary-badge">Primary</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <button type="submit"><?= $id ? 'Update Product' : 'Add Product' ?></button>
        <a href="products_list.php" style="margin-left:12px; color:#666; text-decoration:none;">Cancel</a>
    </form>
</main>
<script>
function addSizeField() {
    const div = document.createElement('div');
    div.className = 'size-stock';
    div.innerHTML = '<input type="text" name="sizes[]" placeholder="S/M/L/XL" /><input type="number" name="stocks[]" placeholder="Qty" min="0" />';
    document.getElementById('sizesContainer').appendChild(div);
}

// Image preview functionality
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

// Form validation
document.getElementById('productForm').addEventListener('submit', function(e) {
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
});
</script>
</body>
</html>
