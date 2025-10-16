<?php
// login_redirect.php
session_start();

if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin') {
        header('Location: dashboard.php');
    } else {
        // Check if there's a redirect parameter in URL
        if (isset($_GET['redirect'])) {
            header('Location: ' . $_GET['redirect']);
        } else {
            header('Location: catalog.php');
        }
    }
} else {
    // If no session, redirect to login
    header('Location: register.html');
}
exit();
?>