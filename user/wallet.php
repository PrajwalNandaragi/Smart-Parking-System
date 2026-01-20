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

// Handle wallet recharge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recharge'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    
    if ($amount <= 0) {
        $error = 'Invalid amount';
    } elseif ($amount > 10000) {
        $error = 'Maximum recharge amount is ₹10,000';
    } else {
        // Get current balance
        $wallet_stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE");
        $wallet_stmt->bind_param("i", $user_id);
        $wallet_stmt->execute();
        $wallet_result = $wallet_stmt->get_result();
        $wallet = $wallet_result->fetch_assoc();
        $current_balance = $wallet ? $wallet['balance'] : 0.00;
        $wallet_stmt->close();
        
        // Update balance
        $new_balance = $current_balance + $amount;
        $update_stmt = $conn->prepare("UPDATE wallet SET balance = ? WHERE user_id = ?");
        $update_stmt->bind_param("di", $new_balance, $user_id);
        
        if ($update_stmt->execute()) {
            $success = 'Wallet recharged successfully with ₹' . number_format($amount, 2);
        } else {
            $error = 'Recharge failed. Please try again.';
        }
        $update_stmt->close();
    }
}

// Get current wallet balance
$wallet_stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
$wallet_stmt->bind_param("i", $user_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
$wallet = $wallet_result->fetch_assoc();
$balance = $wallet ? $wallet['balance'] : 0.00;
$wallet_stmt->close();

// Get payment history
$payments_stmt = $conn->prepare("
    SELECT p.payment_id, p.amount, p.status, p.transaction_id, p.created_at,
           b.booking_id, v.vehicle_number, pa.area_name, ps.slot_number
    FROM payments p
    JOIN bookings b ON p.booking_id = b.booking_id
    JOIN vehicles v ON b.vehicle_id = v.vehicle_id
    JOIN parking_slots ps ON b.slot_id = ps.slot_id
    JOIN parking_areas pa ON ps.area_id = pa.area_id
    WHERE b.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 50
");
$payments_stmt->bind_param("i", $user_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
$payments = $payments_result->fetch_all(MYSQLI_ASSOC);
$payments_stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'Wallet Management';
?>

<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4"><i class="bi bi-wallet2"></i> Wallet Management</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Wallet Balance Card -->
<div class="row">
    <div class="col-md-6">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h5 class="card-title"><i class="bi bi-wallet2"></i> Current Balance</h5>
                <h1 class="display-4 mb-0">₹<?php echo number_format($balance, 2); ?></h1>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Recharge Wallet</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount (₹)</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               min="100" max="10000" step="100" required placeholder="Enter amount">
                        <small class="text-muted">Minimum: ₹100, Maximum: ₹10,000</small>
                    </div>
                    <button type="submit" name="recharge" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle"></i> Recharge
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Payment History -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Payment History</h5>
            </div>
            <div class="card-body">
                <?php if (count($payments) === 0): ?>
                    <p class="text-muted text-center py-4">No payment history found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Date & Time</th>
                                    <th>Vehicle</th>
                                    <th>Area</th>
                                    <th>Slot</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($payment['transaction_id']); ?></code></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($payment['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['vehicle_number']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['area_name']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($payment['slot_number']); ?></span></td>
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

