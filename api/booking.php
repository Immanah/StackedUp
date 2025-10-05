<?php
require __DIR__ . '/bootstrap.php';

$uid = ensure_auth();
$body = body_json();
require_fields($body, ['productId','startDate','endDate']);

$bookingsFile = $DATA_DIR . '/bookings.json';
$bookings = read_json($bookingsFile, []); // [{id, productId, userId, startDate, endDate, createdAt}]

$booking = [
    'id' => bin2hex(random_bytes(8)),
    'productId' => (string)$body['productId'],
    'userId' => $uid,
    'startDate' => $body['startDate'],
    'endDate' => $body['endDate'],
    'createdAt' => date(DATE_ATOM)
];

$bookings[] = $booking;
write_json($bookingsFile, $bookings);

echo json_encode(['ok'=>true,'booking'=>$booking]);
