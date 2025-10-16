<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/image_helpers.php';
require_once __DIR__ . '/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$uploadDir = __DIR__ . '/uploads/products';
$publicPath = 'uploads/products';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Create symlink to make uploads accessible from root if needed
$rootUploads = __DIR__ . '/../uploads';
if (!is_dir($rootUploads)) {
    symlink(__DIR__ . '/uploads', $rootUploads);
}

// FIXED: Use category_name instead of name
$catRes = $mysqli->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

// Load existing sizes if editing
$sizes = [];
if ($id) {
    $sizeRes = $mysqli->query("SELECT * FROM product_sizes WHERE product_id = $id ORDER BY size");
    $sizes = $sizeRes ? $sizeRes->fetch_all(MYSQLI_ASSOC) : [];
}

// handle image delete / set primary actions
if (isset($_GET['delete_image']) && $id) {
    $imgId = (int)$_GET['delete_image'];
    if (!check_csrf($_GET['_csrf'] ?? '')) $errors[] = 'Invalid CSRF token.';
    else {
        $stmt = $mysqli->prepare("SELECT filename, thumb_filename FROM product_images WHERE image_id = ? AND product_id = ?");
        $stmt->bind_param('ii', $imgId, $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            if (!empty($row['filename']) && file_exists($uploadDir . '/' . $row['filename'])) unlink($uploadDir . '/' . $row['filename']);
            if (!empty($row['thumb_filename']) && file_exists($uploadDir . '/' . $row['thumb_filename'])) unlink($uploadDir . '/' . $row['thumb_filename']);
            $del = $mysqli->prepare("DELETE FROM product_images WHERE image_id = ? LIMIT 1");
            $del->bind_param('i', $imgId);
            $del->execute();
            $mysqli->query("UPDATE product_images SET is_primary=1 WHERE product_id = {$id} ORDER BY created_at DESC LIMIT 1");
            header("Location: product_edit.php?id={$id}");
            exit;
        }
    }
}

if (isset($_GET['set_primary']) && $id) {
    $imgId = (int)$_GET['set_primary'];
    if (check_csrf($_GET['_csrf'] ?? '')) {
        $stmt = $mysqli->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        $stmt = $mysqli->prepare("UPDATE product_images SET is_primary = 1 WHERE image_id = ? AND product_id = ?");
        $stmt->bind_param('ii', $imgId, $id); $stmt->execute();
        header("Location: product_edit.php?id={$id}"); exit;
    } else $errors[] = 'Invalid CSRF token.';
}

