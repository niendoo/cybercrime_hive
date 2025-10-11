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

// Process password reset request form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    
    if (empty($email)) {
        $error_message = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } else {
        $conn = get_database_connection();
        
        // Check if password_resets table exists
        $table_exists = $conn->query("SHOW TABLES LIKE 'password_resets'")->num_rows > 0;
        
        if (!$table_exists) {
            // Create the table if it doesn't exist
            $create_table_sql = "CREATE TABLE IF NOT EXISTS password_resets (
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NOT NULL,
                token VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) NOT NULL DEFAULT 0,
                INDEX (token),
                INDEX (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
            
            try {
                $conn->query($create_table_sql);
            } catch (Exception $e) {
                // If we can't create the table, log it but continue
                error_log('Failed to create password_resets table: ' . $e->getMessage());
            }
        }
        
        // Check if email exists in the database
        $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ? AND status = 'Active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];
            $username = $user['username'];
            
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $created_at = date('Y-m-d H:i:s');
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Generate a reset token and store it in the user's record directly
            // This avoids the need for a separate password_resets table
            $token = bin2hex(random_bytes(32));
            $created_at = date('Y-m-d H:i:s');
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store the token in the users table
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE user_id = ?");
            $update_stmt->bind_param("ssi", $token, $expires_at, $user_id);
            
            if ($update_stmt->execute()) {
                // Send password reset email
                $reset_link = SITE_URL . '/auth/reset_password.php?token=' . $token;
                $message = "Hello $username,\n\nYou have requested to reset your password. Please click the link below to reset your password:\n\n$reset_link\n\nThis link will expire in 1 hour.\n\nIf you did not request a password reset, please ignore this email.\n\nRegards,\n" . SITE_NAME . " Team";
                
                // Try to send the notification and capture the result
                $notification_result = create_notification(null, $user_id, 'Email', $message);
                
                if ($notification_result) {
                    $success_message = 'Password reset instructions have been sent to your email address.';
                } else {
                    // Check if logs directory exists and is writable for debugging
                    $log_dir = dirname(__DIR__) . '/logs';
                    if (!file_exists($log_dir)) {
                        mkdir($log_dir, 0755, true);
                    }
                    
                    // Log the error for debugging
                    $log_file = $log_dir . '/password_reset_errors.log';
                    $log_message = date('Y-m-d H:i:s') . ' - Failed to send password reset email to user ID: ' . $user_id . '
';
                    file_put_contents($log_file, $log_message, FILE_APPEND);
                    
                    $error_message = 'Failed to send password reset email. Please try again later.';
                }
            } else {
                $error_message = 'Failed to process your request. Please try again later.';
            }
            
            $update_stmt->close();
        } else {
            // Don't reveal that the email doesn't exist for security reasons
            $success_message = 'If your email address exists in our database, you will receive a password reset link shortly.';
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<main class="flex-grow-1">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8">
            <div class="text-center mb-4">
                <h1 class="h3 text-gray-900 mb-2">Forgot Your Password?</h1>
                <p class="text-muted">We'll help you reset it and get back on track</p>
            </div>
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-gradient-primary-to-secondary">
                    <h4 class="mb-0 text-center"><i class="fas fa-key me-2"></i>Password Recovery</h4>
                </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <div class="p-3">
                    <p class="mb-4 text-gray-700"><i class="fas fa-info-circle text-primary me-2"></i>Enter your email address below and we'll send you a link to reset your password.</p>
                    
                    <form method="post" action="">
                        <div class="mb-4">
                            <label for="email" class="form-label small text-gray-600 fw-bold">Email Address</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light text-primary"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control bg-light" id="email" name="email" placeholder="Enter your registered email" required>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Send Reset Link</button>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-outline-secondary">Back to Login</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-footer bg-light py-3">
                <div class="text-center">
                    <p class="mb-0 text-gray-600">Remember your password? <a href="<?php echo SITE_URL; ?>/auth/login.php" class="fw-bold text-primary">Sign In</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
