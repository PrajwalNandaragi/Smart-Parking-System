<?php
/**
 * Diagnostic and Fix Script for Admin Login
 * This will check and fix the admin account
 */

require_once 'config/db.php';

echo "<h2>Admin Account Diagnostic & Fix</h2>";
echo "<hr>";

// Connect to database
$conn = getDBConnection();

// Check if admin table exists
$tables = $conn->query("SHOW TABLES LIKE 'admin'");
if ($tables->num_rows == 0) {
    echo "<p style='color: red;'>✗ Admin table does not exist! Please import database.sql first.</p>";
    closeDBConnection($conn);
    exit;
}

echo "<p style='color: green;'>✓ Admin table exists</p>";

// Check current admin records
$result = $conn->query("SELECT admin_id, username, password_hash FROM admin");
echo "<h3>Current Admin Records:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Username</th><th>Password Hash (first 20 chars)</th></tr>";

$admin_exists = false;
while ($row = $result->fetch_assoc()) {
    $admin_exists = true;
    $hash_preview = substr($row['password_hash'], 0, 20) . '...';
    echo "<tr><td>{$row['admin_id']}</td><td>{$row['username']}</td><td>{$hash_preview}</td></tr>";
}

if (!$admin_exists) {
    echo "<tr><td colspan='3' style='color: red;'>No admin records found!</td></tr>";
}
echo "</table>";

echo "<hr>";

// Fix admin password
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Fixing Admin Password...</h3>";

if ($admin_exists) {
    // Update existing admin
    $stmt = $conn->prepare("UPDATE admin SET password_hash = ? WHERE username = 'admin'");
    $stmt->bind_param("s", $hash);
    
    if ($stmt->execute()) {
        echo "<p style='color: green; font-size: 18px;'>✓ <strong>Admin password updated successfully!</strong></p>";
    } else {
        echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
    }
    $stmt->close();
} else {
    // Insert new admin
    $stmt = $conn->prepare("INSERT INTO admin (username, password_hash) VALUES ('admin', ?)");
    $stmt->bind_param("s", $hash);
    
    if ($stmt->execute()) {
        echo "<p style='color: green; font-size: 18px;'>✓ <strong>Admin account created successfully!</strong></p>";
    } else {
        echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
    }
    $stmt->close();
}

// Verify the fix
echo "<hr>";
echo "<h3>Verification Test:</h3>";
$test_result = $conn->query("SELECT password_hash FROM admin WHERE username = 'admin'");
if ($test_result->num_rows > 0) {
    $admin = $test_result->fetch_assoc();
    if (password_verify('admin123', $admin['password_hash'])) {
        echo "<p style='color: green; font-size: 18px;'>✓ <strong>Password verification successful!</strong></p>";
    } else {
        echo "<p style='color: red;'>✗ Password verification failed!</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Admin not found after fix!</p>";
}

closeDBConnection($conn);

echo "<hr>";
echo "<h3>Login Credentials:</h3>";
echo "<p><strong>Username:</strong> admin</p>";
echo "<p><strong>Password:</strong> admin123</p>";
echo "<hr>";
echo "<p><a href='/Parking/admin/login.php' style='font-size: 16px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Go to Admin Login</a></p>";
echo "<p><a href='/Parking/index.php'>Back to Home</a></p>";
