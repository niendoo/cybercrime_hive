<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

// Check if session is already active before starting one
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    if ($inactive_time >= SESSION_TIMEOUT) {
        // Destroy session and redirect to login
        session_unset();
        session_destroy();
        header("Location: " . SITE_URL . "/auth/login.php?timeout=1");
        exit();
    }
}

// Update last activity time
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/main.css" rel="stylesheet">
</head>

<body>
    <?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
    <?php require_once(__DIR__ . '/../config/config.php'); ?>
    
    <!-- Session Timeout Handler -->
    <?php 
    // Check if we should initialize the session timeout tracking
    if (isset($_SESSION['user_id']) && !isset($_SESSION['session_timeout_initialized'])) {
        $_SESSION['last_activity'] = time();
        $_SESSION['session_timeout_initialized'] = true;
    }
    
    // If it's been initialized already, check for timeout
    if (isset($_SESSION['session_timeout_initialized'])) {
        require_once(__DIR__ . '/session_manager.php');
        // Use the function directly since session_manager.php defines functions not a class
        check_session_timeout();
    }
    ?>
    
    <nav class="navbar sticky-top navbar-expand-lg navbar-light" style="background-color: #e3f2fd;">
        <div class="container-fluid">
            <a href="<?php echo SITE_URL; ?>/index.php" class="navbar-brand">
                <i class="fas fa-shield-alt text-white me-2"></i>
                <?php echo SITE_NAME; ?>
            </a>
            
            <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <div class="navbar-nav">
                    <a href="<?php echo SITE_URL; ?>/index.php" class="nav-item nav-link">
                        <i class="fas fa-home me-2"></i>Home
                    </a>
                    <a href="<?php echo SITE_URL; ?>/reports/track.php" class="nav-item nav-link">
                        <i class="fas fa-search me-2"></i>Track Report
                    </a>
                    <a href="<?php echo SITE_URL; ?>/knowledge/index.php" class="nav-item nav-link">
                        <i class="fas fa-book me-2"></i>Knowledge Base
                    </a>
                </div>

                <div class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- User is logged in -->
                        
                        <!-- Dashboard link (Admin or User) -->
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="nav-item nav-link">
                                <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/user/dashboard.php" class="nav-item nav-link">
                                <i class="fas fa-tachometer-alt me-2"></i>My Dashboard
                            </a>
                        <?php endif; ?>
                        
                        <!-- Submit Report Link -->
                        <a href="<?php echo SITE_URL; ?>/reports/submit.php" class="nav-item nav-link">
                            <i class="fas fa-file-alt me-2"></i>Submit Report
                        </a>
                        
                        <!-- User Profile Dropdown - exact structure from working example -->
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="<?php echo SITE_URL; ?>/user/profile.php" class="dropdown-item">
                                    <i class="fas fa-user me-2 text-primary"></i>Profile Settings
                                </a>
                                <a href="<?php echo SITE_URL; ?>/user/manage_2fa.php" class="dropdown-item">
                                    <i class="fas fa-shield-alt me-2 text-warning"></i>2FA Settings
                                </a>
                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                <a href="<?php echo SITE_URL; ?>/admin/users.php" class="dropdown-item">
                                    <i class="fas fa-users-cog me-2 text-info"></i>User Management
                                </a>
                                <a href="<?php echo SITE_URL; ?>/admin/knowledge_cms.php" class="dropdown-item">
                                    <i class="fas fa-book me-2 text-primary"></i>Knowledge Base
                                </a>
                                <?php endif; ?>
                                <hr class="dropdown-divider">
                                <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt me-2 text-danger"></i>Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- User is not logged in -->
                        <a href="<?php echo SITE_URL; ?>/auth/login.php" class="nav-item nav-link">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                        <a href="<?php echo SITE_URL; ?>/auth/register.php" class="nav-item nav-link">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </a>
                        <a href="<?php echo SITE_URL; ?>/auth/forgot_password.php" class="nav-item nav-link">
                            <i class="fas fa-key me-2"></i>Forgot Password
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php if(function_exists('display_flash_message') && display_flash_message() != ''): ?>
    <div class="container py-3">
        <?php echo display_flash_message(); ?>
    </div>
    <?php endif; ?>
