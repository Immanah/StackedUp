<?php
session_start();
require 'db.php';


// Handle product deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM products WHERE product_id = $delete_id");
    $_SESSION['success_message'] = "Product deleted successfully!";
    header('Location: inventory_management.php');
    exit();
}

// Get all products with category information
$products_result = $conn->query("
    SELECT p.*, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    ORDER BY p.created_at DESC
");

$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}

// Get inventory stats
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(stock) as total_stock,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END) as low_stock
    FROM products
");

$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Ozyde Admin</title>
    <style>
        /* Ozyde consistent styling */
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        h1 {
            color: var(--accent);
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: var(--accent);
        }
        
        .stat-label {
            color: var(--muted);
            font-size: 14px;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        
        .btn-primary:hover {
            background: #333;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #268a54;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc3545;
        }
        
        .products-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--accent);
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .status-low-stock {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-out-of-stock {
            background: #f8d7da;
            color: #721c24;
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
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Inventory Management</h1>
            <p>Manage your product inventory and stock levels</p>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_stock']; ?></div>
                <div class="stat-label">Total Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['low_stock']; ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['out_of_stock']; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="actions">
            <a href="add_product.php" class="btn btn-primary">Add New Product</a>
            <a href="generate_inventory_report.php" class="btn btn-success">Generate Report</a>
            <a href="admindashboard.php" class="btn">Back to Dashboard</a>
        </div>
        
        <!-- Products Table -->
        <div class="products-table">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Size</th>
                        <th>Color</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $status_class = '';
                        if ($product['stock'] == 0) {
                            $status_class = 'status-out-of-stock';
                            $status_text = 'Out of Stock';
                        } elseif ($product['stock'] <= 5) {
                            $status_class = 'status-low-stock';
                            $status_text = 'Low Stock';
                        } else {
                            $status_class = 'status-in-stock';
                            $status_text = 'In Stock';
                        }
                        ?>
                        <tr>
                            <td>
                                <?php if ($product['image']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" onerror="this.style.display='none'">
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($product['size'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($product['color']); ?></td>
                            <td>R<?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo $product['stock']; ?></td>
                            <td><span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <a href="inventory_management.php?delete_id=<?php echo $product['product_id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this product?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">
                                No products found. <a href="add_product.php">Add your first product</a>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>