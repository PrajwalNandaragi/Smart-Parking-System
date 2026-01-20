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
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "
    SELECT p.payment_id, p.amount, p.status, p.transaction_id, p.created_at,
           b.booking_id, b.entry_time, b.exit_time,
           u.name as user_name, u.email,
           v.vehicle_number,
           pa.area_name, ps.slot_number
    FROM payments p
    JOIN bookings b ON p.booking_id = b.booking_id
    JOIN users u ON b.user_id = u.user_id
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN parking_slots ps ON b.slot_id = ps.slot_id
    JOIN parking_areas pa ON ps.area_id = pa.area_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($status_filter !== 'all') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $query .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $query .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$query .= " ORDER BY p.created_at DESC LIMIT 200";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments_result = $stmt->get_result();
$payments = $payments_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get revenue summary
$summary_query = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as successful_payments,
        SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_payments,
        SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END) as total_revenue
    FROM payments
    WHERE 1=1
";

$summary_params = [];
$summary_types = '';

if (!empty($date_from)) {
    $summary_query .= " AND DATE(created_at) >= ?";
    $summary_params[] = $date_from;
    $summary_types .= 's';
}

if (!empty($date_to)) {
    $summary_query .= " AND DATE(created_at) <= ?";
    $summary_params[] = $date_to;
    $summary_types .= 's';
}

$summary_stmt = $conn->prepare($summary_query);
if (!empty($summary_params)) {
    $summary_stmt->bind_param($summary_types, ...$summary_params);
}
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();
$summary_stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'View Payments';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4"><i class="bi bi-credit-card"></i> Payment History & Revenue</h2>
    </div>
</div>

<!-- Revenue Summary -->
<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-title">Total Payments</h6>
                <h3 class="mb-0"><?php echo $summary['total_payments']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title">Successful</h6>
                <h3 class="mb-0"><?php echo $summary['successful_payments']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6 class="card-title">Failed</h6>
                <h3 class="mb-0"><?php echo $summary['failed_payments']; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6 class="card-title">Total Revenue</h6>
                <h3 class="mb-0">₹<?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Filter by Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="SUCCESS" <?php echo $status_filter === 'SUCCESS' ? 'selected' : ''; ?>>Success</option>
                            <option value="FAILED" <?php echo $status_filter === 'FAILED' ? 'selected' : ''; ?>>Failed</option>
                            <option value="PENDING" <?php echo $status_filter === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Payments Table -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Payments List (<?php echo count($payments); ?> found)</h5>
            </div>
            <div class="card-body">
                <?php if (count($payments) === 0): ?>
                    <p class="text-muted text-center py-4">No payments found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Date & Time</th>
                                    <th>User</th>
                                    <th>Vehicle</th>
                                    <th>Area</th>
                                    <th>Slot</th>
                                    <th>Booking ID</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($payment['transaction_id']); ?></code></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($payment['created_at'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($payment['user_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['vehicle_number']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['area_name']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($payment['slot_number']); ?></span></td>
                                        <td>#<?php echo $payment['booking_id']; ?></td>
                                        <td><strong>₹<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                        <td>
                                            <?php if ($payment['status'] === 'SUCCESS'): ?>
                                                <span class="badge bg-success">SUCCESS</span>
                                            <?php elseif ($payment['status'] === 'FAILED'): ?>
                                                <span class="badge bg-danger">FAILED</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">PENDING</span>
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

