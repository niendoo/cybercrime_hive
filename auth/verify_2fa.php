<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/tfa.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not coming from login
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_username']) || !isset($_SESSION['temp_role'])) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$error_message = '';
$backup_mode = isset($_GET['backup']) && $_GET['backup'] == '1';

// Process 2FA verification form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = isset($_POST['auth_code']) ? trim($_POST['auth_code']) : '';
    
    if (empty($code)) {
        $error_message = 'Authentication code is required.';
    } else {
        // Get user's 2FA information
        $user_id = $_SESSION['temp_user_id'];
        $conn = get_database_connection();
        $stmt = $conn->prepare("SELECT tfa_secret FROM users WHERE user_id = ? AND tfa_enabled = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $valid = false;
            
            if ($backup_mode) {
                // Verify backup code
                $valid = verify_backup_code($user_id, $code);
            } else {
                // Verify TOTP code
                $valid = verify_totp_code($user['tfa_secret'], $code);
            }
            
            if ($valid) {
                // Complete login process
                $_SESSION['user_id'] = $_SESSION['temp_user_id'];
                $_SESSION['username'] = $_SESSION['temp_username'];
                $_SESSION['role'] = $_SESSION['temp_role'];
                $_SESSION['last_activity'] = time();
                
                // Clean up temporary session data
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['temp_username']);
                unset($_SESSION['temp_role']);
                
                // Redirect based on role
                if ($_SESSION['role'] == 'admin') {
                    // Log admin login
                    log_admin_action($_SESSION['user_id'], 'Admin login with 2FA', null);
                    header("Location: " . SITE_URL . "/admin/dashboard.php");
                } else {
                    header("Location: " . SITE_URL . "/user/dashboard.php");
                }
                exit();
            } else {
                $error_message = $backup_mode ? 'Invalid backup code.' : 'Invalid authentication code.';
            }
        } else {
            $error_message = 'User not found or 2FA not enabled.';
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Two-Factor Authentication</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p>An authentication code has been sent to your email address. Please enter it below to complete the login process.</p>
                    <p>The code will expire in 10 minutes.</p>
                </div>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="auth_code" class="form-label">Authentication Code</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="text" class="form-control" id="auth_code" name="auth_code" placeholder="Enter 6-digit code" maxlength="6" required autofocus>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Verify</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">
                    <a href="<?php echo SITE_URL; ?>/auth/login.php">Return to login</a>
                    (This will cancel the current login attempt)
                </p>
            </div>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/includes/footer.php'; ?>
