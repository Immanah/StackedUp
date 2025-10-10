<?php
session_start();
header('Content-Type: application/json');
require 'db.php'; // your mysqli connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'You must be logged in to submit a custom order.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate required fields
$description = trim($_POST['description'] ?? '');
if (!$description) {
    echo json_encode(['error' => 'Description is required.']);
    exit;
}

// Optional fields
$fabric = trim($_POST['fabric_preference'] ?? '');
$budget = !empty($_POST['budget']) ? floatval($_POST['budget']) : NULL;
$bust = !empty($_POST['bust']) ? floatval($_POST['bust']) : NULL;
$waist = !empty($_POST['waist']) ? floatval($_POST['waist']) : NULL;
$hips = !empty($_POST['hips']) ? floatval($_POST['hips']) : NULL;
$height = !empty($_POST['height']) ? floatval($_POST['height']) : NULL;
$sleeve_length = !empty($_POST['sleeve_length']) ? floatval($_POST['sleeve_length']) : NULL;
$shoulder_width = !empty($_POST['shoulder_width']) ? floatval($_POST['shoulder_width']) : NULL;
$notes = trim($_POST['notes'] ?? '');

// Handle image upload
$image_url = NULL;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/custom_orders/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $fileTmp = $_FILES['image']['tmp_name'];
    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $targetFile = $uploadDir . $fileName;

    if (move_uploaded_file($fileTmp, $targetFile)) {
        $image_url = $targetFile;
    } else {
        echo json_encode(['error' => 'Failed to upload image.']);
        exit;
    }
} else {
    echo json_encode(['error' => 'Please attach an image for your custom order.']);
    exit;
}

// Insert into database
$stmt = $conn->prepare("
    INSERT INTO custom_orders 
    (user_id, description, fabric_preference, budget, bust, waist, hips, height, sleeve_length, shoulder_width, notes, image_url)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    'issddddddsss',
    $user_id,
    $description,
    $fabric,
    $budget,
    $bust,
    $waist,
    $hips,
    $height,
    $sleeve_length,
    $shoulder_width,
    $notes,
    $image_url
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to submit custom order.']);
}
?>
