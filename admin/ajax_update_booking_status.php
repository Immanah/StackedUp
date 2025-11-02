<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

header('Content-Type: application/json');

// Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['booking_id']) || !isset($input['status']) || !isset($input['csrf']) || !verify_csrf($input['csrf'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request or CSRF token mismatch.']);
    exit;
}

$booking_id = (int)$input['booking_id'];
$status = $mysqli->real_escape_string($input['status']);

// Validate status
$allowed_statuses = ['booked', 'returned', 'cancelled'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status.']);
    exit;
}

try {
    // Update booking status
    $stmt = $mysqli->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
    $stmt->bind_param('si', $status, $booking_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $activity_context = json_encode([
            'booking_id' => $booking_id,
            'old_status' => $booking['status'] ?? 'unknown',
            'new_status' => $status,
            'admin_id' => $_SESSION['user_id']
        ]);
        
        $log_stmt = $mysqli->prepare("INSERT INTO activity_log (user_id, action, context, ip_address) VALUES (?, 'booking_status_updated', ?, ?)");
        $log_stmt->bind_param('iss', $_SESSION['user_id'], $activity_context, $_SERVER['REMOTE_ADDR']);
        $log_stmt->execute();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $mysqli->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}
?>