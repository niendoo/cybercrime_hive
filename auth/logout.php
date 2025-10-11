<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log admin logout
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    log_admin_action($_SESSION['user_id'], 'Admin logout', null);
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page with logout message
header("Location: " . SITE_URL . "/auth/login.php?logout=1");
exit();
?>
