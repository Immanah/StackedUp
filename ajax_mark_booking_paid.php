<?php
require_once __DIR__ . '/admin_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$booking_id = (int)($input['booking_id'] ?? 0);

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
    exit;
}

// Update booking payment status
$stmt = $mysqli->prepare("UPDATE bookings SET payment_status = 'paid' WHERE booking_id = ?");
$stmt->bind_param('i', $booking_id);

if ($stmt->execute()) {
    // Log the activity
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $log_stmt = $mysqli->prepare("INSERT INTO activity_log (admin_id, action, context) VALUES (?, 'booking_payment_confirmed', ?)");
    $context = json_encode(['booking_id' => $booking_id]);
    $log_stmt->bind_param('is', $admin_id, $context);
    $log_stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $mysqli->error]);
}
?>