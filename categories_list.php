<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['_csrf'] ?? '')) $errors[] = 'Invalid token.';
    else {
        // FIXED: Use category_name instead of name
        $category_name = trim($_POST['category_name'] ?? '');
        
        // Your categories table doesn't have slug or description columns
        // Remove these lines since they don't exist in your schema
        // $slug = trim($_POST['slug'] ?? '') ?: strtolower(preg_replace('/[^a-z0-9]+/','-', $category_name));
        // $desc = trim($_POST['description'] ?? '');
        
        $id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        if ($category_name === '') $errors[] = 'Category name required.';
        if (!$errors) {
            if ($id) {
                // FIXED: Update to match your table structure (only category_name)
                $stmt = $mysqli->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
                $stmt->bind_param('si', $category_name, $id);
                $stmt->execute();
                $log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, 'updated_category', ?)");
                $ctx = json_encode(['category_id'=>$id,'category_name'=>$category_name]);
                $log->bind_param('is', $_SESSION['admin_id'], $ctx);
                $log->execute();
            } else {
                // FIXED: Insert to match your table structure (only category_name)
                $stmt = $mysqli->prepare("INSERT INTO categories (category_name) VALUES (?)");
                $stmt->bind_param('s', $category_name);
                $stmt->execute();
                $newId = $mysqli->insert_id;
                $log = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, 'created_category', ?)");
                $ctx = json_encode(['category_id'=>$newId,'category_name'=>$category_name]);
                $log->bind_param('is', $_SESSION['admin_id'], $ctx);
                $log->execute();
            }
            header('Location: categories_list.php');
            exit;
        }
    }
}

if (isset($_GET['delete'])) {
    if (!check_csrf($_GET['_csrf'] ?? '')) $errors[] = 'Invalid token.';
    else {
        $id = (int)$_GET['delete'];
        $stmt = $mysqli->prepare("DELETE FROM categories WHERE category_id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        header('Location: categories_list.php');
        exit;
    }
}

// FIXED: Use category_name instead of name in ORDER BY
$res = $mysqli->query("SELECT * FROM categories ORDER BY category_name ASC");
?>
<div class="card">
  <h3>Categories</h3>
  <?php if ($errors): ?><div style="color:#b91c1c"><?= e(implode('<br>', $errors)) ?></div><?php endif; ?>
  <table class="table">
    <thead><tr><th>Name</th><th>Products</th><th>Actions</th></tr></thead>
    <tbody>
      <?php while ($c = $res->fetch_assoc()):
        $cnt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM products WHERE category_id = ?");
        $cnt->bind_param('i', $c['category_id']);
        $cnt->execute();
        $row = $cnt->get_result()->fetch_assoc();
      ?>
      <tr>
        <!-- FIXED: Use category_name instead of name -->
        <td><?= e($c['category_name']) ?></td>
        <td><?= e($row['cnt'] ?? 0) ?></td>
        <td>
          <a href="categories_list.php?edit=<?= $c['category_id'] ?>">Edit</a> |
          <a href="?delete=<?= $c['category_id'] ?>&_csrf=<?= csrf() ?>" 
             onclick="return confirm('Delete this category?')">Delete</a>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <hr>
  <h4><?= isset($_GET['edit']) ? 'Edit' : 'Add' ?> Category</h4>
  <?php
    $edit = null;
    if (isset($_GET['edit'])) {
      $stmt = $mysqli->prepare("SELECT * FROM categories WHERE category_id = ? LIMIT 1");
      $id = (int)$_GET['edit']; $stmt->bind_param('i',$id); $stmt->execute(); $edit = $stmt->get_result()->fetch_assoc();
    }
  ?>
  <form method="post" action="">
    <input type="hidden" name="_csrf" value="<?= csrf() ?>">
    <?php if ($edit): ?><input type="hidden" name="category_id" value="<?= e($edit['category_id']) ?>"><?php endif; ?>
    
    <!-- FIXED: Use category_name instead of name -->
    <div class="form-row">
      <label>Category Name</label>
      <input type="text" name="category_name" value="<?= e($edit['category_name'] ?? '') ?>" required>
    </div>
    
    <!-- REMOVED: Slug and description fields since they don't exist in your table -->
    
    <div><button class="btn" type="submit">Save Category</button></div>
  </form>
</div>

<style>
.form-row {
    margin-bottom: 1rem;
}

.form-row label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #374151;
}

.form-row input[type="text"] {
    width: 100%;
    max-width: 400px;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 1rem;
}

.btn {
    background: #161c24ff;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0.75rem 1.5rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9rem;
}

.btn:hover {
    background: #0a0d12ff;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.5rem;
}

.table th,
.table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.table th {
    background: #f8fafc;
    font-weight: 600;
    color: #374151;
}

.card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>