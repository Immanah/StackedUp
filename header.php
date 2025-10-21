<?php
// header.php — include after admin_auth.php
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ozyde Admin</title>
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="admin-shell">

  <!-- Sidebar -->
  <aside class="admin-sidebar" id="sidebar">
    <h2>Ozyde Admin</h2>
    <nav>
      <a href="admindashboard.php">Dashboard</a>
      <a href="products_list.php">Products</a>
      <a href="product_edit.php">Add Product</a>
      <a href="categories_list.php">Categories</a>
      <a href="bookings_list.php"> Bookings List</a>
      <a href="rental_payments_dashboard.php"> Rental Payments</a>
      <a href="customers_list.php">Customers</a>
      <a href="custom_orders_list.php">Custom Orders</a>
      <a href="messages_list.php">Messages</a>
      <a href="posts_list.php">Blog / News</a>
      <a href="posts_manage.php">Blog Posts</a>
      <a href="activity_log.php">Activity Log</a>
      <a href="logout.php">Logout</a>
    </nav>
  </aside>

  <!-- Main content area -->
  <main class="admin-main" id="main">
    <div class="admin-header-bar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <div style="display:flex;align-items:center;gap:12px;">
        <button id="menuToggle" class="btn">☰</button>
        <h1 style="margin:0;font-size:20px;">Ozyde Admin Dashboard</h1>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <button id="themeToggle" class="btn">🌓</button>
      </div>
    </div>
