<?php
/**
 * Migration Script - Add vehicle_type column to vehicles table
 * Run this once: http://localhost/Parking/migrate_vehicle_type.php
 * This will automatically add the vehicle_type column and make vehicle_number unique
 */

require_once 'config/db.php';

$conn = getDBConnection();
$errors = [];
$success = [];

// Check if vehicle_type column exists
$check_column = $conn->query("SHOW COLUMNS FROM vehicles LIKE 'vehicle_type'");
$column_exists = $check_column->num_rows > 0;

if (!$column_exists) {
    // Add vehicle_type column
    $sql1 = "ALTER TABLE vehicles 
             ADD COLUMN vehicle_type ENUM('Bike', 'Car', 'Truck', 'SUV', 'Other') DEFAULT 'Car' 
             AFTER vehicle_number";
    
    if ($conn->query($sql1)) {
        $success[] = "✓ vehicle_type column added successfully";
    } else {
        $errors[] = "✗ Error adding vehicle_type column: " . $conn->error;
    }
} else {
    $success[] = "✓ vehicle_type column already exists";
}

// Check if unique constraint exists on vehicle_number
$check_index = $conn->query("SHOW INDEX FROM vehicles WHERE Key_name = 'unique_vehicle_number'");
$index_exists = $check_index->num_rows > 0;

if (!$index_exists) {
    // Check for duplicate vehicle numbers first
    $duplicate_check = $conn->query("
        SELECT vehicle_number, COUNT(*) as count 
        FROM vehicles 
        GROUP BY vehicle_number 
        HAVING count > 1
    ");
    
    if ($duplicate_check->num_rows > 0) {
        $errors[] = "✗ Found duplicate vehicle numbers. Please resolve duplicates before adding unique constraint.";
        $errors[] = "Duplicate vehicle numbers:";
        while ($row = $duplicate_check->fetch_assoc()) {
            $errors[] = "  - " . htmlspecialchars($row['vehicle_number']) . " (appears " . $row['count'] . " times)";
        }
    } else {
        // Add unique constraint
        $sql2 = "ALTER TABLE vehicles ADD UNIQUE KEY unique_vehicle_number (vehicle_number)";
        
        if ($conn->query($sql2)) {
            $success[] = "✓ Unique constraint added to vehicle_number";
        } else {
            $errors[] = "✗ Error adding unique constraint: " . $conn->error;
        }
    }
} else {
    $success[] = "✓ Unique constraint on vehicle_number already exists";
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - Vehicle Type</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .success-item {
            color: #28a745;
            font-weight: bold;
        }
        .error-item {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h2 class="mb-0">Database Migration - Vehicle Type</h2>
            </div>
            <div class="card-body">
                <?php if (count($success) > 0): ?>
                    <div class="alert alert-success">
                        <h5>Migration Results:</h5>
                        <ul class="mb-0">
                            <?php foreach ($success as $msg): ?>
                                <li class="success-item"><?php echo $msg; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (count($errors) > 0): ?>
                    <div class="alert alert-danger">
                        <h5>Errors:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $msg): ?>
                                <li class="error-item"><?php echo $msg; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (count($errors) == 0 && count($success) > 0): ?>
                    <div class="alert alert-info">
                        <h5>Migration Complete!</h5>
                        <p>Your database has been successfully updated. You can now:</p>
                        <ul>
                            <li>Add vehicles with vehicle type selection</li>
                            <li>Prevent duplicate vehicle numbers globally</li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <hr>
                <div class="text-center">
                    <a href="index.php" class="btn btn-primary">Go to Home</a>
                    <a href="user/dashboard.php" class="btn btn-outline-primary">Go to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
