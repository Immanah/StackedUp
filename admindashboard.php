<?php
session_start();
require_once 'db.php';



$user_id = $_SESSION['user_id'];
$admin_name = $_SESSION['first_name'] ?? 'Admin';
$admin_role = $_SESSION['role'] ?? 'admin';

// Fetch real data from database
try {
    // Total Revenue
    $revenue_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_revenue 
        FROM orders 
        WHERE payment_status = 'paid'
    ");
    $revenue_stmt->execute();
    $total_revenue = $revenue_stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];

    // Total Orders
    $orders_stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders");
    $orders_stmt->execute();
    $total_orders = $orders_stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];

    // Total Messages
    $messages_stmt = $pdo->prepare("SELECT COUNT(*) as total_messages FROM messages");
    $messages_stmt->execute();
    $total_messages = $messages_stmt->fetch(PDO::FETCH_ASSOC)['total_messages'];

    // Total Inventory Items
    $inventory_stmt = $pdo->prepare("SELECT COUNT(*) as total_inventory FROM products");
    $inventory_stmt->execute();
    $total_inventory = $inventory_stmt->fetch(PDO::FETCH_ASSOC)['total_inventory'];

    // Recent Orders
    $recent_orders_stmt = $pdo->prepare("
        SELECT o.order_id, CONCAT(u.first_name, ' ', u.last_name) as customer, 
               o.created_at as date, o.total_amount as amount, o.order_status as status
        FROM orders o 
        JOIN users u ON o.user_id = u.user_id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $recent_orders_stmt->execute();
    $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Inventory Items
    $inventory_items_stmt = $pdo->prepare("
        SELECT p.product_id, p.name, c.category_name as category, 
               p.size, p.stock, p.price, p.image,
               CASE 
                   WHEN p.stock = 0 THEN 'out_of_stock'
                   WHEN p.stock <= 3 THEN 'low_stock' 
                   ELSE 'available' 
               END as status
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        ORDER BY p.created_at DESC
    ");
    $inventory_items_stmt->execute();
    $inventory_items = $inventory_items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Messages
    $messages_stmt = $pdo->prepare("
        SELECT message_id, name, email, message as subject, created_at as date, 
               'unread' as status 
        FROM messages 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $messages_stmt->execute();
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ozyde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Ozyde Boutique consistent styling - matching catalog */
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
            --airbnb-pink: #FF5A5F;
            --hero-height: 420px;
            --sidebar-width: 260px;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: "Helvetica Neue", Arial, sans-serif;
            color: var(--text);
            background-color: var(--bg);
            line-height: 1.5;
        }
        
        /* Admin Layout */
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Navigation */
        .admin-sidebar {
            width: var(--sidebar-width);
            background-color: #0b0b0b;
            color: white;
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .admin-sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid #333;
            margin-bottom: 1.5rem;
        }
        
        .admin-logo {
            font-weight: bold;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo-badge {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background: linear-gradient(135deg, #fff2, #fff6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111;
            font-weight: 900;
            font-size: 14px;
        }
        
        .admin-user-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.5rem;
        }
        
        .admin-role {
            background-color: var(--primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .admin-role.superadmin {
            background-color: var(--airbnb-pink);
        }
        
        .admin-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .admin-nav li {
            margin: 0;
        }
        
        .admin-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: #ddd;
            text-decoration: none;
            transition: background-color 0.2s;
            border-left: 3px solid transparent;
        }
        
        .admin-nav a:hover {
            background-color: #222;
            color: white;
        }
        
        .admin-nav a.active {
            background-color: #222;
            border-left-color: white;
            color: white;
        }
        
        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        /* Main Content Area */
        .admin-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            background-color: #f9f9f9;
            min-height: 100vh;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .admin-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
        }
        
        .admin-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: white;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
            border-color: var(--danger);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-card {
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #eaeaea;
        }
        
        .card-title {
            font-size: 0.875rem;
            color: var(--muted);
            margin: 0 0 0.5rem;
        }
        
        .card-value {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
        }
        
        .card-trend {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .trend-up {
            color: var(--success);
        }
        
        .trend-down {
            color: var(--danger);
        }
        
        /* Tables */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .admin-table th,
        .admin-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
        }
        
        .admin-table th {
            font-weight: 600;
            color: var(--muted);
            font-size: 0.875rem;
            background: #f9f9f9;
        }
        
        .admin-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fef3cd;
            color: #856404;
        }
        
        .status-completed {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-active {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-available {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-out_of_stock {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-low_stock {
            background-color: #fef3cd;
            color: #856404;
        }
        
        /* Tabs */
        .admin-tabs {
            display: flex;
            border-bottom: 1px solid #eaeaea;
            margin-bottom: 1.5rem;
            background: white;
            padding: 0 1rem;
            border-radius: 8px 8px 0 0;
        }
        
        .admin-tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }
        
        .admin-tab.active {
            border-bottom-color: var(--primary);
            font-weight: 500;
        }
        
        /* Section Content */
        .section-content {
            display: none;
        }
        
        .section-content.active {
            display: block;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Charts */
        .chart-container {
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #eaeaea;
            margin-bottom: 2rem;
        }
        
        .chart-placeholder {
            height: 300px;
            background-color: #f9f9f9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            border-radius: 4px;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Product Image */
        .product-image-small {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }

        /* Export Options */
        .export-options {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Loading Spinner */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-logo">
                    <div class="logo-badge">✦</div>
                    <div>Ozyde Admin</div>
                </div>
                <div class="admin-user-info">
                    <span>Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
                    <span class="admin-role <?php echo $admin_role === 'superadmin' ? 'superadmin' : ''; ?>">
                        <?php echo ucfirst($admin_role); ?>
                    </span>
                </div>
            </div>
            
            <ul class="admin-nav">
                <li><a href="#" class="active" data-section="dashboard">
                    <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
                    Dashboard
                </a></li>
                <li><a href="#" data-section="inventory">
                    <span class="nav-icon"><i class="fas fa-tshirt"></i></span>
                    View Inventory
                </a></li>
                <li><a href="#" data-section="messages">
                    <span class="nav-icon"><i class="fas fa-envelope"></i></span>
                    Customer Messages
                </a></li>
                <li><a href="#" data-section="orders">
                    <span class="nav-icon"><i class="fas fa-shopping-bag"></i></span>
                    Orders
                </a></li>
                <li><a href="#" data-section="reports">
                    <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                    Final Report
                </a></li>
                <li><a href="add_product.php">
                    <span class="nav-icon"><i class="fas fa-plus"></i></span>
                    Add New Item
                </a></li>
                <li><a href="finalhomepage.html">
                    <span class="nav-icon"><i class="fas fa-home"></i></span>
                    Homepage
                </a></li>
                <li><a href="logout.php">
                    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                    Logout
                </a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1 class="admin-title">Dashboard</h1>
                <div class="admin-actions">
                    <button class="btn" id="exportBtn">Export Data</button>
                    <button class="btn btn-primary" id="reportBtn">Generate Report</button>
                </div>
            </div>
            
            <!-- Display Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard Section -->
            <section id="dashboard" class="section-content active">
                <div class="dashboard-cards">
                    <div class="dashboard-card">
                        <h3 class="card-title">Total Revenue</h3>
                        <p class="card-value">R<?php echo number_format($total_revenue, 2); ?></p>
                        <div class="card-trend trend-up">↑ Actual revenue from paid orders</div>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="card-title">Orders</h3>
                        <p class="card-value"><?php echo $total_orders; ?></p>
                        <div class="card-trend trend-up">↑ Total orders in system</div>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="card-title">Messages</h3>
                        <p class="card-value"><?php echo $total_messages; ?></p>
                        <div class="card-trend trend-up">↑ Customer inquiries</div>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="card-title">Inventory Items</h3>
                        <p class="card-value"><?php echo $total_inventory; ?></p>
                        <div class="card-trend trend-up">↑ Products in catalog</div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h3>Revenue Overview</h3>
                    <canvas id="revenueChart" height="300"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3>Recent Activity</h3>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['date'])); ?></td>
                                <td>R<?php echo number_format($order['amount'], 2); ?></td>
                                <td><span class="status status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Inventory Section -->
            <section id="inventory" class="section-content">
                <div class="admin-header">
                    <h1 class="admin-title">Inventory Management</h1>
                    <div class="admin-actions">
                        <button class="btn" id="exportInventoryBtn">Export Inventory</button>
                        <a href="add_product.php" class="btn btn-primary">Add New Item</a>
                    </div>
                </div>
                
                <div class="export-options" id="exportOptions" style="display: none;">
                    <button class="btn btn-success" id="exportCSVBtn">Export as CSV</button>
                    <button class="btn btn-primary" id="exportPDFBtn">Export as PDF</button>
                    <button class="btn" id="cancelExportBtn">Cancel</button>
                </div>
                
                <div class="admin-tabs">
                    <div class="admin-tab active" data-filter="all">All Items (<?php echo $total_inventory; ?>)</div>
                    <div class="admin-tab" data-filter="available">Available (<?php echo count(array_filter($inventory_items, function($item) { return $item['status'] === 'available'; })); ?>)</div>
                    <div class="admin-tab" data-filter="out_of_stock">Out of Stock (<?php echo count(array_filter($inventory_items, function($item) { return $item['status'] === 'out_of_stock'; })); ?>)</div>
                    <div class="admin-tab" data-filter="low_stock">Low Stock (<?php echo count(array_filter($inventory_items, function($item) { return $item['status'] === 'low_stock'; })); ?>)</div>
                </div>
                
                <table class="admin-table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Size</th>
                            <th>Stock</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): 
                            $status_class = 'status-' . $item['status'];
                            $status_text = '';
                            switch($item['status']) {
                                case 'available': $status_text = 'In Stock'; break;
                                case 'out_of_stock': $status_text = 'Out of Stock'; break;
                                case 'low_stock': $status_text = 'Low Stock'; break;
                            }
                        ?>
                        <tr data-status="<?php echo $item['status']; ?>">
                            <td>
                                <?php if ($item['image']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-image-small" onerror="this.src='gallery/placeholder.png'">
                                <?php else: ?>
                                    <div class="product-image-small" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">No Image</div>
                                <?php endif; ?>
                            </td>
                            <td>#<?php echo $item['product_id']; ?></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td><?php echo htmlspecialchars($item['size']); ?></td>
                            <td><?php echo $item['stock']; ?></td>
                            <td>R<?php echo number_format($item['price'], 2); ?></td>
                            <td><span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            <td>
                                <button class="btn edit-item" data-id="<?php echo $item['product_id']; ?>">Edit</button>
                                <button class="btn btn-danger delete-item" data-id="<?php echo $item['product_id']; ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            
            <!-- Messages Section -->
            <section id="messages" class="section-content">
                <div class="admin-header">
                    <h1 class="admin-title">Customer Messages</h1>
                    <div class="admin-actions">
                        <button class="btn" id="exportMessagesBtn">Export Messages</button>
                    </div>
                </div>
                
                <div class="admin-tabs">
                    <div class="admin-tab active">All Messages (<?php echo $total_messages; ?>)</div>
                    <div class="admin-tab">Unread (5)</div>
                    <div class="admin-tab">Replied (16)</div>
                    <div class="admin-tab">Archived (3)</div>
                </div>
                
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $message): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($message['name']); ?></td>
                            <td><?php echo htmlspecialchars($message['email']); ?></td>
                            <td><?php echo htmlspecialchars(substr($message['subject'], 0, 50)) . (strlen($message['subject']) > 50 ? '...' : ''); ?></td>
                            <td><?php echo date('M j, Y', strtotime($message['date'])); ?></td>
                            <td><span class="status status-<?php echo $message['status']; ?>"><?php echo ucfirst($message['status']); ?></span></td>
                            <td>
                                <button class="btn view-message" data-id="<?php echo $message['message_id']; ?>">View</button>
                                <button class="btn btn-primary reply-message" data-id="<?php echo $message['message_id']; ?>">Reply</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            
            <!-- Reports Section -->
            <section id="reports" class="section-content">
                <div class="admin-header">
                    <h1 class="admin-title">Final Report</h1>
                    <div class="admin-actions">
                        <button class="btn" id="exportPdfBtn">Export PDF</button>
                        <button class="btn" id="exportCsvBtn">Export CSV</button>
                        <button class="btn btn-primary" id="generateReportBtn">Generate New Report</button>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h3>Sales Overview</h3>
                    <canvas id="salesChart" height="300"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3>Top Selling Products</h3>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Units Sold</th>
                                <th>Revenue</th>
                                <th>Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Elegant Evening Gown</td>
                                <td>Dresses</td>
                                <td>42</td>
                                <td>R27,300</td>
                                <td>R13,650</td>
                            </tr>
                            <tr>
                                <td>Classic Black Dress</td>
                                <td>Dresses</td>
                                <td>38</td>
                                <td>R19,760</td>
                                <td>R9,880</td>
                            </tr>
                            <tr>
                                <td>Silk Blouse</td>
                                <td>Tops</td>
                                <td>35</td>
                                <td>R11,200</td>
                                <td>R5,600</td>
                            </tr>
                            <tr>
                                <td>Summer Floral Dress</td>
                                <td>Dresses</td>
                                <td>28</td>
                                <td>R12,600</td>
                                <td>R6,300</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="chart-container">
                    <h3>Customer Insights</h3>
                    <canvas id="customerChart" height="300"></canvas>
                </div>
            </section>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close">&times;</button>
            </div>
            <p>Are you sure you want to delete <strong id="deleteProductName"></strong>? This action cannot be undone.</p>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete</button>
                <button class="btn" id="cancelDeleteBtn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Navigation between sections
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.admin-nav a');
            const sections = document.querySelectorAll('.section-content');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.getAttribute('href') === '#' || this.getAttribute('data-section')) {
                        e.preventDefault();
                        
                        // Remove active class from all links and sections
                        navLinks.forEach(navLink => navLink.classList.remove('active'));
                        sections.forEach(section => section.classList.remove('active'));
                        
                        // Add active class to clicked link
                        this.classList.add('active');
                        
                        // Show the corresponding section
                        const sectionId = this.getAttribute('data-section');
                        if (sectionId) {
                            document.getElementById(sectionId).classList.add('active');
                            
                            // Update page title
                            const sectionTitle = this.textContent.trim();
                            document.querySelector('.admin-title').textContent = sectionTitle;
                        }
                    }
                });
            });
            
            // Tab functionality for inventory
            const inventoryTabs = document.querySelectorAll('#inventory .admin-tab');
            const inventoryRows = document.querySelectorAll('#inventoryTable tbody tr');
            
            inventoryTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    inventoryTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    
                    inventoryRows.forEach(row => {
                        if (filter === 'all' || row.getAttribute('data-status') === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });
            
            // Export functionality
            document.getElementById('exportInventoryBtn').addEventListener('click', function() {
                document.getElementById('exportOptions').style.display = 'flex';
            });
            
            document.getElementById('cancelExportBtn').addEventListener('click', function() {
                document.getElementById('exportOptions').style.display = 'none';
            });
            
            document.getElementById('exportCSVBtn').addEventListener('click', function() {
                exportInventory('csv');
            });
            
            document.getElementById('exportPDFBtn').addEventListener('click', function() {
                exportInventory('pdf');
            });
            
            // Delete item functionality
            const deleteButtons = document.querySelectorAll('.delete-item');
            const deleteModal = document.getElementById('deleteModal');
            const deleteProductName = document.getElementById('deleteProductName');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const modalClose = document.querySelector('.modal-close');
            
            let productToDelete = null;
            
            deleteButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    productToDelete = this.getAttribute('data-id');
                    const productName = this.getAttribute('data-name');
                    deleteProductName.textContent = productName;
                    deleteModal.style.display = 'flex';
                });
            });
            
            confirmDeleteBtn.addEventListener('click', function() {
                if (productToDelete) {
                    deleteProduct(productToDelete);
                }
            });
            
            cancelDeleteBtn.addEventListener('click', closeModal);
            modalClose.addEventListener('click', closeModal);
            
            function closeModal() {
                deleteModal.style.display = 'none';
                productToDelete = null;
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    closeModal();
                }
            });
            
            // Initialize charts
            initializeCharts();
        });
        
        function exportInventory(format) {
            const btn = document.getElementById('export' + format.toUpperCase() + 'Btn');
            const originalText = btn.textContent;
            
            btn.innerHTML = '<div class="loading"></div> Exporting...';
            btn.disabled = true;
            
            // Simulate export process
            setTimeout(() => {
                alert(`Inventory exported as ${format.toUpperCase()} successfully!`);
                btn.textContent = originalText;
                btn.disabled = false;
                document.getElementById('exportOptions').style.display = 'none';
            }, 2000);
        }
        
        function deleteProduct(productId) {
            const btn = document.querySelector(`.delete-item[data-id="${productId}"]`);
            const originalText = btn.textContent;
            
            btn.innerHTML = '<div class="loading"></div>';
            btn.disabled = true;
            
            // Send AJAX request to delete product
            fetch('delete_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = btn.closest('tr');
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        // Update counts
                        updateInventoryCounts();
                    }, 300);
                    
                    alert('Product deleted successfully!');
                } else {
                    alert('Error: ' + data.message);
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the product.');
                btn.textContent = originalText;
                btn.disabled = false;
            });
            
            closeModal();
        }
        
        function updateInventoryCounts() {
            // This would update the tab counts after deletion
            // For now, we'll just reload the page to get updated counts
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
        
        function initializeCharts() {
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Revenue (R)',
                        data: [12000, 19000, 15000, 25000, 22000, 30000, 28000, 26000, 24000, 22000, 18000, 20000],
                        borderColor: '#111',
                        backgroundColor: 'rgba(17, 17, 17, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Sales Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            new Chart(salesCtx, {
                type: 'bar',
                data: {
                    labels: ['Dresses', 'Tops', 'Bottoms', 'Accessories', 'Outerwear'],
                    datasets: [{
                        label: 'Units Sold',
                        data: [65, 59, 80, 81, 56],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Customer Chart
            const customerCtx = document.getElementById('customerChart').getContext('2d');
            new Chart(customerCtx, {
                type: 'doughnut',
                data: {
                    labels: ['New Customers', 'Returning Customers', 'VIP Customers'],
                    datasets: [{
                        data: [45, 35, 20],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    </script>
</body>
</html>