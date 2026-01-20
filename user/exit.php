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
$booking_id = intval($_GET['booking_id'] ?? 0);

$error = '';
$success = '';

// Get booking details
$booking_stmt = $conn->prepare("
    SELECT b.booking_id, b.user_id, b.vehicle_id, b.slot_id, b.entry_time,
           pa.area_id, pa.hourly_rate
    FROM bookings b
    JOIN parking_slots ps ON b.slot_id = ps.slot_id
    JOIN parking_areas pa ON ps.area_id = pa.area_id
    WHERE b.booking_id = ? AND b.user_id = ? AND b.status = 'Active'
");
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();

if ($booking_result->num_rows === 0) {
    $error = 'Invalid or inactive booking';
    $booking = null;
} else {
    $booking = $booking_result->fetch_assoc();
}
$booking_stmt->close();

// Process exit and auto-pay
if ($booking && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get current real-time from database server
    $time_result = $conn->query("SELECT NOW() as exit_datetime");
    $time_row = $time_result->fetch_assoc();
    $exit_time = $time_row['exit_datetime'];
    $time_result->close();
    
    $entry_time = new DateTime($booking['entry_time']);
    $exit_datetime = new DateTime($exit_time);
    
    // Calculate duration in hours
    $duration = $exit_datetime->diff($entry_time);
    $hours = $duration->h + ($duration->days * 24) + ($duration->i / 60);
    $hours = max(1, ceil($hours)); // Minimum 1 hour, round up
    
    // Calculate amount
    $amount = $hours * $booking['hourly_rate'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get wallet balance
        $wallet_stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE");
        $wallet_stmt->bind_param("i", $user_id);
        $wallet_stmt->execute();
        $wallet_result = $wallet_stmt->get_result();
        $wallet = $wallet_result->fetch_assoc();
        $current_balance = $wallet ? $wallet['balance'] : 0.00;
        $wallet_stmt->close();
        
        // Check if sufficient balance
        if ($current_balance < $amount) {
            throw new Exception('Insufficient wallet balance. Please recharge your wallet.');
        }
        
        // Update booking with current real-time from database server
        $update_booking = $conn->prepare("
            UPDATE bookings 
            SET exit_time = NOW(), status = 'Completed' 
            WHERE booking_id = ?
        ");
        $update_booking->bind_param("i", $booking_id);
        $update_booking->execute();
        $update_booking->close();
        
        // Update slot status back to Available
        $update_slot = $conn->prepare("UPDATE parking_slots SET status = 'Available' WHERE slot_id = ?");
        $update_slot->bind_param("i", $booking['slot_id']);
        $update_slot->execute();
        $update_slot->close();
        
        // Deduct amount from wallet
        $new_balance = $current_balance - $amount;
        $update_wallet = $conn->prepare("UPDATE wallet SET balance = ? WHERE user_id = ?");
        $update_wallet->bind_param("di", $new_balance, $user_id);
        $update_wallet->execute();
        $update_wallet->close();
        
        // Generate transaction ID
        $transaction_id = 'TXN' . date('YmdHis') . rand(1000, 9999);
        
        // Insert payment record
        $payment_stmt = $conn->prepare("
            INSERT INTO payments (booking_id, amount, status, transaction_id) 
            VALUES (?, ?, 'SUCCESS', ?)
        ");
        $payment_stmt->bind_param("ids", $booking_id, $amount, $transaction_id);
        $payment_stmt->execute();
        $payment_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $success = 'Exit processed successfully! Payment deducted from wallet.';
        header('Location: bookings.php?exit_success=1&amount=' . $amount . '&transaction_id=' . $transaction_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
        
        // If payment failed, still update booking but mark payment as failed
        if (strpos($error, 'Insufficient') !== false) {
            try {
                $conn->begin_transaction();
                
                // Update booking with current real-time from database server
                $update_booking = $conn->prepare("
                    UPDATE bookings 
                    SET exit_time = NOW(), status = 'Completed' 
                    WHERE booking_id = ?
                ");
                $update_booking->bind_param("i", $booking_id);
                $update_booking->execute();
                $update_booking->close();
                
                // Update slot
                $update_slot = $conn->prepare("UPDATE parking_slots SET status = 'Available' WHERE slot_id = ?");
                $update_slot->bind_param("i", $booking['slot_id']);
                $update_slot->execute();
                $update_slot->close();
                
                // Record failed payment
                $transaction_id = 'TXN' . date('YmdHis') . rand(1000, 9999);
                $payment_stmt = $conn->prepare("
                    INSERT INTO payments (booking_id, amount, status, transaction_id) 
                    VALUES (?, ?, 'FAILED', ?)
                ");
                $payment_stmt->bind_param("ids", $booking_id, $amount, $transaction_id);
                $payment_stmt->execute();
                $payment_stmt->close();
                
                $conn->commit();
            } catch (Exception $e2) {
                $conn->rollback();
            }
        }
    }
}

// Calculate estimated cost if booking exists
$estimated_hours = 0;
$estimated_cost = 0;
if ($booking) {
    $entry_time = new DateTime($booking['entry_time']);
    $now = new DateTime();
    $duration = $now->diff($entry_time);
    $estimated_hours = $duration->h + ($duration->days * 24) + ($duration->i / 60);
    $estimated_hours = max(1, ceil($estimated_hours));
    $estimated_cost = $estimated_hours * $booking['hourly_rate'];
    
    // Get current wallet balance
    $wallet_stmt = $conn->prepare("SELECT balance FROM wallet WHERE user_id = ?");
    $wallet_stmt->bind_param("i", $user_id);
    $wallet_stmt->execute();
    $wallet_result = $wallet_stmt->get_result();
    $wallet = $wallet_result->fetch_assoc();
    $current_balance = $wallet ? $wallet['balance'] : 0.00;
    $wallet_stmt->close();
}

closeDBConnection($conn);

include '../includes/header.php';
$page_title = 'Exit Parking';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0"><i class="bi bi-box-arrow-right"></i> Exit Parking</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($booking): ?>
                    <div class="alert alert-info">
                        <h5>Booking Details</h5>
                        <p><strong>Entry Time:</strong> <?php echo date('Y-m-d H:i:s', strtotime($booking['entry_time'])); ?></p>
                        <p><strong>Estimated Duration:</strong> <?php echo $estimated_hours; ?> hour(s)</p>
                        <p><strong>Estimated Cost:</strong> ₹<?php echo number_format($estimated_cost, 2); ?></p>
                        <p><strong>Current Wallet Balance:</strong> ₹<?php echo number_format($current_balance, 2); ?></p>
                        
                        <?php if ($current_balance < $estimated_cost): ?>
                            <div class="alert alert-warning mt-2">
                                <i class="bi bi-exclamation-triangle"></i> 
                                Insufficient balance! Please recharge your wallet before exiting.
                                <br><a href="wallet.php" class="alert-link">Recharge Wallet</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to exit? Payment will be processed automatically from your wallet.');">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-lg" 
                                    <?php echo ($current_balance < $estimated_cost) ? 'disabled' : ''; ?>>
                                <i class="bi bi-box-arrow-right"></i> Confirm Exit & Process Payment
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error ?: 'Invalid booking'); ?>
                    </div>
                    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

