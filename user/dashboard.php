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

// Get user's wallet balance
$wallet_stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
$wallet_stmt->bind_param("i", $user_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
$wallet = $wallet_result->fetch_assoc();
$balance = $wallet ? $wallet['balance'] : 0.00;
$wallet_stmt->close();

// Get user's active bookings
$active_bookings_stmt = $conn->prepare("
    SELECT b.booking_id, b.entry_time, v.vehicle_number, 
           pa.area_name, ps.slot_number, pa.hourly_rate
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN parking_slots ps ON b.slot_id = ps.slot_id
    JOIN parking_areas pa ON ps.area_id = pa.area_id
    WHERE b.user_id = ? AND b.status = 'Active'
    ORDER BY b.entry_time DESC
");
$active_bookings_stmt->bind_param("i", $user_id);
$active_bookings_stmt->execute();
$active_bookings = $active_bookings_stmt->get_result();
$active_bookings_stmt->close();

// Get available parking areas with slot counts
$areas_stmt = $conn->query("
    SELECT pa.area_id, pa.area_name, pa.location, pa.hourly_rate,
           COUNT(ps.slot_id) as total_slots,
           SUM(CASE WHEN ps.status = 'Available' THEN 1 ELSE 0 END) as available_slots
    FROM parking_areas pa
    LEFT JOIN parking_slots ps ON pa.area_id = ps.area_id
    GROUP BY pa.area_id, pa.area_name, pa.location, pa.hourly_rate
    ORDER BY pa.area_name
");
$areas = $areas_stmt->fetch_all(MYSQLI_ASSOC);
$areas_stmt->close();

// Get user's vehicles
$vehicles_stmt = $conn->prepare("SELECT vehicle_id, vehicle_number, vehicle_type FROM vehicles WHERE user_id = ?");
$vehicles_stmt->bind_param("i", $user_id);
$vehicles_stmt->execute();
$vehicles_result = $vehicles_stmt->get_result();
$vehicles = $vehicles_result->fetch_all(MYSQLI_ASSOC);
$vehicles_stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'User Dashboard';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
    </div>
</div>

<!-- Wallet Balance Card -->
<div class="row">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-wallet2"></i> Wallet Balance</h5>
                <h2 class="mb-0">₹<?php echo number_format($balance, 2); ?></h2>
                <a href="wallet.php" class="text-white text-decoration-none">
                    <small>Manage Wallet <i class="bi bi-arrow-right"></i></small>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-calendar-check"></i> Active Bookings</h5>
                <h2 class="mb-0"><?php echo $active_bookings->num_rows; ?></h2>
                <a href="bookings.php" class="text-white text-decoration-none">
                    <small>View All <i class="bi bi-arrow-right"></i></small>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-car-front"></i> Registered Vehicles</h5>
                <h2 class="mb-0"><?php echo count($vehicles); ?></h2>
                <a href="vehicles.php" class="text-white text-decoration-none">
                    <small>Manage Vehicles <i class="bi bi-arrow-right"></i></small>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Active Bookings -->
<?php if ($active_bookings->num_rows > 0): ?>
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Active Bookings</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Area</th>
                                <th>Slot</th>
                                <th>Entry Time</th>
                                <th>Duration</th>
                                <th>Rate</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $active_bookings->fetch_assoc()): 
                                $entry_time = new DateTime($booking['entry_time']);
                                $now = new DateTime();
                                $duration = $now->diff($entry_time);
                                $hours = $duration->h + ($duration->days * 24) + ($duration->i / 60);
                                $estimated_cost = $hours * $booking['hourly_rate'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['area_name']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($booking['slot_number']); ?></span></td>
                                    <td><?php echo $entry_time->format('Y-m-d H:i:s'); ?></td>
                                    <td><?php echo $duration->format('%h:%I hours'); ?></td>
                                    <td>₹<?php echo number_format($booking['hourly_rate'], 2); ?>/hr</td>
                                    <td>
                                        <a href="exit.php?booking_id=<?php echo $booking['booking_id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to exit? Payment will be processed automatically.')">
                                            <i class="bi bi-box-arrow-right"></i> Exit
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Available Parking Areas -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-p-square"></i> Available Parking Areas</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($areas as $area): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($area['area_name']); ?></h5>
                                    <p class="text-muted mb-2">
                                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($area['location']); ?>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Rate:</strong> ₹<?php echo number_format($area['hourly_rate'], 2); ?>/hour
                                    </p>
                                    <p class="mb-3">
                                        <span class="badge bg-success"><?php echo $area['available_slots']; ?> Available</span>
                                        <span class="badge bg-secondary"><?php echo $area['total_slots']; ?> Total</span>
                                    </p>
                                    <?php if ($area['available_slots'] > 0): ?>
                                        <a href="book_slot.php?area_id=<?php echo $area['area_id']; ?>" 
                                           class="btn btn-primary btn-sm w-100">
                                            <i class="bi bi-bookmark-plus"></i> Book Slot
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm w-100" disabled>
                                            <i class="bi bi-x-circle"></i> No Slots Available
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

