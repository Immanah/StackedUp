<?php
echo "<h2>Fixing Directory Permissions</h2>";

// Create gallery directory with proper permissions
$gallery_dir = 'gallery/';

if (!is_dir($gallery_dir)) {
    echo "Creating gallery directory...<br>";
    if (mkdir($gallery_dir, 0755, true)) {
        echo "✅ Gallery directory created successfully<br>";
    } else {
        echo "❌ Failed to create gallery directory<br>";
    }
} else {
    echo "✅ Gallery directory already exists<br>";
}

// Check and fix permissions
echo "Setting directory permissions...<br>";
if (chmod($gallery_dir, 0755)) {
    echo "✅ Directory permissions set to 0755<br>";
} else {
    echo "❌ Could not set directory permissions<br>";
}

// Test if directory is writable
echo "Testing write permissions...<br>";
$test_file = $gallery_dir . 'test_permissions.txt';
if (file_put_contents($test_file, 'test')) {
    echo "✅ Directory is writable<br>";
    unlink($test_file);
} else {
    echo "❌ Directory is NOT writable<br>";
}

// Check current permissions
echo "Current directory permissions: " . substr(sprintf('%o', fileperms($gallery_dir)), -4) . "<br>";

// Show server information
echo "<h3>Server Information:</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Web Server User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'Unknown') . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

echo "<h3>Next Steps:</h3>";
echo "If permissions are still not working, try these commands in your terminal:<br>";
echo "<code>chmod 755 gallery/</code><br>";
echo "<code>chown www-data:www-data gallery/</code> (if using Apache)<br>";
?>