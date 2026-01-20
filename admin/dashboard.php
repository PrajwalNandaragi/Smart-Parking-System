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

// Get statistics
$stats = [];

// Total areas
$areas_stmt = $conn->query("SELECT COUNT(*) as count FROM parking_areas");
$stats['total_areas'] = $areas_stmt->fetch_assoc()['count'];
$areas_stmt->close();

// Total slots
$slots_stmt = $conn->query("SELECT COUNT(*) as count FROM parking_slots");
$stats['total_slots'] = $slots_stmt->fetch_assoc()['count'];
$slots_stmt->close();

// Available slots
$available_stmt = $conn->query("SELECT COUNT(*) as count FROM parking_slots WHERE status = 'Available'");
$stats['available_slots'] = $available_stmt->fetch_assoc()['count'];
$available_stmt->close();

// Occupied slots
$occupied_stmt = $conn->query("SELECT COUNT(*) as count FROM parking_slots WHERE status = 'Occupied'");
$stats['occupied_slots'] = $occupied_stmt->fetch_assoc()['count'];
$occupied_stmt->close();

// Total bookings
$bookings_stmt = $conn->query("SELECT COUNT(*) as count FROM bookings");
$stats['total_bookings'] = $bookings_stmt->fetch_assoc()['count'];
$bookings_stmt->close();

// Active bookings
$active_stmt = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'Active'");
$stats['active_bookings'] = $active_stmt->fetch_assoc()['count'];
$active_stmt->close();

// Total revenue
$revenue_stmt = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'SUCCESS'");
$revenue_result = $revenue_stmt->fetch_assoc();
$stats['total_revenue'] = $revenue_result['total'] ?? 0.00;
$revenue_stmt->close();

// Total users
$users_stmt = $conn->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $users_stmt->fetch_assoc()['count'];
$users_stmt->close();

// Recent bookings
$recent_bookings_stmt = $conn->query("
    SELECT b.booking_id, b.entry_time, b.status,
           u.name as user_name, u.email,
           v.vehicle_number,
           pa.area_name, ps.slot_number,
           p.amount, p.status as payment_status
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN parking_slots ps ON b.slot_id = ps.slot_id
    JOIN parking_areas pa ON ps.area_id = pa.area_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    ORDER BY b.entry_time DESC
    LIMIT 10
");
$recent_bookings = $recent_bookings_stmt->fetch_all(MYSQLI_ASSOC);
$recent_bookings_stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'Admin Dashboard';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">Admin Dashboard <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></small></h2>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-p-square"></i> Total Areas</h5>
                <h2 class="mb-0"><?php echo $stats['total_areas']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-grid"></i> Total Slots</h5>
                <h2 class="mb-0"><?php echo $stats['total_slots']; ?></h2>
                <small>Available: <?php echo $stats['available_slots']; ?> | Occupied: <?php echo $stats['occupied_slots']; ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-calendar-check"></i> Bookings</h5>
                <h2 class="mb-0"><?php echo $stats['total_bookings']; ?></h2>
                <small>Active: <?php echo $stats['active_bookings']; ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-currency-rupee"></i> Total Revenue</h5>
                <h2 class="mb-0">₹<?php echo number_format($stats['total_revenue'], 2); ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="areas.php" class="btn btn-primary w-100">
                            <i class="bi bi-geo-alt"></i> Manage Areas
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="slots.php" class="btn btn-info w-100">
                            <i class="bi bi-grid"></i> Manage Slots
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="bookings.php" class="btn btn-success w-100">
                            <i class="bi bi-calendar-check"></i> View Bookings
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="payments.php" class="btn btn-warning w-100">
                            <i class="bi bi-credit-card"></i> View Payments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Bookings -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Bookings</h5>
            </div>
            <div class="card-body">
                <?php if (count($recent_bookings) === 0): ?>
                    <p class="text-muted text-center py-4">No bookings found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Vehicle</th>
                                    <th>Area</th>
                                    <th>Slot</th>
                                    <th>Entry Time</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td>#<?php echo $booking['booking_id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['user_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($booking['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['area_name']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($booking['slot_number']); ?></span></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($booking['entry_time'])); ?></td>
                                        <td>
                                            <?php if ($booking['amount']): ?>
                                                ₹<?php echo number_format($booking['amount'], 2); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($booking['status'] === 'Active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php elseif ($booking['status'] === 'Completed'): ?>
                                                <span class="badge bg-info">Completed</span>
                                                <?php if ($booking['payment_status'] === 'SUCCESS'): ?>
                                                    <br><span class="badge bg-success mt-1">Paid</span>
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
                    <div class="text-center mt-3">
                        <a href="bookings.php" class="btn btn-primary">View All Bookings</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

