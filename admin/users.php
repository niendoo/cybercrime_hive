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
$error_message = '';
$success_message = '';
$users = [];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $conn = get_database_connection();
        
        // Reset password
        if ($_POST['action'] == 'reset_password' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        
        // Generate a random password
        $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Get user info for notification
        $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Update password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Password reset successfully for user {$user['username']}. New password: $new_password";
            
            // Log action
            log_admin_action($admin_id, "Reset password for user: {$user['username']}", null);
            
            // Send notification to user
            $message = "Your password for CyberCrime Hive has been reset by an administrator. Your new password is: $new_password\n\n";
            $message .= "Please login with this password and change it immediately for security reasons.";
            
            create_notification(null, $user_id, 'Email', $message);
        } else {
            $error_message = "Failed to reset password.";
        }
        
        $stmt->close();
    }
    // Manage role
    else if ($_POST['action'] == 'change_role' && isset($_POST['user_id']) && isset($_POST['role'])) {
        $user_id = intval($_POST['user_id']);
        $role = sanitize_input($_POST['role']);
        
        // Get user info for notification
        $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Update role
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        $stmt->bind_param("si", $role, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Role updated successfully for user {$user['username']}.";
            
            // Log action
            log_admin_action($admin_id, "Changed role to '$role' for user: {$user['username']}", null);
        } else {
            $error_message = "Failed to update role.";
        }
        
        $stmt->close();
    }
    
    $conn->close();
    }
}

// Get user list with pagination
$conn = get_database_connection();
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 20;
$offset = ($page - 1) * $items_per_page;

// Count total users
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$row = $result->fetch_assoc();
$total_users = $row['total'];
$total_pages = ceil($total_users / $items_per_page);

// Get users for current page using prepared statement
$stmt = $conn->prepare("SELECT * FROM users ORDER BY registered_at DESC LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $items_per_page);
$stmt->execute();
$result = $stmt->get_result();
while ($user = $result->fetch_assoc()) {
    $users[] = $user;
}
$stmt->close();

$conn->close();

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row m-4 px-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active">User Management</li>
            </ol>
        </nav>
        
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">User Management</h1>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow m-4 px-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Registered Users</h6>
        <div class="small text-gray-500">
            Total: <?php echo htmlspecialchars($total_users, ENT_QUOTES, 'UTF-8'); ?> users
        </div>
    </div>
    <div class="card-body ">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['user_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo ($user['role'] == 'admin') ? 'danger' : 'primary'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($user['registered_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/user_details.php?id=<?php echo $user['user_id']; ?>">
                                                <i class="fas fa-eye me-2"></i>View Details
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item reset-password" href="#" data-id="<?php echo $user['user_id']; ?>" data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fas fa-key me-2"></i>Reset Password
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item change-role" href="#" 
                                               data-id="<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                               data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                               data-role="<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fas fa-user-shield me-2"></i>Change Role
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-4">
                <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                    <a class="page-link" href="<?php 
                    $query_params = $_GET;
                    $query_params['page'] = 1;
                    echo SITE_URL . '/admin/users.php?' . http_build_query($query_params);
                    ?>" aria-label="First">
                        <span aria-hidden="true">&laquo;&laquo;</span>
                    </a>
                </li>
                <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                    <a class="page-link" href="<?php 
                    $query_params = $_GET;
                    $query_params['page'] = $page - 1;
                    echo SITE_URL . '/admin/users.php?' . http_build_query($query_params);
                    ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                    $query_params = $_GET;
                    $query_params['page'] = $i;
                ?>
                <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                    <a class="page-link" href="<?php echo SITE_URL . '/admin/users.php?' . http_build_query($query_params); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                    <a class="page-link" href="<?php 
                    $query_params = $_GET;
                    $query_params['page'] = $page + 1;
                    echo SITE_URL . '/admin/users.php?' . http_build_query($query_params);
                    ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
                <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                    <a class="page-link" href="<?php 
                    $query_params = $_GET;
                    $query_params['page'] = $total_pages;
                    echo SITE_URL . '/admin/users.php?' . http_build_query($query_params);
                    ?>" aria-label="Last">
                        <span aria-hidden="true">&raquo;&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset User Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <?php echo csrf_token_field(); ?>
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
            <form method="post" action="">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="user_id" id="role_user_id">
                <?php echo csrf_token_field(); ?>
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

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
