<?php
header('Content-Type: application/json');
include 'db.php'; // your DB connection

$product_id = $_GET['product_id'] ?? 1; // example: product 1

$stmt = $conn->prepare("SELECT start_date, end_date FROM bookings WHERE product_id=? AND status='booked'");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

$bookedDates = [];

while($row = $result->fetch_assoc()) {
    $current = new DateTime($row['start_date']);
    $end = new DateTime($row['end_date']);
    while ($current <= $end) {
        $bookedDates[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
}

echo json_encode($bookedDates);
?>
