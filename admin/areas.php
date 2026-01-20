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

// Handle add area
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_area'])) {
    $area_name = trim($_POST['area_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
    
    if (empty($area_name) || empty($location)) {
        $error = 'Area name and location are required';
    } elseif ($hourly_rate <= 0) {
        $error = 'Hourly rate must be greater than 0';
    } else {
        $stmt = $conn->prepare("INSERT INTO parking_areas (area_name, location, hourly_rate) VALUES (?, ?, ?)");
        $stmt->bind_param("ssd", $area_name, $location, $hourly_rate);
        
        if ($stmt->execute()) {
            $success = 'Parking area added successfully';
        } else {
            $error = 'Failed to add area. Please try again.';
        }
        $stmt->close();
    }
}

// Handle update area
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_area'])) {
    $area_id = intval($_POST['area_id'] ?? 0);
    $area_name = trim($_POST['area_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
    
    if (empty($area_name) || empty($location)) {
        $error = 'Area name and location are required';
    } elseif ($hourly_rate <= 0) {
        $error = 'Hourly rate must be greater than 0';
    } else {
        $stmt = $conn->prepare("UPDATE parking_areas SET area_name = ?, location = ?, hourly_rate = ? WHERE area_id = ?");
        $stmt->bind_param("ssdi", $area_name, $location, $hourly_rate, $area_id);
        
        if ($stmt->execute()) {
            $success = 'Parking area updated successfully';
        } else {
            $error = 'Failed to update area. Please try again.';
        }
        $stmt->close();
    }
}

// Handle delete area
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $area_id = intval($_GET['delete']);
    
    // Check if area has slots
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_slots WHERE area_id = ?");
    $check_stmt->bind_param("i", $area_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $slot_count = $check_result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($slot_count > 0) {
        $error = 'Cannot delete area with existing slots. Delete slots first.';
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM parking_areas WHERE area_id = ?");
        $delete_stmt->bind_param("i", $area_id);
        
        if ($delete_stmt->execute()) {
            $success = 'Parking area deleted successfully';
        } else {
            $error = 'Failed to delete area';
        }
        $delete_stmt->close();
    }
}

// Get area to edit
$edit_area = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $area_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM parking_areas WHERE area_id = ?");
    $edit_stmt->bind_param("i", $area_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    $edit_area = $edit_result->fetch_assoc();
    $edit_stmt->close();
}

// Get all areas with slot counts
$areas_stmt = $conn->query("
    SELECT pa.*, 
           COUNT(ps.slot_id) as total_slots,
           SUM(CASE WHEN ps.status = 'Available' THEN 1 ELSE 0 END) as available_slots,
           SUM(CASE WHEN ps.status = 'Occupied' THEN 1 ELSE 0 END) as occupied_slots
    FROM parking_areas pa
    LEFT JOIN parking_slots ps ON pa.area_id = ps.area_id
    GROUP BY pa.area_id
    ORDER BY pa.area_name
");
$areas = $areas_stmt->fetch_all(MYSQLI_ASSOC);
$areas_stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'Manage Parking Areas';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4"><i class="bi bi-geo-alt"></i> Manage Parking Areas</h2>
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
                    <i class="bi bi-<?php echo $edit_area ? 'pencil' : 'plus-circle'; ?>"></i> 
                    <?php echo $edit_area ? 'Edit' : 'Add'; ?> Parking Area
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php if ($edit_area): ?>
                        <input type="hidden" name="area_id" value="<?php echo $edit_area['area_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="area_name" class="form-label">Area Name</label>
                        <input type="text" class="form-control" id="area_name" name="area_name" required
                               value="<?php echo htmlspecialchars($edit_area['area_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" required
                               value="<?php echo htmlspecialchars($edit_area['location'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="hourly_rate" class="form-label">Hourly Rate (₹)</label>
                        <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" 
                               step="0.01" min="1" required
                               value="<?php echo $edit_area['hourly_rate'] ?? '50.00'; ?>">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="<?php echo $edit_area ? 'update_area' : 'add_area'; ?>" 
                                class="btn btn-primary">
                            <i class="bi bi-<?php echo $edit_area ? 'check' : 'plus'; ?>-circle"></i> 
                            <?php echo $edit_area ? 'Update' : 'Add'; ?> Area
                        </button>
                        <?php if ($edit_area): ?>
                            <a href="areas.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list"></i> All Parking Areas</h5>
            </div>
            <div class="card-body">
                <?php if (count($areas) === 0): ?>
                    <p class="text-muted text-center py-4">No parking areas found. Add your first area.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Area Name</th>
                                    <th>Location</th>
                                    <th>Hourly Rate</th>
                                    <th>Slots</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($areas as $area): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($area['area_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($area['location']); ?></td>
                                        <td>₹<?php echo number_format($area['hourly_rate'], 2); ?>/hr</td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $area['total_slots']; ?> Total</span>
                                            <span class="badge bg-success"><?php echo $area['available_slots']; ?> Available</span>
                                            <span class="badge bg-warning"><?php echo $area['occupied_slots']; ?> Occupied</span>
                                        </td>
                                        <td>
                                            <a href="slots.php?area_id=<?php echo $area['area_id']; ?>" 
                                               class="btn btn-sm btn-info">
                                                <i class="bi bi-grid"></i> Manage Slots
                                            </a>
                                        </td>
                                        <td>
                                            <a href="?edit=<?php echo $area['area_id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <a href="?delete=<?php echo $area['area_id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Are you sure? This will delete the area and all its slots.')">
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

