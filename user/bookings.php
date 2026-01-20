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

// Show success message if redirected from exit
if (isset($_GET['exit_success'])) {
    $amount = $_GET['amount'] ?? 0;
    $transaction_id = $_GET['transaction_id'] ?? '';
}

// Get all bookings
$bookings_stmt = $conn->prepare("
    SELECT b.booking_id, b.entry_time, b.exit_time, b.status,
           v.vehicle_number,
           pa.area_name, pa.location, pa.hourly_rate,
           ps.slot_number,
           p.amount, p.status as payment_status, p.transaction_id
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN parking_slots ps ON b.slot_id = ps.slot_id
    JOIN parking_areas pa ON ps.area_id = pa.area_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    WHERE b.user_id = ?
    ORDER BY b.entry_time DESC
");
$bookings_stmt->bind_param("i", $user_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
$bookings_stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'My Bookings';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4"><i class="bi bi-calendar-check"></i> My Bookings</h2>
    </div>
</div>

<?php if (isset($_GET['exit_success'])): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> 
        Exit processed successfully! 
        Amount deducted: ₹<?php echo number_format($amount, 2); ?>
        <br>Transaction ID: <code><?php echo htmlspecialchars($transaction_id); ?></code>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Booking History</h5>
            </div>
            <div class="card-body">
                <?php if (count($bookings) === 0): ?>
                    <p class="text-muted text-center py-4">No bookings found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Area</th>
                                    <th>Slot</th>
                                    <th>Entry Time</th>
                                    <th>Exit Time</th>
                                    <th>Duration</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): 
                                    $entry_time = new DateTime($booking['entry_time']);
                                    $duration_text = 'N/A';
                                    $hours = 0;
                                    
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
                                                <small class="text-muted">₹<?php echo number_format($hours * $booking['hourly_rate'], 2); ?> (estimated)</small>
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
                                        <td>
                                            <?php if ($booking['status'] === 'Active'): ?>
                                                <a href="exit.php?booking_id=<?php echo $booking['booking_id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Are you sure you want to exit? Payment will be processed automatically.')">
                                                    <i class="bi bi-box-arrow-right"></i> Exit
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
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

