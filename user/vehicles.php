<?php
require_once '../config/db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$error = '';
$success = '';

// Handle add vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
    $vehicle_number = trim(strtoupper($_POST['vehicle_number'] ?? ''));
    $vehicle_type = $_POST['vehicle_type'] ?? 'Car';
    
    if (empty($vehicle_number)) {
        $error = 'Vehicle number is required';
    } elseif (empty($vehicle_type)) {
        $error = 'Vehicle type is required';
    } else {
        // Check if vehicle already exists globally (across all users) - case insensitive check
        $check_stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE UPPER(vehicle_number) = UPPER(?)");
        $check_stmt->bind_param("s", $vehicle_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Vehicle number is already registered';
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO vehicles (user_id, vehicle_number, vehicle_type) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("iss", $user_id, $vehicle_number, $vehicle_type);
            
            if ($insert_stmt->execute()) {
                $success = 'Vehicle added successfully';
            } else {
                // Check if error is due to duplicate vehicle number (database constraint)
                if ($conn->errno === 1062) { // MySQL duplicate entry error code
                    $error = 'Vehicle number is already registered';
                } else {
                    $error = 'Failed to add vehicle. Please try again.';
                }
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle delete vehicle
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $vehicle_id = intval($_GET['delete']);
    
    // Verify vehicle belongs to user
    $verify_stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND user_id = ?");
    $verify_stmt->bind_param("ii", $vehicle_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        // Check if vehicle has active bookings
        $booking_check = $conn->prepare("SELECT booking_id FROM bookings WHERE vehicle_id = ? AND status = 'Active'");
        $booking_check->bind_param("i", $vehicle_id);
        $booking_check->execute();
        $booking_result = $booking_check->get_result();
        
        if ($booking_result->num_rows > 0) {
            $error = 'Cannot delete vehicle with active bookings';
        } else {
            $delete_stmt = $conn->prepare("DELETE FROM vehicles WHERE vehicle_id = ? AND user_id = ?");
            $delete_stmt->bind_param("ii", $vehicle_id, $user_id);
            
            if ($delete_stmt->execute()) {
                $success = 'Vehicle deleted successfully';
            } else {
                $error = 'Failed to delete vehicle';
            }
            $delete_stmt->close();
        }
        $booking_check->close();
    } else {
        $error = 'Invalid vehicle';
    }
    $verify_stmt->close();
}

// Get user's vehicles
$vehicles_stmt = $conn->prepare("SELECT vehicle_id, vehicle_number, vehicle_type, created_at FROM vehicles WHERE user_id = ? ORDER BY created_at DESC");
$vehicles_stmt->bind_param("i", $user_id);
$vehicles_stmt->execute();
$vehicles_result = $vehicles_stmt->get_result();
$vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);
$vehicles_stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'Manage Vehicles';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4"><i class="bi bi-car-front"></i> Manage Vehicles</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Vehicle</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="vehicle_number" class="form-label">Vehicle Number</label>
                        <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" 
                               required placeholder="e.g., KA01AB1234" 
                               pattern="[A-Z0-9]+" title="Enter vehicle number (letters and numbers only)">
                        <small class="text-muted">Enter your vehicle registration number</small>
                    </div>
                    <div class="mb-3">
                        <label for="vehicle_type" class="form-label">Vehicle Type</label>
                        <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                            <option value="">-- Select Vehicle Type --</option>
                            <option value="Bike">Bike</option>
                            <option value="Car" selected>Car</option>
                            <option value="Truck">Truck</option>
                            <option value="SUV">SUV</option>
                            <option value="Other">Other</option>
                        </select>
                        <small class="text-muted">Select the type of your vehicle</small>
                    </div>
                    <button type="submit" name="add_vehicle" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle"></i> Add Vehicle
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list"></i> My Vehicles</h5>
            </div>
            <div class="card-body">
                <?php if (count($vehicles) === 0): ?>
                    <p class="text-muted text-center py-4">No vehicles registered. Add your first vehicle above.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($vehicles as $vehicle): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($vehicle['vehicle_number']); ?></strong>
                                    <br><small class="text-muted">
                                        Type: <?php echo htmlspecialchars($vehicle['vehicle_type'] ?? 'Car'); ?> | 
                                        Added: <?php echo date('Y-m-d', strtotime($vehicle['created_at'])); ?>
                                    </small>
                                </div>
                                <a href="?delete=<?php echo $vehicle['vehicle_id']; ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this vehicle?')">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

