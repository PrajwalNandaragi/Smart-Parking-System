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
$area_id = intval($_GET['area_id'] ?? 0);

$error = '';
$success = '';

// Get area details
$area_stmt = $conn->prepare("SELECT area_id, area_name, location, hourly_rate FROM parking_areas WHERE area_id = ?");
$area_stmt->bind_param("i", $area_id);
$area_stmt->execute();
$area_result = $area_stmt->get_result();

if ($area_result->num_rows === 0) {
    $error = 'Invalid parking area';
    $area = null;
} else {
    $area = $area_result->fetch_assoc();
}
$area_stmt->close();

// Get available slots for this area
$slots_stmt = $conn->prepare("
    SELECT slot_id, slot_number 
    FROM parking_slots 
    WHERE area_id = ? AND status = 'Available'
    ORDER BY slot_number
");
$slots_stmt->bind_param("i", $area_id);
$slots_stmt->execute();
$slots_result = $slots_stmt->get_result();
$slots = $slots_result->fetch_all(MYSQLI_ASSOC);
$slots_stmt->close();

// Get user's vehicles
$vehicles_stmt = $conn->prepare("SELECT vehicle_id, vehicle_number, vehicle_type FROM vehicles WHERE user_id = ?");
$vehicles_stmt->bind_param("i", $user_id);
$vehicles_stmt->execute();
$vehicles_result = $vehicles_stmt->get_result();
$vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);
$vehicles_stmt->close();

// Handle booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $area) {
    $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
    $slot_id = intval($_POST['slot_id'] ?? 0);
    
    if (empty($vehicle_id) || empty($slot_id)) {
        $error = 'Please select a vehicle and slot';
    } else {
        // Verify vehicle belongs to user
        $verify_vehicle = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND user_id = ?");
        $verify_vehicle->bind_param("ii", $vehicle_id, $user_id);
        $verify_vehicle->execute();
        $verify_result = $verify_vehicle->get_result();
        
        if ($verify_result->num_rows === 0) {
            $error = 'Invalid vehicle selected';
        } else {
            // Verify slot is available
            $verify_slot = $conn->prepare("SELECT slot_id FROM parking_slots WHERE slot_id = ? AND area_id = ? AND status = 'Available'");
            $verify_slot->bind_param("ii", $slot_id, $area_id);
            $verify_slot->execute();
            $slot_result = $verify_slot->get_result();
            
            if ($slot_result->num_rows === 0) {
                $error = 'Selected slot is no longer available';
            } else {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Create booking with current real-time from database server
                    $booking_stmt = $conn->prepare("
                        INSERT INTO bookings (user_id, vehicle_id, slot_id, entry_time, status) 
                        VALUES (?, ?, ?, NOW(), 'Active')
                    ");
                    $booking_stmt->bind_param("iii", $user_id, $vehicle_id, $slot_id);
                    $booking_stmt->execute();
                    $booking_id = $conn->insert_id;
                    $booking_stmt->close();
                    
                    // Update slot status to Occupied
                    $update_slot = $conn->prepare("UPDATE parking_slots SET status = 'Occupied' WHERE slot_id = ?");
                    $update_slot->bind_param("i", $slot_id);
                    $update_slot->execute();
                    $update_slot->close();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success = 'Slot booked successfully!';
                    header('Location: dashboard.php?booked=1');
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Booking failed. Please try again.';
                }
            }
            $verify_slot->close();
        }
        $verify_vehicle->close();
    }
}

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'Book Parking Slot';
?>

<?php if ($error && $area): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($area): ?>
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-bookmark-plus"></i> Book Slot - <?php echo htmlspecialchars($area['area_name']); ?></h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($area['location']); ?></p>
                        <p><strong>Hourly Rate:</strong> ₹<?php echo number_format($area['hourly_rate'], 2); ?></p>
                    </div>
                    
                    <?php if (count($slots) === 0): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> No slots available in this area.
                        </div>
                        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    <?php elseif (count($vehicles) === 0): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> You need to register a vehicle first.
                        </div>
                        <a href="vehicles.php" class="btn btn-primary">Add Vehicle</a>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="vehicle_id" class="form-label">Select Vehicle</label>
                                <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                    <option value="">-- Select Vehicle --</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo $vehicle['vehicle_id']; ?>">
                                            <?php echo htmlspecialchars($vehicle['vehicle_number']); ?> (<?php echo htmlspecialchars($vehicle['vehicle_type'] ?? 'Car'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="slot_id" class="form-label">Select Slot</label>
                                <select class="form-select" id="slot_id" name="slot_id" required>
                                    <option value="">-- Select Slot --</option>
                                    <?php foreach ($slots as $slot): ?>
                                        <option value="<?php echo $slot['slot_id']; ?>">
                                            <?php echo htmlspecialchars($slot['slot_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Confirm Booking
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error ?: 'Invalid parking area'); ?>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

