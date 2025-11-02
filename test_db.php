<?php
$host = 'localhost'; // or the host given by provider
$user = 'ozyderen_ozyde';
$pass = '7QADxddwtwYFXSWDUWTB';
$db   = 'ozyderen_ozyde';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully!";
?>
