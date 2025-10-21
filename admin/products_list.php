<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

// categories for filter
$catRes = $mysqli->query("SELECT category_id, category_name FROM categories");
$categories = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

// handle delete
if (isset($_GET['delete'])) {
    if (!check_csrf($_GET['_csrf'] ?? '')) {
        echo "<div class='card' style='color:red;'>Invalid CSRF token.</div>";
    } else {
        $id = (int)$_GET['delete'];
        $stmt = $mysqli->prepare("DELETE FROM products WHERE product_id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            header('Location: products_list.php?deleted=1');
            exit;
        } else {
            echo "<div class='card' style='color:red;'>Error deleting product.</div>";
        }
    }
}

// Show success message
if (isset($_GET['deleted'])) {
    echo "<div class='card' style='color:green;'>Product deleted successfully.</div>";
}

// filters
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$is_rental = isset($_GET['is_rental']) ? (int)$_GET['is_rental'] : -1;
$search = isset($_GET['search']) ? trim($mysqli->real_escape_string($_GET['search'])) : '';

// Build WHERE clause
$where = "1=1";
$params = [];
$types = '';

if ($category > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category;
    $types .= 'i';
}

if ($is_rental !== -1) {
    $where .= " AND p.is_rental = ?";
    $params[] = $is_rental;
    $types .= 'i';
}

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Fixed SQL query to match your database schema
$sql = "SELECT p.product_id, p.name, p.price, p.stock, p.is_rental, p.image, 
               p.description, p.color, p.created_at,
               c.category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE $where
        ORDER BY p.created_at DESC
        LIMIT 500";

// Prepare and execute query with parameters
$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

