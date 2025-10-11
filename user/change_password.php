<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif ($new_password != $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        $error_message = "New password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
    } else {
        // Verify current password
        $conn = get_database_connection();
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!password_verify($current_password, $user['password'])) {
            $error_message = "Current password is incorrect.";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Password changed successfully.";
                
                // Log action if admin
                if ($_SESSION['role'] == 'admin') {
                    log_admin_action($user_id, "Changed own password", null);
                }
                
                // Send notification
                $message = "Your password for CyberCrime Hive has been changed successfully. If you did not make this change, please contact us immediately.";
                create_notification(null, $user_id, 'Email', $message);
            } else {
                $error_message = "Failed to change password.";
            }
            
            $stmt->close();
        }
        
        $conn->close();
    }
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Admin Dashboard</a></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/user/dashboard.php">My Dashboard</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/user/profile.php">My Profile</a></li>
                <li class="breadcrumb-item active">Change Password</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <form method="post" action="" id="changePasswordForm">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button type="button" class="btn btn-outline-secondary" id="toggleCurrentPwd" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" id="toggleCurrentPwdIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <button type="button" class="btn btn-outline-secondary" id="toggleNewPwd" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" id="toggleNewPwdIcon"></i>
                            </button>
                        </div>
                        <div class="form-text">Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPwd" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" id="toggleConfirmPwdIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        For security reasons, choose a strong password that includes uppercase and lowercase letters, numbers, and special characters.
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Change Password
                        </button>
                        <a href="<?php echo SITE_URL; ?>/user/profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Profile
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Client-side validation and password visibility toggles
(function() {
    const form = document.getElementById('changePasswordForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            if (newPassword !== confirmPassword) {
                event.preventDefault();
                alert('New passwords do not match.');
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

    wireToggle('toggleCurrentPwd', 'toggleCurrentPwdIcon', 'current_password');
    wireToggle('toggleNewPwd', 'toggleNewPwdIcon', 'new_password');
    wireToggle('toggleConfirmPwd', 'toggleConfirmPwdIcon', 'confirm_password');
})();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
