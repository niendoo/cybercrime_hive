<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/tfa.php';

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
$timeout_message = '';

// Check if redirected due to session timeout
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $timeout_message = 'Your session has expired. Please login again.';
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // No need to sanitize password before checking
    
    if (empty($username) || empty($password)) {
        $error_message = 'Username and password are required.';
    } else {
        $conn = get_database_connection();
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT user_id, username, email, password, role, status, tfa_enabled, tfa_secret FROM users WHERE (username = ? OR email = ?)");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check account status
                if ($user['status'] == 'Inactive') {
                    $error_message = 'Your account has not been activated. Please check your email for the activation link.';
                } else if ($user['status'] == 'Suspended') {
                    $error_message = 'Your account has been suspended. Please contact the administrator.';
                } else {
                    // Check if 2FA is enabled for this user
                    if ($user['tfa_enabled'] == 1) {
                        // Store user info for 2FA verification
                        $_SESSION['temp_user_id'] = $user['user_id'];
                        $_SESSION['temp_username'] = $user['username'];
                        $_SESSION['temp_role'] = $user['role'];
                        
                        // Redirect to 2FA verification page
                        header("Location: " . SITE_URL . "/auth/verify_2fa.php");
                        exit();
                    } else {
                        // Regular login for users without 2FA
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['last_activity'] = time();
                        
                        // Log admin login
                        if ($user['role'] == 'admin') {
                            log_admin_action($user['user_id'], 'Admin login', null);
                        }
                        
                        // Redirect to appropriate dashboard
                        if ($user['role'] == 'admin') {
                            header("Location: " . SITE_URL . "/admin/dashboard.php");
                        } else {
                            header("Location: " . SITE_URL . "/user/dashboard.php");
                        }
                        exit();
                    }
                }
            } else {
                $error_message = 'Invalid username or password.';
            }
        } else {
            $error_message = 'Invalid username or password.';
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
                <h1 class="h3 text-gray-900 mb-2">Welcome Back!</h1>
                <p class="text-muted">Enter your credentials to access your account</p>
            </div>
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-gradient-primary-to-secondary">
                    <h4 class="mb-0 text-center"><i class="fas fa-sign-in-alt me-2"></i>Login</h4>
                </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($timeout_message): ?>
                    <div class="alert alert-warning"><?php echo $timeout_message; ?></div>
                <?php endif; ?>
                
                <form method="post" action="" id="loginForm" class="p-3 needs-validation" novalidate>
                    <div class="mb-4">
                        <label for="username" class="form-label small text-gray-600 fw-bold">Username or Email</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light text-primary"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control bg-light" id="username" name="username" placeholder="Enter your username or email" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        <div class="invalid-feedback">Please enter your username or email.</div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between">
                            <label for="password" class="form-label small text-gray-600 fw-bold">Password</label>
                            <a href="<?php echo SITE_URL; ?>/auth/forgot_password.php" class="small text-primary">Forgot Password?</a>
                        </div>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light text-primary"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control bg-light" id="password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePassword" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Please enter your password.</div>
                    </div>
                    <div class="d-grid gap-2 mt-5">
                        <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light py-3">
                <div class="text-center">
                    <p class="mb-0 text-gray-600">Don't have an account? <a href="<?php echo SITE_URL; ?>/auth/register.php" class="fw-bold text-primary">Create Account</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
// Bootstrap inline validation for login form
(function() {
    const form = document.getElementById('loginForm');
    const toggleBtn = document.getElementById('togglePassword');
    const toggleIcon = document.getElementById('togglePasswordIcon');
    const passwordInput = document.getElementById('password');
    function focusFirstInvalid() {
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid && typeof firstInvalid.focus === 'function') {
            firstInvalid.focus();
        }
    }
    if (toggleBtn && passwordInput && toggleIcon) {
        toggleBtn.addEventListener('click', function() {
            const isText = passwordInput.type === 'text';
            passwordInput.type = isText ? 'password' : 'text';
            this.setAttribute('aria-pressed', String(!isText));
            // Toggle icon
            if (isText) {
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
                this.setAttribute('aria-label', 'Show password');
            } else {
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
                this.setAttribute('aria-label', 'Hide password');
            }
        });
    }
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated');
            focusFirstInvalid();
        }
    }, false);

    // Apply validation styling after server-side error
    const hadServerError = <?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error_message)) ? 'true' : 'false'; ?>;
    if (hadServerError) {
        form.classList.add('was-validated');
        focusFirstInvalid();
    }
})();
</script>
</main>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
