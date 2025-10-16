<?php
require 'db.php';

echo "<h3>Admin User Status Check</h3>";

$result = $conn->query("
    SELECT user_id, first_name, last_name, email, role 
    FROM users 
    WHERE role IN ('admin', 'super_admin')
");

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>";
    
    while ($user = $result->fetch_assoc()) {
        $status = "✅ Active";
        echo "<tr>
                <td>{$user['user_id']}</td>
                <td>{$user['first_name']} {$user['last_name']}</td>
                <td>{$user['email']}</td>
                <td>{$user['role']}</td>
                <td>{$status}</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "❌ No admin users found in database.";
}

// Check admin_permissions table
echo "<h4>Admin Permissions</h4>";
$perms = $conn->query("SELECT * FROM admin_permissions");
if ($perms->num_rows > 0) {
    echo "<ul>";
    while ($perm = $perms->fetch_assoc()) {
        echo "<li>Admin ID {$perm['admin_id']} - {$perm['permission_type']}</li>";
    }
    echo "</ul>";
} else {
    echo "No specific permissions set (super admins have all permissions)";
}
?>