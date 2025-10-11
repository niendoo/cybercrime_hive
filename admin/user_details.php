<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check if user is logged in and is admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Redirect to login page
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user = null;
$reports = [];
$error_message = '';

// Get user details
if ($user_id > 0) {
    $conn = get_database_connection();
    
    // Get user info
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Get user reports
        $stmt = $conn->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $reports_result = $stmt->get_result();
        
        while ($report = $reports_result->fetch_assoc()) {
            $reports[] = $report;
        }
        
        // Log admin action
        log_admin_action($admin_id, "Viewed user details for: {$user['username']}", null);
    } else {
        $error_message = "User not found.";
    }
    
    $stmt->close();
    $conn->close();
} else {
    $error_message = "Invalid user ID.";
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row m-4 px-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/users.php">User Management</a></li>
                <li class="breadcrumb-item active">User Details</li>
            </ol>
        </nav>
    </div>
</div>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Users
        </a>
    </div>
<?php elseif ($user): ?>
    <div class="row m-4 px-4">
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>User Profile</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="<?php echo SITE_URL; ?>/assets/img/default-avatar.svg" alt="User Avatar" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px;">
                        <h5 class="mt-3"><?php echo htmlspecialchars($user['username']); ?></h5>
                        <p>
                            <span class="badge bg-<?php echo ($user['role'] == 'admin') ? 'danger' : 'primary'; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <table class="table table-bordered">
                        <tr>
                            <th width="35%">User ID</th>
                            <td><?php echo $user['user_id']; ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                        </tr>
                        <tr>
                            <th>Registered</th>
                            <td><?php echo format_date($user['registered_at']); ?></td>
                        </tr>
                    </table>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-warning reset-password" data-id="<?php echo $user['user_id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </button>
                        <button class="btn btn-secondary change-role" data-id="<?php echo $user['user_id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-role="<?php echo $user['role']; ?>">
                            <i class="fas fa-user-shield me-2"></i>Change Role
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>User Reports</h5>
                </div>
                <div class="card-body">
                    <?php if (count($reports) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID/Code</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td>
                                                <small class="d-block text-muted">#<?php echo $report['report_id']; ?></small>
                                                <small class="font-weight-bold"><?php echo $report['tracking_code']; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['title']); ?></td>
                                            <td>
                                                <span class="badge rounded-pill bg-<?php 
                                                    if ($report['category'] == 'Phishing') echo 'warning';
                                                    elseif ($report['category'] == 'Hacking') echo 'danger';
                                                    elseif ($report['category'] == 'Fraud') echo 'info';
                                                    else echo 'secondary';
                                                ?>">
                                                    <?php echo $report['category']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill bg-<?php 
                                                    if ($report['status'] == 'Submitted') echo 'secondary';
                                                    elseif ($report['status'] == 'Under Review') echo 'info';
                                                    elseif ($report['status'] == 'In Investigation') echo 'primary';
                                                    elseif ($report['status'] == 'Resolved') echo 'success';
                                                ?>">
                                                    <?php echo $report['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>/admin/view_report.php?id=<?php echo $report['report_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-folder-open fa-4x text-gray-300 mb-3"></i>
                            <p>This user hasn't submitted any reports yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>User Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Reports by Status</h6>
                                    <div class="mb-4">
                                        <?php
                                        // Calculate reports by status
                                        $status_counts = ['Submitted' => 0, 'Under Review' => 0, 'In Investigation' => 0, 'Resolved' => 0];
                                        foreach ($reports as $report) {
                                            $status_counts[$report['status']]++;
                                        }
                                        
                                        // Calculate percentages
                                        $total_reports = count($reports);
                                        foreach ($status_counts as $status => $count) {
                                            $percentage = $total_reports > 0 ? ($count / $total_reports) * 100 : 0;
                                            $color_class = '';
                                            
                                            switch ($status) {
                                                case 'Submitted': $color_class = 'bg-secondary'; break;
                                                case 'Under Review': $color_class = 'bg-info'; break;
                                                case 'In Investigation': $color_class = 'bg-primary'; break;
                                                case 'Resolved': $color_class = 'bg-success'; break;
                                            }
                                        ?>
                                        <div class="mb-1 small"><?php echo $status; ?> (<?php echo $count; ?>)</div>
                                        <div class="progress mb-3" style="height: 10px;">
                                            <div class="progress-bar <?php echo $color_class; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Reports by Category</h6>
                                    <div class="mb-4">
                                        <?php
                                        // Calculate reports by category
                                        $category_counts = ['Phishing' => 0, 'Hacking' => 0, 'Fraud' => 0, 'Other' => 0];
                                        foreach ($reports as $report) {
                                            $category_counts[$report['category']]++;
                                        }
                                        
                                        // Calculate percentages
                                        foreach ($category_counts as $category => $count) {
                                            $percentage = $total_reports > 0 ? ($count / $total_reports) * 100 : 0;
                                            $color_class = '';
                                            
                                            switch ($category) {
                                                case 'Phishing': $color_class = 'bg-warning'; break;
                                                case 'Hacking': $color_class = 'bg-danger'; break;
                                                case 'Fraud': $color_class = 'bg-info'; break;
                                                case 'Other': $color_class = 'bg-secondary'; break;
                                            }
                                        ?>
                                        <div class="mb-1 small"><?php echo $category; ?> (<?php echo $count; ?>)</div>
                                        <div class="progress mb-3" style="height: 10px;">
                                            <div class="progress-bar <?php echo $color_class; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Recent Activity</h6>
                            <?php
                            // Get most recent report and stats
                            $recent_report = count($reports) > 0 ? $reports[0] : null;
                            $conn = get_database_connection();
                            
                            // Get feedback count
                            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM feedback WHERE user_id = ?");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $feedback_count = $stmt->get_result()->fetch_assoc()['count'];
                            $stmt->close();
                            
                            // Get average rating
                            $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM feedback WHERE user_id = ?");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $avg_rating = $stmt->get_result()->fetch_assoc()['avg_rating'];
                            $stmt->close();
                            $conn->close();
                            ?>
                            
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Total Reports</span>
                                    <span class="badge bg-primary rounded-pill"><?php echo count($reports); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Most Recent Report</span>
                                    <span>
                                        <?php if ($recent_report): ?>
                                            <?php echo date('M d, Y', strtotime($recent_report['created_at'])); ?>
                                        <?php else: ?>
                                            None
                                        <?php endif; ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Feedback Provided</span>
                                    <span class="badge bg-info rounded-pill"><?php echo $feedback_count; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Average Rating</span>
                                    <span>
                                        <?php if ($avg_rating): ?>
                                            <?php 
                                            $rating = round($avg_rating, 1);
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating) {
                                                    echo '<i class="fas fa-star text-warning"></i>';
                                                } elseif ($i - 0.5 <= $rating) {
                                                    echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                                } else {
                                                    echo '<i class="far fa-star text-warning"></i>';
                                                }
                                            }
                                            echo " ($rating)";
                                            ?>
                                        <?php else: ?>
                                            No ratings yet
                                        <?php endif; ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Users
        </a>
    </div>
    
    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset User Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="<?php echo SITE_URL; ?>/admin/users.php">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    <div class="modal-body">
                        <p>Are you sure you want to reset the password for user: <strong id="reset_username"></strong>?</p>
                        <p>A new random password will be generated and sent to the user's email address.</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            The new password will be displayed on screen after reset. Make sure to note it down if needed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Role Modal -->
    <div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="changeRoleModalLabel">Change User Role</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="<?php echo SITE_URL; ?>/admin/users.php">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="user_id" id="role_user_id">
                    <div class="modal-body">
                        <p>Change role for user: <strong id="role_username"></strong></p>
                        <div class="mb-3">
                            <label for="role" class="form-label">Select Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Changing a user to Admin will grant them full access to the administrative functions of the system.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Handle modal triggers
    document.addEventListener('DOMContentLoaded', function() {
        // Reset password
        const resetPasswordButtons = document.querySelectorAll('.reset-password');
        resetPasswordButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const userId = this.getAttribute('data-id');
                const username = this.getAttribute('data-username');
                
                document.getElementById('reset_user_id').value = userId;
                document.getElementById('reset_username').textContent = username;
                
                const resetModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
                resetModal.show();
            });
        });
        
        // Change role
        const changeRoleButtons = document.querySelectorAll('.change-role');
        changeRoleButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const userId = this.getAttribute('data-id');
                const username = this.getAttribute('data-username');
                const currentRole = this.getAttribute('data-role');
                
                document.getElementById('role_user_id').value = userId;
                document.getElementById('role_username').textContent = username;
                document.getElementById('role').value = currentRole;
                
                const roleModal = new bootstrap.Modal(document.getElementById('changeRoleModal'));
                roleModal.show();
            });
        });
    });
    </script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