// Count total products
$count_sql = "SELECT COUNT(*) as total FROM products p WHERE $where";
$count_stmt = $mysqli->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result()->fetch_assoc();
$total_products = $count_result['total'];
?>
<div class="card">
  <h3>Products (<?php echo $total_products; ?> total)</h3>
  
  <!-- Search and Filter Form -->
  <form method="get" style="display:flex;gap:12px;align-items:end;margin-bottom:12px;flex-wrap:wrap;">
    <div>
      <label>Search</label>
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Product name or description">
    </div>
    <div>
      <label>Category</label>
      <select name="category">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?php echo $c['category_id']; ?>" <?php echo ($category == $c['category_id']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($c['category_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Type</label>
      <select name="is_rental">
        <option value="-1" <?php echo $is_rental==-1?'selected':''; ?>>All Types</option>
        <option value="0" <?php echo $is_rental==0?'selected':''; ?>>For Sale</option>
        <option value="1" <?php echo $is_rental==1?'selected':''; ?>>Rental</option>
      </select>
    </div>
    <div>
      <button class="btn" type="submit">Filter</button>
      <a class="btn" href="products_list.php" style="background:#6b7280;">Clear</a>
    </div>
    <div style="margin-left:auto; display:flex; gap:8px;">
      <a class="btn" href="product_edit.php" style="background:#059669;">+ Add Product</a>
      <a class="btn" href="products_export_csv.php" style="background:#0369a1;">Export CSV</a>
      <a class="btn" href="products_import_csv.php" style="background:#7c3aed;">Import CSV</a>
    </div>
  </form>

  <!-- Bulk Stock Form -->
  <form method="post" action="bulk_stock_update.php" id="bulkStockForm">
    <input type="hidden" name="_csrf" value="<?php echo csrf(); ?>">
    
    <?php if ($res->num_rows > 0): ?>
      <table class="table">
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll"></th>
            <th>ID</th>
            <th>Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Type</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($p = $res->fetch_assoc()):
            // Get primary image for product
            $img_query = "SELECT filename FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1";
            $img_stmt = $mysqli->prepare($img_query);
            $img_stmt->bind_param('i', $p['product_id']);
            $img_stmt->execute();
            $img_result = $img_stmt->get_result();
            $primary_image = $img_result->fetch_assoc();
            $img_stmt->close();
            
            // Use primary image if available, otherwise use product image field
            $image_to_use = $primary_image['filename'] ?? $p['image'];
            
            // FIX: Remove "gallery/" prefix if it exists in the filename
            if (!empty($image_to_use) && strpos($image_to_use, 'gallery/') === 0) {
                $image_to_use = str_replace('gallery/', '', $image_to_use);
            }
            
            // CORRECTED PATH: pointing to gallery folder (one level up from admin)
            $imgSrc = !empty($image_to_use) ? '../gallery/' . $image_to_use : '../images/placeholder.png';
            
            $productType = $p['is_rental'] ? '<span style="color:#dc2626;">Rental</span>' : '<span style="color:#059669;">Sale</span>';
          ?>
            <tr>
              <td><input type="checkbox" name="product_ids[]" value="<?php echo htmlspecialchars($p['product_id']); ?>" class="product-checkbox"></td>
              <td><?php echo htmlspecialchars($p['product_id']); ?></td>
              <td>
                <div style="position:relative; display:inline-block;">
                  <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                       style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #ddd;"
                       onerror="this.src='../images/placeholder.png'"
                       alt="<?php echo htmlspecialchars($p['name']); ?>"
                       title="<?php echo htmlspecialchars($p['name']); ?>">
                  <?php if (empty($image_to_use)): ?>
                    <div style="position:absolute;top:-5px;right:-5px;background:#ef4444;color:white;border-radius:50%;width:16px;height:16px;font-size:10px;display:flex;align-items:center;justify-content:center;" title="No image uploaded">!</div>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                <?php if (!empty($p['color'])): ?>
                  <br><small style="color:#6b7280;">Color: <?php echo htmlspecialchars($p['color']); ?></small>
                <?php endif; ?>
                <?php if (!empty($p['description'])): ?>
                  <br><small style="color:#6b7280;"><?php echo htmlspecialchars(substr($p['description'], 0, 50)); ?>...</small>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?></td>
              <td><strong>R<?php echo number_format($p['price'], 2); ?></strong></td>
              <td>
                <input type="number" 
                       name="stock[<?php echo htmlspecialchars($p['product_id']); ?>]" 
                       value="<?php echo htmlspecialchars($p['stock']); ?>" 
                       style="width:80px;padding:4px;"
                       min="0">
              </td>
              <td><?php echo $productType; ?></td>
              <td><small><?php echo date('M j, Y', strtotime($p['created_at'])); ?></small></td>
              <td>
                <a href="product_edit.php?id=<?php echo htmlspecialchars($p['product_id']); ?>" style="color:#0369a1;">Edit</a> |
                <a href="?delete=<?php echo htmlspecialchars($p['product_id']); ?>&_csrf=<?php echo csrf(); ?>" 
                   onclick="return confirm('Are you sure you want to delete <?php echo addslashes($p['name']); ?>?')"
                   style="color:#dc2626;">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <!-- Bulk Actions -->
      <div style="margin-top:20px;padding:15px;background:#f8fafc;border-radius:8px;">
        <h4>Bulk Stock Management</h4>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <div>
            <label>Set selected products to stock:</label>
            <input type="number" name="set_stock" placeholder="e.g., 10" min="0" style="width:100px;">
          </div>
          <button class="btn" type="submit" name="action" value="set" style="background:#dc2626;">
            Apply to Selected
          </button>
          <button class="btn" type="submit" name="action" value="update_individual" style="background:#059669;">
            Save All Individual Stock Values
          </button>
          <small style="color:#6b7280;margin-left:auto;">
            Checkboxes for bulk actions, individual fields for precise control
          </small>
        </div>
      </div>

    <?php else: ?>
      <div style="text-align:center;padding:40px;color:#6b7280;">
        <h4>No products found</h4>
        <p>No products match your current filters.</p>
        <a href="products_list.php" class="btn">View All Products</a>
        <a href="product_edit.php" class="btn" style="background:#059669;">+ Add Your First Product</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<script>
// Select all checkboxes functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Confirm before bulk actions
document.getElementById('bulkStockForm').addEventListener('submit', function(e) {
    const action = e.submitter?.value;
    const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
    
    if (action === 'set' && checkedBoxes.length === 0) {
        alert('Please select at least one product for bulk action.');
        e.preventDefault();
        return;
    }
    
    if (action === 'set') {
        const setStock = document.querySelector('input[name="set_stock"]').value;
        if (setStock === '') {
            alert('Please enter a stock value for bulk update.');
            e.preventDefault();
            return;
        }
        if (!confirm(`Set stock to ${setStock} for ${checkedBoxes.length} selected products?`)) {
            e.preventDefault();
        }
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>