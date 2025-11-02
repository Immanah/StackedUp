<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['_csrf'] ?? '')) $errors[] = 'Invalid CSRF token.';
    else {
        if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) $errors[] = 'Upload failed.';
        else {
            $tmp = $_FILES['csv']['tmp_name'];
            if (($handle = fopen($tmp,'r')) !== false) {
                $header = fgetcsv($handle);
                // expect header as exported format
                $map = array_flip($header ?: []);
                $updated = $created = 0;
                while (($data = fgetcsv($handle)) !== false) {
                    $row = [];
                    foreach ($map as $col => $idx) $row[$col] = $data[$idx] ?? null;
                    $pid = (int)($row['product_id'] ?? 0);
                    if ($pid) {
                        $stmt = $mysqli->prepare("UPDATE products SET name=?, sku=?, price=?, stock=?, is_rental=?, category_id=? WHERE product_id=?");
                        $stmt->bind_param('ssdiiii', $row['name'], $row['sku'], $row['price'], $row['stock'], $row['is_rental'], $row['category_id'], $pid);
                        $stmt->execute(); $updated++;
                    } else {
                        $stmt = $mysqli->prepare("INSERT INTO products (name,sku,price,stock,is_rental,category_id) VALUES (?,?,?,?,?,?)");
                        $stmt->bind_param('ssdiii', $row['name'], $row['sku'], $row['price'], $row['stock'], $row['is_rental'], $row['category_id']);
                        $stmt->execute(); $created++;
                    }
                }
                fclose($handle);
                $_SESSION['flash'] = "Imported: {$created} created, {$updated} updated.";
                header('Location: products_list.php'); exit;
            } else $errors[] = 'Could not open file.';
        }
    }
}
?>
<div class="card">
  <h3>Import Products CSV</h3>
  <?php if ($errors): ?><div style="color:#b91c1c"><?= e(implode('<br>', $errors)) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= csrf() ?>">
    <div class="form-row"><label>CSV file (exported format)</label><input type="file" name="csv" accept=".csv" required></div>
    <div><button class="btn" type="submit">Upload & Import</button></div>
  </form>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
