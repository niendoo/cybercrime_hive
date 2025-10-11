<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to appropriate dashboard based on role
    if ($_SESSION['role'] == 'admin') {
        header("Location: " . SITE_URL . "/admin/dashboard.php");
    } else {
        header("Location: " . SITE_URL . "/user/dashboard.php");
    }
    exit();
}

$error_message = '';
$success_message = '';
$token_valid = false;
$user_id = null;

// Check if token is provided
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $conn = get_database_connection();
    
    // Validate token directly from users table
    $stmt = $conn->prepare("SELECT user_id, username, reset_token_expires 
                        FROM users 
                        WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $reset = $result->fetch_assoc();
        $expires_at = new DateTime($reset['reset_token_expires']);
        $now = new DateTime();
        
        if ($now < $expires_at) {
            $token_valid = true;
            $user_id = $reset['user_id'];
            $username = $reset['username'];
        } else {
            $error_message = 'Password reset link has expired. Please request a new one.';
        }
    } else {
        $error_message = 'Invalid or already used password reset link.';
    }
    
    $stmt->close();
    
    // Process password reset form
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token_valid) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($password) || empty($confirm_password)) {
            $error_message = 'All fields are required.';
        } elseif ($password != $confirm_password) {
            $error_message = 'Passwords do not match.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error_message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        } else {
            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user password
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                // Clear the reset token from the user's record
                $clear_token_stmt = $conn->prepare("UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE user_id = ?");
                $clear_token_stmt->bind_param("i", $user_id);
                $clear_token_stmt->execute();
                $clear_token_stmt->close();
                
                $success_message = 'Your password has been reset successfully! You can now login with your new password.';
                $token_valid = false; // Hide the form
                
                // Send confirmation email
                $message = "Hello $username,\n\nYour password has been reset successfully. If you did not perform this action, please contact us immediately.\n\nRegards,\n" . SITE_NAME . " Team";
                create_notification(null, $user_id, 'Email', $message);
                
                // Check if user_logs table exists before logging
                $user_logs_exists = $conn->query("SHOW TABLES LIKE 'user_logs'")->num_rows > 0;
                if ($user_logs_exists) {
                    // Log the action
                    log_action($user_id, 'Password reset', null);
                }
            } else {
                $error_message = 'Failed to reset password. Please try again.';
            }
            
            $update_stmt->close();
        }
    }
    
    $conn->close();
} else {
    $error_message = 'No password reset token provided.';
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<main class="flex-grow-1">
<div class="container py-5 my-4 px-4">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8">
            <div class="text-center mb-4">
                <h1 class="h3 text-gray-900 mb-2">Reset Your Password</h1>
                <p class="text-muted">Create a new secure password for your account</p>
            </div>
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-gradient-primary-to-secondary">
                    <h4 class="mb-0 text-center"><i class="fas fa-key me-2"></i>Reset Password</h4>
                </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if ($token_valid): ?>
                    <div class="p-3">
                        <p class="mb-4 text-gray-700"><i class="fas fa-info-circle text-primary me-2"></i>Enter your new password below.</p>
                        
                        <form method="post" action="" id="resetPasswordForm">
                            <div class="mb-4">
                                <label for="password" class="form-label small text-gray-600 fw-bold">New Password</label>
                                <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light text-primary"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control bg-light" id="password" name="password" placeholder="Create a new password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                <button type="button" class="btn btn-outline-secondary" id="toggleResetPwd" aria-label="Show password" aria-pressed="false">
                                    <i class="fas fa-eye" id="toggleResetPwdIcon"></i>
                                </button>
                            </div>
                                <div class="form-text small"><i class="fas fa-shield-alt me-1"></i>Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label small text-gray-600 fw-bold">Confirm New Password</label>
                                <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light text-primary"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control bg-light" id="confirm_password" name="confirm_password" placeholder="Confirm your new password" required>
                                <button type="button" class="btn btn-outline-secondary" id="toggleResetConfirm" aria-label="Show password" aria-pressed="false">
                                    <i class="fas fa-eye" id="toggleResetConfirmIcon"></i>
                                </button>
                            </div>
                            </div>
                            
                            <div class="d-grid gap-2 mt-5">
                                <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center">
                        <?php if ($success_message): ?>
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                <p class="text-gray-700">Your password has been successfully reset. You can now login with your new credentials.</p>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt me-2"></i>Go to Login</a>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                                <p class="text-gray-700">The password reset link appears to be invalid or expired.</p>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/auth/forgot_password.php" class="btn btn-primary btn-lg"><i class="fas fa-key me-2"></i>Request New Reset Link</a>
                                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Login</a>
                            </div>
                        <?php endif; ?>
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

<script>
// Client-side validation and password visibility toggles
document.addEventListener('DOMContentLoaded', function() {
    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match.');
            }
        });
    }
    function wireToggle(btnId, iconId, inputId) {
        const btn = document.getElementById(btnId);
        const icon = document.getElementById(iconId);
        const input = document.getElementById(inputId);
        if (!btn || !icon || !input) return;
        btn.addEventListener('click', function() {
            const isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            this.setAttribute('aria-pressed', String(!isText));
            if (isText) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                this.setAttribute('aria-label', 'Show password');
            } else {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                this.setAttribute('aria-label', 'Hide password');
            }
        });
    }
    wireToggle('toggleResetPwd', 'toggleResetPwdIcon', 'password');
    wireToggle('toggleResetConfirm', 'toggleResetConfirmIcon', 'confirm_password');
});
</script>

</main>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
