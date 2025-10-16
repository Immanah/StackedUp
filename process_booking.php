<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

try {
    // Save booking to file (in real application, save to database)
    $bookingsFile = 'bookings.json';
    
    // Read existing bookings
    $bookings = [];
    if (file_exists($bookingsFile)) {
        $bookings = json_decode(file_get_contents($bookingsFile), true) ?: [];
    }
    
    // Add new booking
    $data['id'] = uniqid();
    $data['processed_at'] = date('Y-m-d H:i:s');
    $bookings[] = $data;
    
    // Save back to file
    if (file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT))) {
        echo json_encode([
            'success' => true, 
            'message' => 'Booking processed successfully',
            'booking_ref' => $data['ref']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save booking']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>