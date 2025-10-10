<?php
header('Content-Type: application/json');
require 'db.php'; // database connection

$data = json_decode(file_get_contents('php://input'), true);

// Collect form data
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$message = trim($data['message'] ?? '');
$channel = 'contact_form';

if(!$name || !$message){
    echo json_encode(['error'=>'Name and message are required.']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;

$stmt = $conn->prepare("INSERT INTO messages (user_id, name, email, phone, message, channel) VALUES (?,?,?,?,?,?)");
$stmt->bind_param("isssss", $user_id, $name, $email, $phone, $message, $channel);

if($stmt->execute()){
    echo json_encode(['success'=>true]);
}else{
    echo json_encode(['error'=>'Failed to save message.']);
}
?>