// create/update product and handle images
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['_csrf'] ?? '')) $errors[] = 'Invalid CSRF token.';
    else {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $is_rental = isset($_POST['is_rental']) ? 1 : 0;
        $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
        $color = trim($_POST['color'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        
        if ($name === '' || $price <= 0) $errors[] = "Provide name and price.";
        if (empty($errors)) {
            if ($id) {
                $stmt = $mysqli->prepare("UPDATE products SET name=?,description=?,price=?,stock=?,is_rental=?,category_id=?,color=?,sku=? WHERE product_id=?");
                $stmt->bind_param('ssdiiissi', $name, $description, $price, $stock, $is_rental, $category_id, $color, $sku, $id);
                $stmt->execute();
            } else {
                $stmt = $mysqli->prepare("INSERT INTO products (name, description, price, stock, is_rental, category_id, color, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssdiiiss', $name, $description, $price, $stock, $is_rental, $category_id, $color, $sku);
                $stmt->execute();
                $id = $mysqli->insert_id;
            }
            
            // Handle sizes
            if ($id) {
                // Delete existing sizes
                $mysqli->query("DELETE FROM product_sizes WHERE product_id = $id");
                
                // Insert new sizes
                if (!empty($_POST['sizes']) && !empty($_POST['size_stocks'])) {
                    $sizeStmt = $mysqli->prepare("INSERT INTO product_sizes (product_id, size, stock) VALUES (?, ?, ?)");
                    foreach ($_POST['sizes'] as $index => $size) {
                        $size = trim($size);
                        $sizeStock = (int)($_POST['size_stocks'][$index] ?? 0);
                        if (!empty($size)) {
                            $sizeStmt->bind_param('isi', $id, $size, $sizeStock);
                            $sizeStmt->execute();
                        }
                    }
                }
            }
            
            // images upload handling (multiple)
            if (!empty($_FILES['images'])) {
                foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['images']['error'][$k] !== UPLOAD_ERR_OK) continue;
                    
                    $file = [
                        'name' => $_FILES['images']['name'][$k],
                        'tmp_name' => $_FILES['images']['tmp_name'][$k],
                        'size' => $_FILES['images']['size'][$k],
                        'error' => $_FILES['images']['error'][$k],
                        'type' => $_FILES['images']['type'][$k],
                    ];
                    $validation = validate_image_upload($file);
                    if ($validation === 'nofile') continue;
                    if ($validation !== null) { $errors[] = $file['name'] . ': ' . $validation; continue; }
                    
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                    $ext = $extMap[$mime] ?? 'jpg';
                    $safeName = make_safe_filename($file['name'], $ext);
                    $target = $uploadDir . '/' . $safeName;
                    
                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        $thumbName = 'thumb-' . $safeName;
                        $thumbPath = $uploadDir . '/' . $thumbName;
                        $thumbCreated = create_thumbnail($target, $thumbPath, 400, 400);
                        
                        // Check if we already have a primary image
                        $primaryCheck = $mysqli->query("SELECT COUNT(*) AS cnt FROM product_images WHERE product_id = {$id} AND is_primary = 1");
                        $hasPrimary = $primaryCheck->fetch_assoc()['cnt'] > 0;
                        $isPrimary = $hasPrimary ? 0 : 1;
                        
                        $stmt = $mysqli->prepare("INSERT INTO product_images (product_id, filename, thumb_filename, is_primary) VALUES (?, ?, ?, ?)");
                        $thumbForDb = $thumbCreated ? $thumbName : $safeName;
                        $stmt->bind_param('issi', $id, $safeName, $thumbForDb, $isPrimary);
                        $stmt->execute();
                    } else {
                        $errors[] = 'Failed to move uploaded file for ' . e($file['name']);
                    }
                }
            }
            
            if (empty($errors)) {
                // log activity
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

// load product
$product = null;
if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM products WHERE product_id = ? LIMIT 1");
    $stmt->bind_param('i', $id); 
    $stmt->execute(); 
    $product = $stmt->get_result()->fetch_assoc();
}

// load images (order by sort_order)
$images = [];
if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, created_at DESC");
    $stmt->bind_param('i', $id); 
    $stmt->execute(); 
    $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<style>
.product-form {
    max-width: 1200px;
    margin: 0 auto;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.form-section {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.form-section h4 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #1e293b;
    border-bottom: 2px solid #14171cff;
    padding-bottom: 0.5rem;
}

.form-row {
    margin-bottom: 1.25rem;
}

.form-row label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.form-row input[type="text"],
.form-row input[type="number"],
.form-row select,
.form-row textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.2s;
    background: white;
}

.form-row input[type="text"]:focus,
.form-row input[type="number"]:focus,
.form-row select:focus,
.form-row textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-row textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.checkbox-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox-row input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;
}

.size-row {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 0.75rem;
    align-items: end;
    margin-bottom: 0.75rem;
}

.size-row input {
    margin-bottom: 0;
}

.remove-size {
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    font-size: 0.8rem;
}

.remove-size:hover {
    background: #dc2626;
}

.add-size {
    background: #10b981;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0.5rem 1rem;
    cursor: pointer;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.add-size:hover {
    background: #059669;
}

.image-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.image-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    transition: all 0.2s;
    cursor: move;
}

.image-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.image-card img {
    max-width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 4px;
    margin-bottom: 0.75rem;
}

.image-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.image-actions a {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.primary-btn {
    background: #10b981;
    color: white;
}

.primary-btn:hover {
    background: #059669;
}

.delete-btn {
    background: #ef4444;
    color: white;
}

.delete-btn:hover {
    background: #dc2626;
}

.primary-badge {
    background: #10b981;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
}

.btn-primary {
    background: #1b1f26ff;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #03e343ff;
}

.btn-secondary {
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: #4b5563;
}

.help-text {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .size-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<div class="card product-form">
  <h3><?= $id ? 'Edit Product' : 'Add New Product' ?></h3>
  
  <?php if ($errors): ?>
    <div style="color:#b91c1c; background:#fef2f2; padding:1rem; border-radius:6px; margin-bottom:1.5rem; border:1px solid #fecaca;">
      <strong>Please fix the following errors:</strong>
      <?php foreach ($errors as $error): ?>
        <div style="margin-top:0.5rem;">• <?= e($error) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= csrf() ?>">
    
    <div class="form-grid">
      <!-- Basic Information -->
      <div class="form-section">
        <h4>Basic Information</h4>
        
        <div class="form-row">
          <label for="name">Product Name *</label>
          <input type="text" id="name" name="name" value="<?= e($product['name'] ?? '') ?>" required>
        </div>
        
        <div class="form-row">
          <label for="sku">SKU</label>
          <input type="text" id="sku" name="sku" value="<?= e($product['sku'] ?? '') ?>" placeholder="PROD-001">
          <div class="help-text">Unique product identifier</div>
        </div>
        
        <div class="form-row">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id">
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['category_id'] ?>" <?= (!empty($product['category_id']) && $product['category_id']==$c['category_id'])?'selected':'' ?>>
                <?= e($c['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-row">
          <label for="price">Price (ZAR) *</label>
          <input type="number" id="price" step="0.01" name="price" value="<?= e($product['price'] ?? '') ?>" required>
        </div>
        
        <div class="form-row">
          <label for="stock">Default Stock</label>
          <input type="number" id="stock" name="stock" value="<?= e($product['stock'] ?? 0) ?>">
          <div class="help-text">Overall stock quantity (can be overridden by sizes)</div>
        </div>
      </div>

      <!-- Additional Details -->
      <div class="form-section">
        <h4>Additional Details</h4>
        
        <div class="form-row">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Product description..."><?= e($product['description'] ?? '') ?></textarea>
        </div>
        
        <div class="form-row">
          <label for="color">Color</label>
          <input type="text" id="color" name="color" value="<?= e($product['color'] ?? '') ?>" placeholder="e.g., Red, Blue, Black">
        </div>
        
        <div class="form-row checkbox-row">
          <input type="checkbox" id="is_rental" name="is_rental" <?= (!empty($product['is_rental']) ? 'checked' : '') ?>>
          <label for="is_rental" style="margin:0;">This is a rental product</label>
        </div>
        
        <div class="form-row">
          <label>Product Images</label>
          <input type="file" name="images[]" accept="image/*" multiple>
          <div class="help-text">JPEG, PNG, or WebP (max 5MB per file)</div>
        </div>
      </div>
    </div>

    <!-- Size Management -->
    <div class="form-section">
      <h4>Size & Stock Management</h4>
      <p class="help-text">Add different sizes with their individual stock quantities. Leave empty if product doesn't have sizes.</p>
      
      <div id="sizes-container">
        <?php if (!empty($sizes)): ?>
          <?php foreach ($sizes as $index => $size): ?>
            <div class="size-row">
              <div>
                <label>Size</label>
                <input type="text" name="sizes[]" value="<?= e($size['size']) ?>" placeholder="e.g., S, M, L, XL">
              </div>
              <div>
                <label>Stock</label>
                <input type="number" name="size_stocks[]" value="<?= e($size['stock']) ?>" min="0" placeholder="Quantity">
              </div>
              <button type="button" class="remove-size" onclick="removeSize(this)">Remove</button>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="size-row">
            <div>
              <label>Size</label>
              <input type="text" name="sizes[]" placeholder="e.g., S, M, L, XL">
            </div>
            <div>
              <label>Stock</label>
              <input type="number" name="size_stocks[]" min="0" placeholder="Quantity">
            </div>
            <button type="button" class="remove-size" onclick="removeSize(this)">Remove</button>
          </div>
        <?php endif; ?>
      </div>
      
      <button type="button" class="add-size" onclick="addSize()">+ Add Another Size</button>
    </div>

    <!-- Image Gallery -->
    <?php if (!empty($images)): ?>
      <div class="form-section">
        <h4>Product Images</h4>
        <p class="help-text">Drag and drop to reorder images. First image will be used as primary.</p>
        
        <div id="imageGallery" class="image-gallery">
          <?php foreach ($images as $img):
            $thumb = !empty($img['thumb_filename']) ? $publicPath . '/' . $img['thumb_filename'] : $publicPath . '/' . $img['filename'];
          ?>
            <div class="image-card" data-image-id="<?= $img['image_id'] ?>" draggable="true">
              <img src="<?= e($thumb) ?>" alt="Product Image" onerror="this.src='../images/placeholder.png'">
              <div class="image-actions">
                <?php if ($img['is_primary']): ?>
                  <span class="primary-badge">Primary</span>
                <?php else: ?>
                  <a href="?id=<?= $id ?>&set_primary=<?= $img['image_id'] ?>&_csrf=<?= csrf() ?>" class="primary-btn">
                    Set as Primary
                  </a>
                <?php endif; ?>
                <a href="?id=<?= $id ?>&delete_image=<?= $img['image_id'] ?>&_csrf=<?= csrf() ?>" 
                   class="delete-btn"
                   onclick="return confirm('Are you sure you want to delete this image?')">
                  Delete
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <button type="submit" class="btn-primary">
        <?= $id ? 'Update Product' : 'Create Product' ?>
      </button>
      <a href="products_list.php" class="btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>
// Size Management
function addSize() {
    const container = document.getElementById('sizes-container');
    const newSizeRow = document.createElement('div');
    newSizeRow.className = 'size-row';
    newSizeRow.innerHTML = `
        <div>
            <label>Size</label>
            <input type="text" name="sizes[]" placeholder="e.g., S, M, L, XL">
        </div>
        <div>
            <label>Stock</label>
            <input type="number" name="size_stocks[]" min="0" placeholder="Quantity">
        </div>
        <button type="button" class="remove-size" onclick="removeSize(this)">Remove</button>
    `;
    container.appendChild(newSizeRow);
}

function removeSize(button) {
    const row = button.closest('.size-row');
    if (document.querySelectorAll('.size-row').length > 1) {
        row.remove();
    } else {
        // If it's the last row, just clear the inputs
        row.querySelectorAll('input').forEach(input => input.value = '');
    }
}

// Image Drag & Drop Reordering
const gallery = document.getElementById('imageGallery');
if (gallery) {
    let draggedItem = null;

    gallery.addEventListener('dragstart', function(e) {
        draggedItem = e.target.closest('.image-card');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', draggedItem.innerHTML);
    });

    gallery.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });

    gallery.addEventListener('drop', function(e) {
        e.preventDefault();
        const target = e.target.closest('.image-card');
        
        if (target && target !== draggedItem) {
            const allItems = Array.from(gallery.children);
            const draggedIndex = allItems.indexOf(draggedItem);
            const targetIndex = allItems.indexOf(target);
            
            if (draggedIndex < targetIndex) {
                target.parentNode.insertBefore(draggedItem, target.nextSibling);
            } else {
                target.parentNode.insertBefore(draggedItem, target);
            }
            
            saveImageOrder();
        }
    });

    function saveImageOrder() {
        const order = Array.from(gallery.children).map((item, index) => ({
            id: item.dataset.imageId,
            pos: index
        }));
        
        fetch('update_image_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order: order,
                csrf: '<?= csrf() ?>',
                product_id: <?= $id ?>
            })
        }).then(r => r.json()).then(data => {
            if (!data.ok) {
                console.error('Failed to save image order');
            }
        }).catch(error => {
            console.error('Error saving order:', error);
        });
    }
}

// Add some interactivity to form inputs
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>