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
                // Start transaction for product and styles
                $mysqli->begin_transaction();
                
                try {
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

                    // Handle dress styles
                    // First, remove existing styles for this product
                    $delete_styles_stmt = $mysqli->prepare("DELETE FROM product_styles WHERE product_id = ?");
                    $delete_styles_stmt->bind_param('i', $id);
                    $delete_styles_stmt->execute();
                    $delete_styles_stmt->close();

                    // Handle selected dress styles
                    $selected_styles = isset($_POST['dress_styles']) ? $_POST['dress_styles'] : [];
                    if (!empty($selected_styles)) {
                        foreach ($selected_styles as $style_id) {
                            $style_stmt = $mysqli->prepare("INSERT INTO product_styles (product_id, style_id) VALUES (?, ?)");
                            if ($style_stmt) {
                                $style_stmt->bind_param("ii", $id, $style_id);
                                $style_stmt->execute();
                                $style_stmt->close();
                            }
                        }
                    }

                    // Handle custom style - THIS IS THE FIXED PART
                    if (!empty(trim($_POST['custom_style'] ?? ''))) {
                        $custom_style = trim($_POST['custom_style']);
                        
                        // First check if this custom style already exists
                        $check_style = $mysqli->prepare("SELECT style_id FROM dress_styles WHERE style_name = ? AND is_custom = 1");
                        $check_style->bind_param("s", $custom_style);
                        $check_style->execute();
                        $style_result = $check_style->get_result();
                        
                        if ($style_result->num_rows > 0) {
                            // Style already exists, get its ID
                            $existing_style = $style_result->fetch_assoc();
                            $style_id = $existing_style['style_id'];
                        } else {
                            // Insert new custom style into dress_styles table
                            $style_stmt = $mysqli->prepare("INSERT INTO dress_styles (style_name, is_custom) VALUES (?, 1)");
                            $style_stmt->bind_param("s", $custom_style);
                            $style_stmt->execute();
                            $style_id = $style_stmt->insert_id;
                            $style_stmt->close();
                        }
                        $check_style->close();
                        
                        // Link the custom style to the product
                        $link_stmt = $mysqli->prepare("INSERT INTO product_styles (product_id, style_id) VALUES (?, ?)");
                        $link_stmt->bind_param("ii", $id, $style_id);
                        $link_stmt->execute();
                        $link_stmt->close();
                        
                        // Also add it to the selected styles so it shows up in the form
                        $selected_styles[] = $style_id;
                    }

                    // Log activity
                    $log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, ?, ?)");
                    $act = $id ? 'product_updated' : 'product_created';
                    $ctx = json_encode(['product_id'=>$id,'name'=>$name]);
                    $log->bind_param('iss', $_SESSION['admin_id'], $act, $ctx);
                    $log->execute();
                    
                    $mysqli->commit();
                    
                    header("Location: products_list.php");
                    exit;
                    
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $errors[] = "Error updating product: " . $e->getMessage();
                }
            }
        }
    }
}

// Load product if editing
$product = null;
$sizes = [];
$existing_images = [];
$selected_styles = [];
$custom_style_value = '';
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
    
    // Get existing dress styles for this product
    $style_stmt = $mysqli->prepare("
        SELECT ds.style_id, ds.style_name, ds.is_custom
        FROM dress_styles ds 
        JOIN product_styles ps ON ds.style_id = ps.style_id 
        WHERE ps.product_id = ?
    ");
    $style_stmt->bind_param('i', $id);
    $style_stmt->execute();
    $styles_result = $style_stmt->get_result();
    while ($style = $styles_result->fetch_assoc()) {
        $selected_styles[] = $style['style_id'];
        // If it's a custom style, store the name for the input field
        if ($style['is_custom'] == 1) {
            $custom_style_value = $style['style_name'];
        }
    }
    $style_stmt->close();
}

// Get categories
$catRes = $mysqli->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

