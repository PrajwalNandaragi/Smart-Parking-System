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

// Filter options
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query
$query = "
    SELECT b.booking_id, b.entry_time, b.exit_time, b.status,
           u.user_id, u.name as user_name, u.email,
           v.vehicle_number,
           pa.area_name, pa.location,
           ps.slot_number,
           p.amount, p.status as payment_status, p.transaction_id
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN parking_slots ps ON b.slot_id = ps.slot_id
    JOIN parking_areas pa ON ps.area_id = pa.area_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR v.vehicle_number LIKE ? OR b.booking_id = ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    if (is_numeric($search)) {
        $params[] = intval($search);
        $types .= 'sssi';
    } else {
        $params[] = 0;
        $types .= 'sssi';
    }
}

$query .= " ORDER BY b.entry_time DESC LIMIT 100";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bookings_result = $stmt->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'View Bookings';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4"><i class="bi bi-calendar-check"></i> View All Bookings</h2>
    </div>
</div>

<!-- Filters -->
<div class="row mb-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Filter by Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Search by name, email, vehicle number, or booking ID"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bookings Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Bookings List (<?php echo count($bookings); ?> found)</h5>
            </div>
            <div class="card-body">
                <?php if (count($bookings) === 0): ?>
                    <p class="text-muted text-center py-4">No bookings found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>User</th>
                                    <th>Vehicle</th>
                                    <th>Area</th>
                                    <th>Slot</th>
                                    <th>Entry Time</th>
                                    <th>Exit Time</th>
                                    <th>Duration</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): 
                                    $entry_time = new DateTime($booking['entry_time']);
                                    $duration_text = 'N/A';
                                    
                                    if ($booking['exit_time']) {
                                        $exit_time = new DateTime($booking['exit_time']);
                                        $duration = $exit_time->diff($entry_time);
                                        $hours = $duration->h + ($duration->days * 24) + ($duration->i / 60);
                                        $duration_text = $duration->format('%h:%I hours');
                                    } else {
                                        $now = new DateTime();
                                        $duration = $now->diff($entry_time);
                                        $hours = $duration->h + ($duration->days * 24) + ($duration->i / 60);
                                        $duration_text = $duration->format('%h:%I hours') . ' (ongoing)';
                                    }
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo $booking['booking_id']; ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['user_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($booking['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['area_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($booking['location']); ?></small>
                                        </td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($booking['slot_number']); ?></span></td>
                                        <td><?php echo $entry_time->format('Y-m-d H:i:s'); ?></td>
                                        <td>
                                            <?php echo $booking['exit_time'] ? date('Y-m-d H:i:s', strtotime($booking['exit_time'])) : '-'; ?>
                                        </td>
                                        <td><?php echo $duration_text; ?></td>
                                        <td>
                                            <?php if ($booking['amount']): ?>
                                                <strong>₹<?php echo number_format($booking['amount'], 2); ?></strong>
                                                <?php if ($booking['transaction_id']): ?>
                                                    <br><small class="text-muted"><code><?php echo htmlspecialchars($booking['transaction_id']); ?></code></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] === 'Active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($booking['status'] === 'Completed'): ?>
                                                <span class="badge bg-info">Completed</span>
                                                <?php if ($booking['payment_status']): ?>
                                                    <br>
                                                    <?php if ($booking['payment_status'] === 'SUCCESS'): ?>
                                                        <span class="badge bg-success mt-1">Paid</span>
                                                    <?php elseif ($booking['payment_status'] === 'FAILED'): ?>
                                                        <span class="badge bg-danger mt-1">Payment Failed</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($booking['status']); ?></span>
                                            <?php endif; ?>
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

