<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php'; // <- make sure you have db.php with the connection

// TEMP: Dev mode (no login yet)
$userId = 1;  
// Later: $userId = $_SESSION['user_id'];

// Query to fetch account info (users) + profile info (profiles)
$sql = "
    SELECT 
        u.user_id,
        u.name AS account_name,
        u.email,
        u.phone AS account_phone,
        u.role,
        u.created_at AS account_created,
        p.first_name,
        p.last_name,
        p.email AS profile_email,
        p.phone AS profile_phone,
        p.address,
        p.bust,
        p.waist,
        p.hip,
        p.styles,
        p.created_at AS profile_created
    FROM users u
    LEFT JOIN profiles p ON u.user_id = p.id
    WHERE u.user_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode($user);
} else {
    echo json_encode(["error" => "No profile found"]);
}

$stmt->close();
$conn->close();
