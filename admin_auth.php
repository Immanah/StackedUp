<?php
// admin_auth.php - include this at top of protected pages
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Optionally fetch admin details
$admin_id = (int)$_SESSION['admin_id'];
$stmt = $mysqli->prepare("SELECT id, username, name, role FROM admins WHERE id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$ADMIN = $res->fetch_assoc();
if (!$ADMIN) {
    // invalid session
    session_unset();
    session_destroy();
    header('Location: admin_login.php');
    exit;
}
