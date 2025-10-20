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
        $total_stock = array_sum(array_map('intval', $stocks));

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
            
            // Handle main image (first image becomes primary)
            if (isset($_FILES['images']['name'][0]) && $_FILES['images']['error'][0] === UPLOAD_ERR_OK) {
                $image_name = uniqid() . '_' . basename($_FILES['images']['name'][0]);
                $target = $uploadDir . $image_name;
                
                if (move_uploaded_file($_FILES['images']['tmp_name'][0], $target)) {
                    $uploaded_images[] = 'gallery/' . $image_name;
                } else {
                    $errors[] = "Failed to upload main image.";
                }
            }
            
            // Handle additional images
            if (!empty($_FILES['images']['name'])) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($key === 0) continue; // Skip first image (already processed)
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK && !empty($tmp_name)) {
                        $image_name = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                        $target = $uploadDir . $image_name;
                        
                        if (move_uploaded_file($tmp_name, $target)) {
                            $uploaded_images[] = 'gallery/' . $image_name;
                        }
                    }
                }
            }
            
            // Use first image as primary, or keep existing if no new images
            $image_path = !empty($uploaded_images) ? $uploaded_images[0] : ($product['image'] ?? '');

            if (empty($errors)) {
                if ($id) {
                    // Update existing product
                    if (!empty($uploaded_images)) {
                        $stmt = $mysqli->prepare("UPDATE products SET category_id=?, name=?, description=?, size=?, color=?, price=?, image=?, stock=? WHERE product_id=?");
                        $stmt->bind_param('issssdsii', $category_id, $name, $description, $size_str, $color, $price, $image_path, $total_stock, $id);
                    } else {
                        $stmt = $mysqli->prepare("UPDATE products SET category_id=?, name=?, description=?, size=?, color=?, price=?, stock=? WHERE product_id=?");
                        $stmt->bind_param('isssdii', $category_id, $name, $description, $size_str, $color, $price, $total_stock, $id);
                    }
                    $stmt->execute();
                } else {
                    // Insert new product - all products are rentals (is_rental=1)
                    $stmt = $mysqli->prepare("INSERT INTO products (category_id, name, description, size, color, price, image, stock, is_rental) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->bind_param('issssdsi', $category_id, $name, $description, $size_str, $color, $price, $image_path, $total_stock);
                    $stmt->execute();
                    $id = $mysqli->insert_id;
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

    <form method="POST" enctype="multipart/form-data">
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

        <label>Sizes & Stock</label>
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
        <input type="file" name="images[]" accept="image/*" multiple />
        <div class="muted">You can select multiple images. First image will be used as primary.</div>
        
        <?php if ($id && !empty($product['image'])): ?>
            <div style="margin-top:8px;">
                <img src="../<?= e($product['image']) ?>" alt="Current image" style="max-width:200px; height:auto; border:1px solid #ddd; border-radius:4px;">
                <div class="muted">Current primary image</div>
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
</script>
</body>
</html>