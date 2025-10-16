<?php
session_start();
// Store some data for goodbye message if needed
$user_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : '';

if (isset($_SESSION['user_id']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'super_admin')) {
    require 'db.php';
    require 'admin_auth.php';
    logAdminAction('logout', 'system', null, "Admin logged out");
}

// Destroy all session data
session_unset();
session_destroy();
header("Location: register.html");
exit();
