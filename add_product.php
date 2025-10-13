<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Add Product — OZYDE</title>
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
    </style>
</head>
<body>
<main>
    <h1>Add Product</h1>
    <?php
    session_start();
    $pdo = new PDO('mysql:host=localhost;dbname=ozyde', 'root', '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $category_id = intval($_POST['category_id']);
        $name = $_POST['name'];
        $description = $_POST['description'];
        $color = $_POST['color'];
        $price = max(0, floatval($_POST['price']));
        $sizes = $_POST['sizes'] ?? [];
        $stocks = $_POST['stocks'] ?? [];

        $size_stock_pairs = [];
        foreach($sizes as $i => $size) {
            $qty = max(0, intval($stocks[$i] ?? 0));
            if($size) $size_stock_pairs[] = $size . ':' . $qty;
        }
        $size_str = implode(',', $size_stock_pairs);

        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['tmp_name']) {
            $image_name = basename($_FILES['image']['name']);
            $target = 'gallery/' . $image_name;
            move_uploaded_file($_FILES['image']['tmp_name'], $target);
            $image_path = $target;
        }

        $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, color, price, image, stock, is_rental) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$total_stock = array_sum(array_map('intval', $stocks));
$is_rental = 0; // Set based on your logic
$stmt->execute([$category_id, $name, $description, $color, $price, $image_path, $total_stock, $is_rental]);

        echo '<div style="margin-top:12px;color:green;">Product added successfully!</div>';
    }

    // fetch categories for dropdown
    $cats = $pdo->query("SELECT category_id, category_name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Category</label>
        <select name="category_id" required>
            <?php foreach($cats as $c): ?>
                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Product Name</label>
        <input type="text" name="name" required />

        <label>Description</label>
        <textarea name="description" rows="4"></textarea>

        <label>Color</label>
        <input type="text" name="color" />

        <label>Price (R)</label>
        <input type="number" name="price" min="0" step="0.01" value="0" required />

        <label>Sizes & Stock</label>
        <div id="sizesContainer">
            <div class="size-stock">
                <input type="text" name="sizes[]" placeholder="S/M/L/XL" />
                <input type="number" name="stocks[]" placeholder="Qty" min="0" />
            </div>
        </div>
        <button type="button" onclick="addSizeField()">Add Another Size</button>

        <label>Image</label>
        <input type="file" name="image" accept="image/*" />

        <button type="submit">Add Product</button>
    </form>
</main>
<script>
function addSizeField() {
    const div = document.createElement('div');
    div.className = 'size-stock';
    div.innerHTML = '<input type="text" name="sizes[]" placeholder="S/M/L" /><input type="number" name="stocks[]" placeholder="Qty" min="0" />';
    document.getElementById('sizesContainer').appendChild(div);
}
</script>
</body>
</html>
