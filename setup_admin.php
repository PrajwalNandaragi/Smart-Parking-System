<?php
/**
 * Setup Script - Automatically fixes admin password in database
 * Run this once: http://localhost/Parking/setup_admin.php
 */

require_once 'config/db.php';

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Connect to database
$conn = getDBConnection();

// Check if admin exists
$check_stmt = $conn->prepare("SELECT admin_id FROM admin WHERE username = 'admin'");
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    $update_stmt = $conn->prepare("UPDATE admin SET password_hash = ? WHERE username = 'admin'");
    $update_stmt->bind_param("s", $hash);
    
    if ($update_stmt->execute()) {
        echo "<h2 style='color: green;'>✓ Admin password updated successfully!</h2>";
    } else {
        echo "<h2 style='color: red;'>✗ Error updating admin: " . $conn->error . "</h2>";
    }
    $update_stmt->close();
} else {
    // Insert new admin
    $insert_stmt = $conn->prepare("INSERT INTO admin (username, password_hash) VALUES ('admin', ?)");
    $insert_stmt->bind_param("s", $hash);
    
    if ($insert_stmt->execute()) {
        echo "<h2 style='color: green;'>✓ Admin account created successfully!</h2>";
    } else {
        echo "<h2 style='color: red;'>✗ Error creating admin: " . $conn->error . "</h2>";
    }
    $insert_stmt->close();
}

$check_stmt->close();
closeDBConnection($conn);

echo "<hr>";
echo "<h3>Login Credentials:</h3>";
echo "<p><strong>Username:</strong> admin</p>";
echo "<p><strong>Password:</strong> admin123</p>";
echo "<hr>";
echo "<p><a href='/Parking/admin/login.php'>Go to Admin Login</a> | <a href='/Parking/index.php'>Back to Home</a></p>";

