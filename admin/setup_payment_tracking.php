<?php
require_once __DIR__ . '/admin_auth.php';

echo "<h3>Setting up payment tracking...</h3>";

$queries = [
    "ALTER TABLE `bookings` ADD COLUMN `payment_status` ENUM('pending','paid','failed') DEFAULT 'pending'",
    "ALTER TABLE `bookings` ADD COLUMN `booking_ref` VARCHAR(20) NULL",
    "ALTER TABLE `bookings` ADD COLUMN `total_amount` DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE `bookings` ADD COLUMN `rental_days` INT DEFAULT 3",
    "UPDATE `bookings` SET `payment_status` = 'paid' WHERE `payment_status` IS NULL"
];

foreach ($queries as $query) {
    if ($mysqli->query($query)) {
        echo "<p>✅ " . htmlspecialchars($query) . "</p>";
    } else {
        echo "<p>❌ Error: " . $mysqli->error . "</p>";
    }
}

echo "<p><strong>Setup complete! <a href='rental_payments_dashboard.php'>Go to Rental Payments Dashboard</a></strong></p>";
?>