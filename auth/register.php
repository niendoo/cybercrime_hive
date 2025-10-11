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

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($username) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif ($password != $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error_message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } else {
        // Check if username or email already exists
        $conn = get_database_connection();
        
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = 'Username or email already exists.';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $registered_at = date('Y-m-d H:i:s');
            $role = 'user'; // Default role for new registrations
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, phone, registered_at, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $email, $hashed_password, $phone, $registered_at, $role);
            
            if ($stmt->execute()) {
                $success_message = 'Registration successful! Please check your email for activation link.';
                
                // Generate activation token and set account as inactive
                $user_id = $conn->insert_id;
                $activation_token = bin2hex(random_bytes(32));
                $activation_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Store activation token in database
                $stmt = $conn->prepare("UPDATE users SET activation_token = ?, activation_expires = ?, status = 'Inactive' WHERE user_id = ?");
                $stmt->bind_param("ssi", $activation_token, $activation_expires, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Send activation email
                $activation_link = SITE_URL . '/auth/activate.php?token=' . $activation_token;
                $message = "Welcome to CyberCrime Hive! Please activate your account by clicking the link below:

$activation_link

This link will expire in 24 hours.";
                create_notification(null, $user_id, 'Email', $message);
            } else {
                $error_message = 'Registration failed. Please try again.';
            }
        }
        
        // Only close the main statement if we haven't already closed it
        if (isset($stmt) && $stmt instanceof mysqli_stmt && !empty($stmt->id)) {
            $stmt->close();
        }
        $conn->close();
    }
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<main class="flex-grow-1">
<div class="container py-5 my-4 px-4">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-8">
            <div class="text-center mb-4">
                <h1 class="h3 text-gray-900 mb-2">Create Your Account</h1>
                <p class="text-muted">Fill out the form below to get started</p>
            </div>
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-gradient-primary-to-secondary">
                    <h4 class="mb-0 text-center"><i class="fas fa-user-plus me-2"></i>Register</h4>
                </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <form method="post" action="" id="registrationForm" class="p-3 needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label small text-gray-600 fw-bold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control bg-light" id="username" name="username" placeholder="Choose a username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        <div class="invalid-feedback">Please enter a username.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label small text-gray-600 fw-bold">Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control bg-light" id="email" name="email" placeholder="Enter your email address" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label small text-gray-600 fw-bold">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary"><i class="fas fa-phone"></i></span>
                            <input type="text" class="form-control bg-light" id="phone" name="phone" placeholder="Enter your phone number" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        <div class="invalid-feedback">Please enter your phone number.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label small text-gray-600 fw-bold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control bg-light" id="password" name="password" placeholder="Create a password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <button type="button" class="btn btn-outline-secondary" id="toggleRegPassword" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" id="toggleRegPasswordIcon"></i>
                            </button>
                        </div>
                        <div class="form-text small"><i class="fas fa-info-circle me-1"></i>Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.</div>
                        <div class="invalid-feedback">Please enter a password (minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters).</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label small text-gray-600 fw-bold">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-primary"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control bg-light" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                            <button type="button" class="btn btn-outline-secondary" id="toggleRegConfirm" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" id="toggleRegConfirmIcon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Please confirm your password.</div>
                        <div id="passwordMatchFeedback" class="small mt-1"></div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light py-3">
                <div class="text-center">
                    <p class="mb-0 text-gray-600">Already have an account? <a href="<?php echo SITE_URL; ?>/auth/login.php" class="fw-bold text-primary">Sign In</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced client-side validation with Bootstrap styles
(function() {
    const form = document.getElementById('registrationForm');
    const togglePwdBtn = document.getElementById('toggleRegPassword');
    const togglePwdIcon = document.getElementById('toggleRegPasswordIcon');
    const pwdInput = document.getElementById('password');
    const toggleCfmBtn = document.getElementById('toggleRegConfirm');
    const toggleCfmIcon = document.getElementById('toggleRegConfirmIcon');
    const cfmInput = document.getElementById('confirm_password');
    const matchFeedback = document.getElementById('passwordMatchFeedback');
    function focusFirstInvalid() {
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid && typeof firstInvalid.focus === 'function') {
            firstInvalid.focus();
        }
    }
    function wireToggle(btn, icon, input) {
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
    wireToggle(togglePwdBtn, togglePwdIcon, pwdInput);
    wireToggle(toggleCfmBtn, toggleCfmIcon, cfmInput);
    function updatePasswordMatchFeedback() {
        const p = pwdInput.value;
        const c = cfmInput.value;
        if (!c && !p) {
            cfmInput.setCustomValidity('');
            if (matchFeedback) { matchFeedback.textContent = ''; matchFeedback.classList.remove('text-success','text-danger'); }
            return;
        }
        if (c && p === c) {
            cfmInput.setCustomValidity('');
            if (matchFeedback) {
                matchFeedback.textContent = 'Passwords match';
                matchFeedback.classList.remove('text-danger');
                matchFeedback.classList.add('text-success');
            }
        } else {
            // Only show mismatch message if user has started typing confirmation
            if (c) {
                cfmInput.setCustomValidity('Passwords do not match');
                if (matchFeedback) {
                    matchFeedback.textContent = 'Passwords do not match';
                    matchFeedback.classList.remove('text-success');
                    matchFeedback.classList.add('text-danger');
                }
            } else {
                cfmInput.setCustomValidity('');
                if (matchFeedback) { matchFeedback.textContent = ''; matchFeedback.classList.remove('text-success','text-danger'); }
            }
        }
    }
    pwdInput.addEventListener('input', updatePasswordMatchFeedback);
    cfmInput.addEventListener('input', updatePasswordMatchFeedback);
    form.addEventListener('submit', function(event) {
        // Custom cross-field validation: passwords match
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        if (password.value !== confirmPassword.value) {
            // Mark confirm field invalid via setCustomValidity
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated');
            focusFirstInvalid();
        }
    }, false);

    // If returned from server with errors after POST, show validation state and focus first invalid
    const hadServerError = <?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error_message)) ? 'true' : 'false'; ?>;
    if (hadServerError) {
        form.classList.add('was-validated');
        // Also ensure password match message is set appropriately
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
        updatePasswordMatchFeedback();
        focusFirstInvalid();
    }
})();
</script>

</main>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
