<?php
require_once __DIR__ . '/config.php';
session_unset();
session_destroy();
header('Location: admin_login.php');
exit;
