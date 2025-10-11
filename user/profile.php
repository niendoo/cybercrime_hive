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

// Get user information
$conn = get_database_connection();
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Update profile
        if ($_POST['action'] == 'update_profile') {
            // Verify CSRF token
            if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
                $error_message = "Invalid security token. Please try again.";
            } else {
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                
                // Validate input
                if (empty($username) || empty($email) || empty($phone)) {
                    $error_message = "All fields are required.";
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error_message = "Invalid email format.";
                } else {
                    // Check if username or email is already in use by another user
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
                $stmt->bind_param("ssi", $username, $email, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error_message = "Username or email is already in use by another user.";
                } else {
                    // Update user information
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ? WHERE user_id = ?");
                    $stmt->bind_param("sssi", $username, $email, $phone, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Profile updated successfully.";
                        $_SESSION['username'] = $username; // Update session information
                        
                        // Refresh user data
                        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                    } else {
                        $error_message = "Failed to update profile.";
                    }
                }
                
                $stmt->close();
            }
        }
    }
}}

$conn->close();

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row my-4 px-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Admin Dashboard</a></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/user/dashboard.php">My Dashboard</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active">My Profile</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row px-4">
    <div class="col-md-4 mb-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>User Profile</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <img src="<?php echo SITE_URL; ?>/assets/img/default-avatar.png" alt="User Avatar" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px;">
                </div>
                <h5><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></h5>
                <p class="text-muted">
                    <span class="badge bg-<?php echo ($user['role'] == 'admin') ? 'danger' : 'primary'; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </p>
                <p class="mb-0"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="mb-0"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="mb-0"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Member since: <?php echo date('F j, Y', strtotime($user['registered_at'])); ?></p>
            </div>
            <div class="card-footer">
                <div class="d-grid gap-2">
                    <a href="<?php echo SITE_URL; ?>/user/change_password.php" class="btn btn-outline-primary">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/user/dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tachometer-alt me-2"></i>My Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="update_profile">
                    <?php echo csrf_token_field(); ?>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- User Activity Summary -->
        <div class="card shadow mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Activity Summary</h5>
            </div>
            <div class="card-body">
                <?php
                // Get user's reports count
                $conn = get_database_connection();
                $stmt = $conn->prepare("SELECT COUNT(*) as total_reports FROM reports WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $reports_count = $result->fetch_assoc()['total_reports'];
                
                // Get user's reports by status
                $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM reports WHERE user_id = ? GROUP BY status");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $reports_by_status = ['Submitted' => 0, 'Under Review' => 0, 'In Investigation' => 0, 'Resolved' => 0];
                while ($row = $result->fetch_assoc()) {
                    $reports_by_status[$row['status']] = $row['count'];
                }
                
                // Get last report date
                $stmt = $conn->prepare("SELECT MAX(created_at) as last_report FROM reports WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $last_report = $result->fetch_assoc()['last_report'];
                
                $stmt->close();
                $conn->close();
                ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Reports Summary</h6>
                                <p class="card-text display-6 text-primary"><?php echo htmlspecialchars($reports_count, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-muted mb-0">Total Reports Submitted</p>
                                <?php if ($last_report): ?>
                                    <small class="text-muted">Last report: <?php echo htmlspecialchars(date('F j, Y', strtotime($last_report)), ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">Reports by Status</h6>
                                <div class="d-flex justify-content-between">
                                    <div class="text-center">
                                        <div class="fw-bold text-secondary"><?php echo htmlspecialchars($reports_by_status['Submitted'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small>Pending</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-bold text-info"><?php echo htmlspecialchars($reports_by_status['Under Review'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small>Review</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($reports_by_status['In Investigation'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small>Investigating</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-bold text-success"><?php echo htmlspecialchars($reports_by_status['Resolved'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small>Resolved</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <?php if ($reports_count > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/user/dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-2"></i>View All My Reports
                        </a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/reports/submit.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-file-alt me-2"></i>Submit Your First Report
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
