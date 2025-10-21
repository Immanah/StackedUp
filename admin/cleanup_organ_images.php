<?php
/**
 * cleanup_orphan_images.php
 * Delete orphaned image files not in DB.
 * Run periodically (e.g., weekly cron).
 */
require_once __DIR__ . '/config.php';

$uploadDir = __DIR__ . '/uploads/products';
if (!is_dir($uploadDir)) {
    echo "Upload dir missing\n";
    exit;
}

// Get all referenced filenames from DB
$refs = [];
$res = $mysqli->query("SELECT filename, thumb_filename FROM product_images");
while ($row = $res->fetch_assoc()) {
    if ($row['filename']) $refs[] = $row['filename'];
    if ($row['thumb_filename']) $refs[] = $row['thumb_filename'];
}
$refs = array_unique($refs);

// Scan filesystem
$files = scandir($uploadDir);
$deleted = [];
foreach ($files as $f) {
    if ($f === '.' || $f === '..' || is_dir("$uploadDir/$f")) continue;
    if (!in_array($f, $refs)) {
        // Delete orphan
        unlink("$uploadDir/$f");
        $deleted[] = $f;
    }
}

echo "Cleanup complete. Deleted " . count($deleted) . " orphaned file(s):\n";
foreach ($deleted as $f) echo " - $f\n";
