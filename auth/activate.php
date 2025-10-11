<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$error_message = '';
$success_message = '';

// Check if token is provided
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $conn = get_database_connection();
    
    // Validate token and activate account
    $stmt = $conn->prepare("SELECT user_id, activation_expires FROM users WHERE activation_token = ? AND status = 'Inactive'");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['user_id'];
        $expires = new DateTime($user['activation_expires']);
        $now = new DateTime();
        
        if ($now < $expires) {
            // Activate the account
            $update = $conn->prepare("UPDATE users SET status = 'Active', activation_token = NULL WHERE user_id = ?");
            $update->bind_param("i", $user_id);
            
            if ($update->execute()) {
                $success_message = 'Your account has been activated successfully! You can now login.';
                
                // Send welcome notification
                $message = "Your account has been activated! You can now login and submit reports.";
                create_notification(null, $user_id, 'Email', $message);
            } else {
                $error_message = 'Failed to activate your account. Please try again.';
            }
            $update->close();
        } else {
            $error_message = 'Activation link has expired. Please register again.';
        }
    } else {
        $error_message = 'Invalid activation token or account already activated.';
    }
    
    $stmt->close();
    $conn->close();
} else {
    $error_message = 'No activation token provided.';
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<main class="flex-grow-1">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8">
            <div class="text-center mb-4">
                <h1 class="h3 text-gray-900 mb-2">Account Activation</h1>
                <p class="text-muted">Verifying your account activation status</p>
            </div>
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-gradient-primary-to-secondary">
                    <h4 class="mb-0 text-center"><i class="fas fa-user-check me-2"></i>Account Activation</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($error_message): ?>
                        <div class="text-center mb-4">
                            <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                            <div class="alert alert-danger">
                                <?php echo $error_message; ?>
                            </div>
                            <div class="mt-4">
                                <a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Register Again
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="text-center mb-4">
                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                            <div class="alert alert-success">
                                <?php echo $success_message; ?>
                            </div>
                            <div class="mt-4">
                                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login Now
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light py-3">
                    <div class="text-center">
                        <p class="mb-0 text-gray-600"><a href="<?php echo SITE_URL; ?>/auth/login.php" class="fw-bold text-primary"><i class="fas fa-arrow-left me-1"></i>Back to Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<?php
include dirname(__DIR__) . '/includes/footer.php';
?>