// Get dress styles for dropdown (both standard and custom)
$stylesRes = $mysqli->query("SELECT * FROM dress_styles ORDER BY style_name");
$styles = $stylesRes ? $stylesRes->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Edit Product' : 'Add Product' ?> - Ozyde Admin</title>
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
        
        .required::after {
            content: " *";
            color: var(--danger);
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
        
        .style-chip.custom {
            background: #e8f5e8;
            border-color: #2fa46b;
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
        
        .upload-hint {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
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
        
        .muted {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 10px;
        }
        
        .errors {
            color: #b91c1c;
            background: #fef2f2;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 1px solid #fecaca;
        }
        
        /* Image preview styles */
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
        
        .custom-style-note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
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
            
            <!-- Category Selection with Tile/Chip Layout -->
            <div class="form-group">
                <label for="category_id" class="required">Category</label>
                <div class="category-chips" id="categoryChips">
                    <?php foreach($categories as $c): ?>
                        <div class="category-chip <?= (!empty($product['category_id']) && $product['category_id']==$c['category_id'])?'selected':'' ?>" 
                             data-category-id="<?= $c['category_id'] ?>">
                            <?= e($c['category_name']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="category_id" id="selectedCategory" value="<?= e($product['category_id'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="required">Product Name</label>
                <input type="text" name="name" class="form-control" value="<?= e($product['name'] ?? '') ?>" required />
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?= e($product['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Color</label>
                <input type="text" name="color" class="form-control" value="<?= e($product['color'] ?? '') ?>" />
            </div>

            <div class="form-group">
                <label class="required">Price (ZAR)</label>
                <input type="number" name="price" class="form-control" min="0" step="0.01" value="<?= e($product['price'] ?? '0') ?>" required />
            </div>

            <!-- Dress Styles Section -->
            <div class="form-group">
                <label>Dress Styles</label>
                <div class="style-chips" id="styleChips">
                    <?php foreach ($styles as $style): ?>
                        <div class="style-chip <?= in_array($style['style_id'], $selected_styles) ? 'selected' : '' ?> <?= $style['is_custom'] ? 'custom' : '' ?>" 
                             data-style-id="<?= $style['style_id'] ?>">
                            <?= e($style['style_name']) ?>
                            <?php if ($style['is_custom']): ?>
                                <span style="font-size:10px; margin-left:5px;">(custom)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="dress_styles[]" id="selectedStyles" value="<?= implode(',', $selected_styles) ?>">
                
                <div class="custom-style-note">
                    Selected styles will be saved with the product. You can also add a new custom style below.
                </div>
            </div>

            <div class="form-group">
                <label for="custom_style">Add Custom Style</label>
                <input type="text" name="custom_style" id="custom_style" class="form-control" 
                       placeholder="Enter a new style name (e.g., 'Bohemian', 'Vintage')" value="<?= e($custom_style_value) ?>">
                <div class="custom-style-note">
                    Enter a new style name and it will be created and linked to this product.
                </div>
            </div>

            <!-- Sizes & Stock Section -->
            <div class="form-group">
                <label>Sizes & Stock (Admin Only)</label>
                <div class="muted">Stock quantities are for admin reference only and won't be shown to customers.</div>
                <div id="sizesContainer">
                    <?php if (!empty($sizes)): ?>
                        <?php foreach($sizes as $size): ?>
                            <div class="size-row">
                                <input type="text" name="sizes[]" class="form-control" placeholder="S/M/L/XL" value="<?= e($size['size']) ?>" />
                                <input type="number" name="stocks[]" class="form-control" placeholder="Qty" min="0" value="<?= e($size['stock']) ?>" />
                                <button type="button" class="btn btn-danger" onclick="removeSize(this)">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="size-row">
                            <input type="text" name="sizes[]" class="form-control" placeholder="S/M/L/XL" />
                            <input type="number" name="stocks[]" class="form-control" placeholder="Qty" min="0" />
                            <button type="button" class="btn btn-danger" onclick="removeSize(this)">Remove</button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addSizeField()" style="margin-top: 10px;">Add Another Size</button>
            </div>

            <!-- Images Section -->
            <div class="form-group">
                <label>Images</label>
                <div class="file-upload" id="imageUploadArea">
                    <div class="file-input-wrapper">
                        <input type="file" name="images[]" accept="image/*" multiple id="imageInput" />
                        <p>📷 Click to upload or drag and drop</p>
                        <p class="file-info">You can select multiple images. First image will be primary.</p>
                        <p class="file-info">Maximum file size: 10MB • Supported formats: JPG, PNG, GIF</p>
                    </div>
                </div>
                
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
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $id ? 'Update Product' : 'Add Product' ?></button>
                <a href="products_list.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Category chip selection
        const categoryChips = document.querySelectorAll('.category-chip');
        const selectedCategoryInput = document.getElementById('selectedCategory');
        
        categoryChips.forEach(chip => {
            chip.addEventListener('click', () => {
                // Remove selected class from all chips
                categoryChips.forEach(c => c.classList.remove('selected'));
                // Add selected class to clicked chip
                chip.classList.add('selected');
                // Update the hidden input
                selectedCategoryInput.value = chip.getAttribute('data-category-id');
            });
        });

        // Dress style chip selection
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

        // Size field management
        function addSizeField() {
            const container = document.getElementById('sizesContainer');
            const newRow = document.createElement('div');
            newRow.className = 'size-row';
            newRow.innerHTML = `
                <input type="text" name="sizes[]" class="form-control" placeholder="S/M/L/XL" />
                <input type="number" name="stocks[]" class="form-control" placeholder="Qty" min="0" />
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

        // Image upload area drag and drop
        const imageUploadArea = document.getElementById('imageUploadArea');
        const imageInput = document.getElementById('imageInput');
        
        imageUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            imageUploadArea.classList.add('dragover');
        });
        
        imageUploadArea.addEventListener('dragleave', () => {
            imageUploadArea.classList.remove('dragover');
        });
        
        imageUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            imageUploadArea.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                imageInput.files = e.dataTransfer.files;
                updateImagePreview();
            }
        });

        // Image preview functionality
        function updateImagePreview() {
            const previewsContainer = document.getElementById('imagePreviews');
            previewsContainer.innerHTML = '';
            
            const files = imageInput.files;
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
        }

        imageInput.addEventListener('change', updateImagePreview);

        // Form validation
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const files = imageInput.files;
            
            // Check file sizes (max 10MB per image)
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > 10 * 1024 * 1024) {
                    e.preventDefault();
                    alert('File "' + files[i].name + '" is too large. Maximum size is 10MB per image.');
                    return;
                }
            }

            // Validate category selection
            if (!selectedCategoryInput.value) {
                e.preventDefault();
                alert('Please select a category.');
                return;
            }
        });

        // Initialize with proper size row management
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('sizesContainer');
            if (container.children.length === 0) {
                addSizeField();
            }
        });
    </script>
</body>
</html>