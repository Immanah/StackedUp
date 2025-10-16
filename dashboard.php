<?php
require_once 'admin_auth_simple.php'; // Use simple version first
requireAdminAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OZYDE</title>
    <style>
        /* Keep your existing CSS styles */
        :root {
            --sidebar-width: 250px;
            --header-height: 60px;
            --primary: #111;
            --success: #2fa46b;
            --warning: #f59e0b;
            --danger: #ef4444;
            --muted: #6b7280;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; }
        
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary);
            color: white;
            position: fixed;
            height: 100vh;
            padding: 20px 0;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #374151;
            margin-bottom: 20px;
        }
        
        .sidebar-nav a {
            display: block;
            padding: 12px 20px;
            color: #d1d5db;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: #374151;
            color: white;
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
        }
        
        .admin-header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            padding: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 8px 0;
        }
        
        .stat-label {
            color: var(--muted);
            font-size: 0.875rem;
        }

        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>OZYDE Admin</h2>
                <small><?php echo $_SESSION['role'] == 'super_admin' ? 'Super Admin' : 'Admin'; ?></small>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="admin_products.php">Products</a>
                <a href="categories.php">Categories</a>
                <a href="orders.php">Orders</a>
                <a href="bookings.php">Bookings</a>
                <?php if ($_SESSION['role'] == 'super_admin'): ?>
                <a href="users.php">Users</a>
                <a href="admins.php">Admins</a>
                <a href="system_settings.php">System Settings</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <header class="admin-header">
                <h1>Dashboard</h1>
                <div>
                    Welcome, <?php echo $_SESSION['first_name']; ?> 
                    (<?php echo $_SESSION['role'] == 'super_admin' ? 'Super Admin' : 'Admin'; ?>)
                </div>
            </header>

            <!-- Debug Info -->
            <div class="debug-info">
                <strong>Debug Information:</strong><br>
                User ID: <?php echo $_SESSION['user_id']; ?><br>
                Role: <?php echo $_SESSION['role']; ?><br>
                Email: <?php echo $_SESSION['email']; ?>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value">-</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value">-</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value">-</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Monthly Revenue</div>
                    <div class="stat-value">-</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>