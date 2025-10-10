<?php
session_start();
// Check if user is logged in and is admin


$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ozyde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <li><a href="finalhomepage.html">
                    <span class="nav-icon"><i class="fas fa-home"></i></span>
                    Homepage
                </a></li>
                <li><a href="admin_logout.php">
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
                        <p class="card-value">R<?php echo number_format($total_revenue ?? 12458, 2); ?></p>
                        <div class="card-trend trend-up">↑ 12.5% from last month</div>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="card-title">Orders</h3>
                        <p class="card-value"><?php echo $total_orders ?? 142; ?></p>
                        <div class="card-trend trend-up">↑ 8.3% from last month</div>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="card-title">Messages</h3>
                        <p class="card-value"><?php echo $total_messages ?? 24; ?></p>
                        <div class="card-trend trend-down">↓ 3.2% from last month</div>
                    </div>
                    <div class="dashboard-card">
                        <h3 class="card-title">Inventory Items</h3>
                        <p class="card-value"><?php echo $total_inventory ?? 86; ?></p>
                        <div class="card-trend trend-up">↑ 5.1% from last month</div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h3>Revenue Overview</h3>
                    <div class="chart-placeholder">
                        Revenue Chart Placeholder
                    </div>
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
                            <?php
                            // Sample data - replace with actual database data
                            $recent_orders = [
                                ['id' => 'ORD-5842', 'customer' => 'Sarah Johnson', 'date' => 'Nov 12, 2023', 'amount' => 'R245', 'status' => 'completed'],
                                ['id' => 'ORD-5841', 'customer' => 'Michael Chen', 'date' => 'Nov 11, 2023', 'amount' => 'R189', 'status' => 'pending'],
                                ['id' => 'ORD-5840', 'customer' => 'Emma Rodriguez', 'date' => 'Nov 10, 2023', 'amount' => 'R320', 'status' => 'completed'],
                                ['id' => 'ORD-5839', 'customer' => 'James Wilson', 'date' => 'Nov 9, 2023', 'amount' => 'R156', 'status' => 'cancelled']
                            ];
                            
                            foreach ($recent_orders as $order): ?>
                            <tr>
                                <td><?php echo $order['id']; ?></td>
                                <td><?php echo $order['customer']; ?></td>
                                <td><?php echo $order['date']; ?></td>
                                <td><?php echo $order['amount']; ?></td>
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
                        <button class="btn btn-primary" id="addItemBtn">Add New Item</button>
                    </div>
                </div>
                
                <div class="admin-tabs">
                    <div class="admin-tab active">All Items (<?php echo $total_inventory ?? 86; ?>)</div>
                    <div class="admin-tab">Available (72)</div>
                    <div class="admin-tab">Out of Stock (8)</div>
                    <div class="admin-tab">Low Stock (6)</div>
                </div>
                
                <table class="admin-table">
                    <thead>
                        <tr>
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
                        <?php
                        // Sample inventory data - replace with database data
                        $inventory_items = [
                            ['id' => 'PRD-1245', 'name' => 'Elegant Evening Gown', 'category' => 'Dresses', 'size' => 'M', 'stock' => 12, 'price' => 'R650', 'status' => 'available'],
                            ['id' => 'PRD-1246', 'name' => 'Summer Floral Dress', 'category' => 'Dresses', 'size' => 'S', 'stock' => 0, 'price' => 'R450', 'status' => 'out_of_stock'],
                            ['id' => 'PRD-1247', 'name' => 'Classic Black Dress', 'category' => 'Dresses', 'size' => 'L', 'stock' => 3, 'price' => 'R520', 'status' => 'low_stock'],
                            ['id' => 'PRD-1248', 'name' => 'Silk Blouse', 'category' => 'Tops', 'size' => 'M', 'stock' => 15, 'price' => 'R320', 'status' => 'available']
                        ];
                        
                        foreach ($inventory_items as $item): 
                            $status_class = '';
                            $status_text = '';
                            if ($item['status'] == 'available') {
                                $status_class = 'status-completed';
                                $status_text = 'In Stock';
                            } elseif ($item['status'] == 'out_of_stock') {
                                $status_class = 'status-cancelled';
                                $status_text = 'Out of Stock';
                            } elseif ($item['status'] == 'low_stock') {
                                $status_class = 'status-pending';
                                $status_text = 'Low Stock';
                            }
                        ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td><?php echo $item['name']; ?></td>
                            <td><?php echo $item['category']; ?></td>
                            <td><?php echo $item['size']; ?></td>
                            <td><?php echo $item['stock']; ?></td>
                            <td><?php echo $item['price']; ?></td>
                            <td><span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            <td>
                                <button class="btn edit-item" data-id="<?php echo $item['id']; ?>">Edit</button>
                                <button class="btn btn-danger delete-item" data-id="<?php echo $item['id']; ?>">Delete</button>
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
                    <div class="admin-tab active">All Messages (<?php echo $total_messages ?? 24; ?>)</div>
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
                        <?php
                        // Sample messages data - replace with database data
                        $messages = [
                            ['name' => 'Jennifer Lopez', 'email' => 'jennifer@example.com', 'subject' => 'Question about dress sizing', 'date' => 'Nov 12, 2023', 'status' => 'unread'],
                            ['name' => 'Robert Smith', 'email' => 'robert@example.com', 'subject' => 'Return request', 'date' => 'Nov 11, 2023', 'status' => 'replied'],
                            ['name' => 'Amanda Johnson', 'email' => 'amanda@example.com', 'subject' => 'Custom order inquiry', 'date' => 'Nov 10, 2023', 'status' => 'unread'],
                            ['name' => 'Thomas Brown', 'email' => 'thomas@example.com', 'subject' => 'Compliment about service', 'date' => 'Nov 9, 2023', 'status' => 'replied']
                        ];
                        
                        foreach ($messages as $message): 
                            $status_class = $message['status'] == 'unread' ? 'status-pending' : 'status-completed';
                            $status_text = $message['status'] == 'unread' ? 'Unread' : 'Replied';
                        ?>
                        <tr>
                            <td><?php echo $message['name']; ?></td>
                            <td><?php echo $message['email']; ?></td>
                            <td><?php echo $message['subject']; ?></td>
                            <td><?php echo $message['date']; ?></td>
                            <td><span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            <td>
                                <button class="btn view-message" data-id="1">View</button>
                                <button class="btn btn-primary reply-message" data-id="1">Reply</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            
            <!-- Orders Section -->
            <section id="orders" class="section-content">
                <div class="admin-header">
                    <h1 class="admin-title">Order Management</h1>
                    <div class="admin-actions">
                        <button class="btn" id="exportOrdersBtn">Export Orders</button>
                    </div>
                </div>
                
                <div class="admin-tabs">
                    <div class="admin-tab active">All Orders (<?php echo $total_orders ?? 142; ?>)</div>
                    <div class="admin-tab">Pending (18)</div>
                    <div class="admin-tab">Processing (24)</div>
                    <div class="admin-tab">Completed (92)</div>
                    <div class="admin-tab">Cancelled (8)</div>
                </div>
                
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Sample orders data - replace with database data
                        $orders = [
                            ['id' => 'ORD-5842', 'customer' => 'Sarah Johnson', 'date' => 'Nov 12, 2023', 'items' => 2, 'total' => 'R490', 'status' => 'completed'],
                            ['id' => 'ORD-5841', 'customer' => 'Michael Chen', 'date' => 'Nov 11, 2023', 'items' => 1, 'total' => 'R189', 'status' => 'pending'],
                            ['id' => 'ORD-5840', 'customer' => 'Emma Rodriguez', 'date' => 'Nov 10, 2023', 'items' => 3, 'total' => 'R960', 'status' => 'completed'],
                            ['id' => 'ORD-5839', 'customer' => 'James Wilson', 'date' => 'Nov 9, 2023', 'items' => 1, 'total' => 'R156', 'status' => 'cancelled']
                        ];
                        
                        foreach ($orders as $order): 
                            $status_class = 'status-' . $order['status'];
                        ?>
                        <tr>
                            <td><?php echo $order['id']; ?></td>
                            <td><?php echo $order['customer']; ?></td>
                            <td><?php echo $order['date']; ?></td>
                            <td><?php echo $order['items']; ?></td>
                            <td><?php echo $order['total']; ?></td>
                            <td><span class="status <?php echo $status_class; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                            <td>
                                <button class="btn view-order" data-id="<?php echo $order['id']; ?>">View</button>
                                <button class="btn btn-primary update-order" data-id="<?php echo $order['id']; ?>">Update</button>
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
                    <div class="chart-placeholder">
                        Sales Chart Placeholder
                    </div>
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
                    <div class="chart-placeholder">
                        Customer Insights Chart Placeholder
                    </div>
                </div>
            </section>
        </main>
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
            
            // Tab functionality
            const tabs = document.querySelectorAll('.admin-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Button actions
            document.getElementById('exportBtn').addEventListener('click', function() {
                alert('Exporting dashboard data...');
            });
            
            document.getElementById('reportBtn').addEventListener('click', function() {
                alert('Generating report...');
            });
            
            // Inventory management
            document.getElementById('addItemBtn').addEventListener('click', function() {
                alert('Opening add item form...');
            });
            
            // Message management
            document.querySelectorAll('.view-message').forEach(btn => {
                btn.addEventListener('click', function() {
                    alert('Viewing message ID: ' + this.getAttribute('data-id'));
                });
            });
            
            // Order management
            document.querySelectorAll('.view-order').forEach(btn => {
                btn.addEventListener('click', function() {
                    alert('Viewing order ID: ' + this.getAttribute('data-id'));
                });
            });
        });
    </script>
</body>
</html>