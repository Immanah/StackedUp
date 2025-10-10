<?php
require 'db.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $user_id = $_POST['user_id'] ?? null;
    $name = $_POST['name'] ?? null;
    $phone = $_POST['phone'] ?? null;
    $message = $_POST['message'] ?? null;
    $channel = $_POST['channel'] ?? 'contact_form';

    $stmt = $conn->prepare("
        INSERT INTO messages (user_id,name,phone,message,channel) 
        VALUES (?,?,?,?,?)
    ");
    $stmt->bind_param("issss",$user_id,$name,$phone,$message,$channel);
    $stmt->execute();

    echo json_encode(['status'=>'success']);
    exit;
}
?>
