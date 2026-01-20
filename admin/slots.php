<?php
require_once '../config/db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

$error = '';
$success = '';
$area_id = intval($_GET['area_id'] ?? 0);

// Get area details if area_id is provided
$area = null;
if ($area_id > 0) {
    $area_stmt = $conn->prepare("SELECT * FROM parking_areas WHERE area_id = ?");
    $area_stmt->bind_param("i", $area_id);
    $area_stmt->execute();
    $area_result = $area_stmt->get_result();
    $area = $area_result->fetch_assoc();
    $area_stmt->close();
}

// Handle add slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slot'])) {
    $area_id = intval($_POST['area_id'] ?? 0);
    $slot_number = trim(strtoupper($_POST['slot_number'] ?? ''));
    $status = $_POST['status'] ?? 'Available';
    
    if (empty($slot_number)) {
        $error = 'Slot number is required';
    } elseif ($area_id <= 0) {
        $error = 'Please select an area';
    } else {
        // Check if slot already exists in this area
        $check_stmt = $conn->prepare("SELECT slot_id FROM parking_slots WHERE area_id = ? AND slot_number = ?");
        $check_stmt->bind_param("is", $area_id, $slot_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Slot number already exists in this area';
        } else {
            $stmt = $conn->prepare("INSERT INTO parking_slots (area_id, slot_number, status) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $area_id, $slot_number, $status);
            
            if ($stmt->execute()) {
                $success = 'Parking slot added successfully';
            } else {
                $error = 'Failed to add slot. Please try again.';
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle update slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_slot'])) {
    $slot_id = intval($_POST['slot_id'] ?? 0);
    $slot_number = trim(strtoupper($_POST['slot_number'] ?? ''));
    $status = $_POST['status'] ?? 'Available';
    
    if (empty($slot_number)) {
        $error = 'Slot number is required';
    } else {
        $stmt = $conn->prepare("UPDATE parking_slots SET slot_number = ?, status = ? WHERE slot_id = ?");
        $stmt->bind_param("ssi", $slot_number, $status, $slot_id);
        
        if ($stmt->execute()) {
            $success = 'Parking slot updated successfully';
        } else {
            $error = 'Failed to update slot. Please try again.';
        }
        $stmt->close();
    }
}

// Handle delete slot
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $slot_id = intval($_GET['delete']);
    
    // Check if slot has active bookings
    $check_stmt = $conn->prepare("SELECT booking_id FROM bookings WHERE slot_id = ? AND status = 'Active'");
    $check_stmt->bind_param("i", $slot_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error = 'Cannot delete slot with active bookings';
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM parking_slots WHERE slot_id = ?");
        $delete_stmt->bind_param("i", $slot_id);
        
        if ($delete_stmt->execute()) {
            $success = 'Parking slot deleted successfully';
        } else {
            $error = 'Failed to delete slot';
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
}

// Get slot to edit
$edit_slot = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $slot_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM parking_slots WHERE slot_id = ?");
    $edit_stmt->bind_param("i", $slot_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    $edit_slot = $edit_result->fetch_assoc();
    if ($edit_slot) {
        $area_id = $edit_slot['area_id'];
        // Get area details
        $area_stmt = $conn->prepare("SELECT * FROM parking_areas WHERE area_id = ?");
        $area_stmt->bind_param("i", $area_id);
        $area_stmt->execute();
        $area_result = $area_stmt->get_result();
        $area = $area_result->fetch_assoc();
        $area_stmt->close();
    }
    $edit_stmt->close();
}

// Get all areas for dropdown
$areas_stmt = $conn->query("SELECT area_id, area_name FROM parking_areas ORDER BY area_name");
$all_areas = $areas_stmt->fetch_all(MYSQLI_ASSOC);
$areas_stmt->close();

// Get slots (filtered by area if provided)
if ($area_id > 0) {
    $slots_stmt = $conn->prepare("
        SELECT ps.*, 
               COUNT(b.booking_id) as booking_count
        FROM parking_slots ps
        LEFT JOIN bookings b ON ps.slot_id = b.slot_id AND b.status = 'Active'
        WHERE ps.area_id = ?
        GROUP BY ps.slot_id
        ORDER BY ps.slot_number
    ");
    $slots_stmt->bind_param("i", $area_id);
    $slots_stmt->execute();
    $slots_result = $slots_stmt->get_result();
    $slots = $slots_result->fetch_all(MYSQLI_ASSOC);
    $slots_stmt->close();
} else {
    $slots = [];
}

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'Manage Parking Slots';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">
            <i class="bi bi-grid"></i> Manage Parking Slots
            <?php if ($area): ?>
                <small class="text-muted">- <?php echo htmlspecialchars($area['area_name']); ?></small>
            <?php endif; ?>
        </h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $edit_slot ? 'pencil' : 'plus-circle'; ?>"></i> 
                    <?php echo $edit_slot ? 'Edit' : 'Add'; ?> Parking Slot
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($edit_slot): ?>
                        <input type="hidden" name="slot_id" value="<?php echo $edit_slot['slot_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="area_id" class="form-label">Parking Area</label>
                        <select class="form-select" id="area_id" name="area_id" required 
                                onchange="if(this.value) window.location.href='slots.php?area_id='+this.value">
                            <option value="">-- Select Area --</option>
                            <?php foreach ($all_areas as $a): ?>
                                <option value="<?php echo $a['area_id']; ?>" 
                                        <?php echo ($a['area_id'] == $area_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a['area_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slot_number" class="form-label">Slot Number</label>
                        <input type="text" class="form-control" id="slot_number" name="slot_number" required
                               value="<?php echo htmlspecialchars($edit_slot['slot_number'] ?? ''); ?>"
                               placeholder="e.g., A1, V1">
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Available" <?php echo ($edit_slot['status'] ?? '') === 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="Occupied" <?php echo ($edit_slot['status'] ?? '') === 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                            <option value="Maintenance" <?php echo ($edit_slot['status'] ?? '') === 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="<?php echo $edit_slot ? 'update_slot' : 'add_slot'; ?>" 
                                class="btn btn-primary" <?php echo $area_id <= 0 ? 'disabled' : ''; ?>>
                            <i class="bi bi-<?php echo $edit_slot ? 'check' : 'plus'; ?>-circle"></i> 
                            <?php echo $edit_slot ? 'Update' : 'Add'; ?> Slot
                        </button>
                        <?php if ($edit_slot): ?>
                            <a href="slots.php?area_id=<?php echo $area_id; ?>" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list"></i> Parking Slots</h5>
                <a href="areas.php" class="btn btn-sm btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Areas
                </a>
            </div>
            <div class="card-body">
                <?php if ($area_id <= 0): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Please select a parking area to view and manage slots.
                    </div>
                <?php elseif (count($slots) === 0): ?>
                    <p class="text-muted text-center py-4">No slots found for this area. Add your first slot.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Slot Number</th>
                                    <th>Status</th>
                                    <th>Active Bookings</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($slots as $slot): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($slot['slot_number']); ?></strong></td>
                                        <td>
                                            <?php if ($slot['status'] === 'Available'): ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php elseif ($slot['status'] === 'Occupied'): ?>
                                                <span class="badge bg-warning">Occupied</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Maintenance</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($slot['booking_count'] > 0): ?>
                                                <span class="badge bg-danger"><?php echo $slot['booking_count']; ?> Active</span>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?edit=<?php echo $slot['slot_id']; ?>&area_id=<?php echo $area_id; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="?delete=<?php echo $slot['slot_id']; ?>&area_id=<?php echo $area_id; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Are you sure you want to delete this slot?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

