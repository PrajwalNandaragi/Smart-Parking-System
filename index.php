<?php
require_once 'config/db.php';
include 'includes/header.php';
$page_title = 'Home - Smart Parking System';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <h1 class="display-4 mb-4">
                    <i class="bi bi-p-square text-primary"></i> Smart Parking System
                </h1>
                <p class="lead mb-4">Efficient parking slot management with automatic payment processing</p>
                
                <?php if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])): ?>
                    <div class="mt-4">
                        <a href="user/register.php" class="btn btn-primary btn-lg me-2">
                            <i class="bi bi-person-plus"></i> Register as User
                        </a>
                        <a href="user/login.php" class="btn btn-outline-primary btn-lg me-2">
                            <i class="bi bi-box-arrow-in-right"></i> User Login
                        </a>
                        <a href="admin/login.php" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-shield-lock"></i> Admin Login
                        </a>
                    </div>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <div class="mt-4">
                        <a href="user/dashboard.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-speedometer2"></i> Go to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <div class="mt-4">
                        <a href="admin/dashboard.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-speedometer2"></i> Go to Admin Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-clock-history display-4 text-primary mb-3"></i>
                <h5>24/7 Availability</h5>
                <p class="text-muted">Access parking slots anytime, anywhere</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-credit-card display-4 text-success mb-3"></i>
                <h5>Auto-Pay System</h5>
                <p class="text-muted">Automatic payment processing on exit</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-shield-check display-4 text-info mb-3"></i>
                <h5>Secure & Reliable</h5>
                <p class="text-muted">Your data and payments are safe</p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

