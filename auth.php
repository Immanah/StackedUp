<?php
session_start();
require 'db.php'; // your DB connection

$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? 'index.php';

if($action==='login'){
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($user = $result->fetch_assoc()){
        if(password_verify($password,$user['password'])){
            $_SESSION['user_id']=$user['user_id'];
            $_SESSION['user_name']=$user['first_name'];
            echo json_encode(['status'=>'success','redirect'=>$redirect]);
            exit;
        }
    }
    echo json_encode(['status'=>'error','message'=>'Invalid email or password']);
    exit;
}

if($action==='register'){
    $first_name=$_POST['first_name'];
    $last_name=$_POST['last_name'];
    $email=$_POST['email'];
    $password=password_hash($_POST['password'],PASSWORD_DEFAULT);

    // Check if email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    if($stmt->get_result()->num_rows>0){
        echo json_encode(['status'=>'error','message'=>'Email already registered']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO users (first_name,last_name,email,password) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss",$first_name,$last_name,$email,$password);
    if($stmt->execute()){
        $_SESSION['user_id']=$stmt->insert_id;
        $_SESSION['user_name']=$first_name;
        echo json_encode(['status'=>'success','redirect'=>$redirect]);
        exit;
    } else {
        echo json_encode(['status'=>'error','message'=>'Registration failed']);
        exit;
    }
}
?>

