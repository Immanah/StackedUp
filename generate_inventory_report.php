<?php
session_start();
require 'db.php';


// Handle export request
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    // Get inventory data
    $products_result = $conn->query("
        SELECT p.*, c.category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        ORDER BY p.name
    ");
    
    if ($export_type === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=inventory_report_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Product ID', 'Name', 'Category', 'Size', 'Color', 'Price', 'Stock', 'Status', 'Rental']);
        
        while ($row = $products_result->fetch_assoc()) {
            $status = $row['stock'] == 0 ? 'Out of Stock' : ($row['stock'] <= 5 ? 'Low Stock' : 'In Stock');
            $rental = $row['is_rental'] ? 'Yes' : 'No';
            
            fputcsv($output, [
                $row['product_id'],
                $row['name'],
                $row['category_name'],
                $row['size'],
                $row['color'],
                $row['price'],
                $row['stock'],
                $status,
                $rental
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// Get inventory stats for display
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(stock) as total_stock,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock > 0 AND stock <= 5 THEN 1 ELSE 0 END) as low_stock,
        AVG(price) as avg_price,
        SUM(price * stock) as total_value
    FROM products
");

$stats = $stats_result->fetch_assoc();

// Get category distribution
$categories_result = $conn->query("
    SELECT c.category_name, COUNT(p.product_id) as product_count
    FROM categories c 
    LEFT JOIN products p ON c.category_id = p.category_id 
    GROUP BY c.category_id, c.category_name
    ORDER BY product_count DESC
");

$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report - Ozyde Admin</title>
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
        
        .report-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .report-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .report-section h3 {
            color: var(--accent);
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .category-list {
            list-style: none;
        }
        
        .category-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .category-item:last-child {
            border-bottom: none;
        }
        
        .stock-status {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .status-item {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
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
        
        .status-number {
            font-size: 20px;
            font-weight: bold;
            display: block;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--accent);
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Inventory Report</h1>
            <p>Comprehensive overview of your inventory and stock levels</p>
        </div>
        
        <!-- Export Actions -->
        <div class="actions">
            <a href="generate_inventory_report.php?export=csv" class="btn btn-success">Export as CSV</a>
            <a href="inventory_management.php" class="btn">Back to Inventory</a>
            <a href="admindashboard.php" class="btn">Back to Dashboard</a>
        </div>
        
        <!-- Key Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_stock']; ?></div>
                <div class="stat-label">Total Stock Units</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">R<?php echo number_format($stats['total_value'], 2); ?></div>
                <div class="stat-label">Total Inventory Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">R<?php echo number_format($stats['avg_price'], 2); ?></div>
                <div class="stat-label">Average Price</div>
            </div>
        </div>
        
        <div class="report-sections">
            <!-- Stock Status -->
            <div class="report-section">
                <h3>Stock Status Overview</h3>
                <div class="stock-status">
                    <div class="status-item status-in-stock">
                        <span class="status-number"><?php echo $stats['total_products'] - $stats['out_of_stock'] - $stats['low_stock']; ?></span>
                        <span>In Stock</span>
                    </div>
                    <div class="status-item status-low-stock">
                        <span class="status-number"><?php echo $stats['low_stock']; ?></span>
                        <span>Low Stock</span>
                    </div>
                    <div class="status-item status-out-of-stock">
                        <span class="status-number"><?php echo $stats['out_of_stock']; ?></span>
                        <span>Out of Stock</span>
                    </div>
                </div>
            </div>
            
            <!-- Category Distribution -->
            <div class="report-section">
                <h3>Products by Category</h3>
                <ul class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <li class="category-item">
                            <span><?php echo htmlspecialchars($category['category_name']); ?></span>
                            <span><?php echo $category['product_count']; ?> products</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <!-- Report Summary -->
        <div class="report-section">
            <h3>Report Summary</h3>
            <p><strong>Report Generated:</strong> <?php echo date('F j, Y, g:i a'); ?></p>
            <p><strong>Total Products:</strong> <?php echo $stats['total_products']; ?> items</p>
            <p><strong>Inventory Value:</strong> R<?php echo number_format($stats['total_value'], 2); ?></p>
            <p><strong>Stock Health:</strong> 
                <?php 
                $healthy_percentage = (($stats['total_products'] - $stats['out_of_stock'] - $stats['low_stock']) / $stats['total_products']) * 100;
                echo number_format($healthy_percentage, 1) . '% of products have adequate stock';
                ?>
            </p>
            <p><strong>Attention Needed:</strong> 
                <?php echo $stats['low_stock'] + $stats['out_of_stock']; ?> products require restocking attention
            </p>
        </div>
        
        <a href="inventory_management.php" class="back-link">← Back to Inventory Management</a>
    </div>
</body>
</html>