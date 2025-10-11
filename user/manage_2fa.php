<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error.log');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/tfa.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$step = isset($_GET['step']) ? $_GET['step'] : '1';
$conn = get_database_connection();

// Get user's 2FA status
$stmt = $conn->prepare("SELECT tfa_enabled, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$tfa_enabled = $user['tfa_enabled'];
$user_email = $user['email'];
$stmt->close();

$error_message = '';
$success_message = '';
$qr_url = '';
$secret_key = '';
$backup_codes = [];

// Process 2FA actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Enable 2FA
    if (isset($_POST['setup_2fa'])) {
        $secret = generate_tfa_secret();
        $_SESSION['temp_tfa_secret'] = $secret;
        header("Location: " . SITE_URL . "/user/manage_2fa.php?step=2");
        exit();
    }
    
    // Verify code and activate 2FA
    else if (isset($_POST['verify_code'])) {
        $code = isset($_POST['auth_code']) ? trim($_POST['auth_code']) : '';
        $temp_secret = isset($_SESSION['temp_tfa_secret']) ? $_SESSION['temp_tfa_secret'] : '';
        
        if (empty($code) || empty($temp_secret)) {
            $error_message = 'Authentication code is required or setup session expired.';
        } else {
            // Verify the entered code
            if (verify_totp_code($temp_secret, $code)) {
                // Enable 2FA for the user
                $result = enable_tfa($user_id, $temp_secret);
                
                if ($result['success']) {
                    $success_message = '2FA has been successfully enabled for your account.';
                    $backup_codes = $result['backup_codes'];
                    unset($_SESSION['temp_tfa_secret']);
                    $step = '3'; // Move to backup codes display
                } else {
                    $error_message = $result['message'];
                }
            } else {
                $error_message = 'Invalid authentication code. Please try again.';
            }
        }
    }
    
    // Disable 2FA
    else if (isset($_POST['disable_2fa'])) {
        $code = isset($_POST['auth_code']) ? trim($_POST['auth_code']) : '';
        
        if (empty($code)) {
            $error_message = 'Authentication code is required.';
        } else {
            // Get user's secret
            $stmt = $conn->prepare("SELECT tfa_secret FROM users WHERE user_id = ? AND tfa_enabled = 1");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $secret = $user['tfa_secret'];
                
                // Verify the entered code
                if (verify_totp_code($secret, $code)) {
                    if (disable_tfa($user_id)) {
                        $success_message = '2FA has been disabled for your account.';
                        $tfa_enabled = 0;
                    } else {
                        $error_message = 'Failed to disable 2FA. Please try again.';
                    }
                } else {
                    $error_message = 'Invalid authentication code.';
                }
            } else {
                $error_message = '2FA is not enabled for your account.';
            }
            $stmt->close();
        }
    }
}

// Step 2: Generate QR code for setup
if ($step == '2' && !$tfa_enabled) {
    $secret_key = isset($_SESSION['temp_tfa_secret']) ? $_SESSION['temp_tfa_secret'] : generate_tfa_secret();
    if (!isset($_SESSION['temp_tfa_secret'])) {
        $_SESSION['temp_tfa_secret'] = $secret_key;
    }
    $qr_url = get_qr_code_url('CyberCrime Hive', $user_email, $secret_key);
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Two-Factor Authentication Management</h4>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step == '1'): ?>
                        <!-- Step 1: Current Status -->
                        <div class="mb-4">
                            <h5>Current Status: 
                                <?php if ($tfa_enabled): ?>
                                    <span class="badge bg-success">Enabled</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Disabled</span>
                                <?php endif; ?>
                            </h5>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Two-factor authentication adds an extra layer of security to your account. When enabled, you'll need to provide both your password and a code from your mobile device when logging in.
                            </div>
                        </div>
                        
                        <?php if (!$tfa_enabled): ?>
                            <!-- Enable 2FA -->
                            <form method="post" action="">
                                <button type="submit" name="setup_2fa" class="btn btn-primary">
                                    <i class="fas fa-shield-alt me-2"></i>Set Up Two-Factor Authentication
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Disable 2FA -->
                            <form method="post" action="" class="mb-3">
                                <div class="mb-3">
                                    <label for="auth_code" class="form-label">Enter your authentication code to disable 2FA:</label>
                                    <input type="text" id="auth_code" name="auth_code" class="form-control" required autofocus>
                                    <div class="form-text">Enter the 6-digit code from your authenticator app.</div>
                                </div>
                                <button type="submit" name="disable_2fa" class="btn btn-danger" onclick="return confirm('Are you sure you want to disable two-factor authentication? This will make your account less secure.')">
                                    <i class="fas fa-times-circle me-2"></i>Disable Two-Factor Authentication
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($step == '2'): ?>
                        <!-- Step 2: QR Code Setup -->
                        <h5 class="mb-3">Set Up Authenticator App</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="alert alert-info">
                                    <ol class="mb-0">
                                        <li>Install an authenticator app on your mobile device (Google Authenticator, Microsoft Authenticator, Authy, etc.)</li>
                                        <li>Open the app and scan the QR code</li>
                                        <li>Enter the 6-digit code provided by the app below</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="col-md-6 text-center mb-3">
                                <div class="qr-code-container d-inline-block p-2 bg-white border">
                                    <img src="<?php echo $qr_url; ?>" alt="QR Code" class="img-fluid">
                                </div>
                                <p class="mt-2">Can't scan? Use key: <strong><?php echo chunk_split($secret_key, 4, ' '); ?></strong></p>
                            </div>
                        </div>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="auth_code" class="form-label">Verification Code:</label>
                                <input type="text" id="auth_code" name="auth_code" class="form-control" required autofocus>
                                <div class="form-text">Enter the 6-digit code from your authenticator app.</div>
                            </div>
                            <button type="submit" name="verify_code" class="btn btn-primary">
                                <i class="fas fa-check-circle me-2"></i>Verify and Enable 2FA
                            </button>
                            <a href="<?php echo SITE_URL; ?>/user/manage_2fa.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                        </form>
                    <?php elseif ($step == '3'): ?>
                        <!-- Step 3: Show backup codes -->
                        <h5 class="mb-3">Backup Codes</h5>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Save these backup codes in a secure place. Each code can only be used once to access your account if you lose your device.
                        </div>
                        
                        <div class="backup-codes-container bg-light p-3 mb-3 border">
                            <div class="row">
                                <?php foreach ($backup_codes as $code): ?>
                                <div class="col-6 col-md-4 mb-2">
                                    <code><?php echo $code; ?></code>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <a href="<?php echo SITE_URL; ?>/user/manage_2fa.php" class="btn btn-primary">
                            <i class="fas fa-check-circle me-2"></i>I've Saved My Backup Codes
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include dirname(__DIR__) . '/includes/footer.php';
?>
