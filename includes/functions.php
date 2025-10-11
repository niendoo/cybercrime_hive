<?php
// Core utility functions for the application

/**
 * Sanitize user input
 * @param string $data User input to sanitize
 * @return string Sanitized data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate a unique tracking code for reports
 * @return string Unique tracking code
 */
function generate_tracking_code() {
    $prefix = 'CR';
    $timestamp = date('ymd');
    $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    return $prefix . $timestamp . $random;
}

/**
 * Generate SEO-friendly URL slug from title
 * @param string $title The title to convert to slug
 * @param int $exclude_id Optional article ID to exclude from uniqueness check
 * @return string SEO-friendly unique slug
 */
function generate_slug($title, $exclude_id = null) {
    // Remove HTML tags and decode entities
    $slug = strip_tags($title);
    $slug = html_entity_decode($slug, ENT_QUOTES, 'UTF-8');
    
    // Convert to lowercase and trim
    $slug = strtolower(trim($slug));
    
    // Replace non-alphanumeric characters with hyphens
    $slug = preg_replace('/[^a-z0-9\s\-_]/', '', $slug);
    
    // Replace spaces, underscores, and multiple hyphens with single hyphen
    $slug = preg_replace('/[\s\-_]+/', '-', $slug);
    
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    
    // Limit length to 100 characters for SEO
    $slug = substr($slug, 0, 100);
    $slug = rtrim($slug, '-');
    
    // Ensure it's not empty
    if (empty($slug)) {
        $slug = 'article';
    }
    
    // Ensure uniqueness in database
    $conn = get_database_connection();
    $base_slug = $slug;
    $counter = 1;
    
    while (true) {
        $check_slug = $counter > 1 ? $base_slug . '-' . $counter : $base_slug;
        
        $query = "SELECT kb_id FROM knowledge_base WHERE slug = ?";
        $params = [$check_slug];
        
        if ($exclude_id) {
            $query .= " AND kb_id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(str_repeat('s', count($params) - 1) . 'i', ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return $check_slug;
        }
        
        $counter++;
    }
}

/**
 * Log admin actions
 * @param int $admin_id Admin user ID
 * @param string $action Action performed
 * @param int $report_id Related report ID (optional)
 * @return bool Success status
 */
function log_admin_action($admin_id, $action, $report_id = null, $user_notes = '') {
    require_once dirname(__DIR__) . '/config/database.php';
    $conn = get_database_connection();
    
    $timestamp = date('Y-m-d H:i:s');
    
    // Check if user_notes column exists
    $user_notes_exists = false;
    $columns_result = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'user_notes'");
    if ($columns_result && $columns_result->num_rows > 0) {
        $user_notes_exists = true;
    }
    // Use prepared statement to prevent SQL injection
    if ($report_id && $user_notes_exists) {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, report_id, timestamp, user_notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiss", $admin_id, $action, $report_id, $timestamp, $user_notes);
    } elseif ($report_id) {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, report_id, timestamp) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isis", $admin_id, $action, $report_id, $timestamp);
    } elseif ($user_notes_exists) {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, timestamp, user_notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $admin_id, $action, $timestamp, $user_notes);
    } else {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, timestamp) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $admin_id, $action, $timestamp);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Log general user actions
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param int $report_id Related report ID (optional)
 * @return bool Success status
 */
function log_action($user_id, $action, $report_id = null) {
    require_once dirname(__DIR__) . '/config/database.php';
    $conn = get_database_connection();
    
    $timestamp = date('Y-m-d H:i:s');
    
    // Use prepared statements to prevent SQL injection
    if ($report_id !== null) {
        $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, report_id, log_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isis", $user_id, $action, $report_id, $timestamp);
    } else {
        $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, log_time) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $action, $timestamp);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Create and send notification
 * @param int $report_id Report ID
 * @param int $user_id User ID
 * @param string $type Notification type (SMS or Email)
 * @param string $message Message content
 * @return bool Success status
 */
function create_notification($report_id, $user_id, $type, $message) {
    require_once dirname(__DIR__) . '/config/database.php';
    $conn = get_database_connection();
    
    $timestamp = date('Y-m-d H:i:s');
    $status = 'Sent'; // Default, will be updated by actual sending functions
    
    // Handle system notifications where report_id is null (e.g. welcome emails)
    if ($report_id === null) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, notification_type, message_content, sent_timestamp, status) 
             VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $type, $message, $timestamp, $status);
    } else {
        // Use prepared statement to prevent SQL injection for report-related notifications
        $stmt = $conn->prepare("INSERT INTO notifications (report_id, user_id, notification_type, message_content, sent_timestamp, status) 
             VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $report_id, $user_id, $type, $message, $timestamp, $status);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    // Send the actual notification
    $notification_sent = false;
    if ($type == 'Email') {
        $notification_sent = send_email_notification($user_id, $message);
    } else if ($type == 'SMS') {
        $notification_sent = send_sms_notification($user_id, $message);
    }
    
    // Update status if the notification failed
    if (!$notification_sent) {
        $notification_id = $conn->insert_id;
        $conn->query("UPDATE notifications SET status = 'Failed' WHERE notification_id = $notification_id");
    }
    
    $conn->close();
    return $result && $notification_sent;
}

/**
 * Send email notification
 * @param int $user_id User ID
 * @param string $message Message content
 * @return bool Success status
 */
function send_email_notification($user_id, $message) {
    require_once dirname(__DIR__) . '/config/database.php';
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/EmailService.php';
    
    if (!ENABLE_EMAIL) {
        return false;
    }
    
    $conn = get_database_connection();
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $to = $user['email'];
        $subject = SITE_NAME . ' - Notification';
        
        // Format the message as HTML
        $htmlMessage = nl2br($message);
        
        // Use the EmailService class to send the email
        $emailService = new EmailService();
        $result = $emailService->send($to, $subject, $htmlMessage, $message);
        
        $stmt->close();
        $conn->close();
        return $result['success'];
    }
    
    $conn->close();
    return false;
}

/**
 * Send SMS notification
 * @param int $user_id User ID
 * @param string $message Message content
 * @return bool Success status
 */
function send_sms_notification($user_id, $message) {
    require_once dirname(__DIR__) . '/config/database.php';
    require_once dirname(__DIR__) . '/config/config.php';
    
    if (!ENABLE_SMS) {
        return false;
    }
    
    $conn = get_database_connection();
    $query = "SELECT phone FROM users WHERE user_id = $user_id";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $phone = $user['phone'];
        
        // In a production environment, replace with actual SMS API code
        // This is a placeholder for development
        $sms_sent = true; // Simulate successful sending
        $conn->close();
        return $sms_sent;
    }
    
    $conn->close();
    return false;
}

/**
 * Generate and store 2FA code for admin
 * @param int $user_id Admin user ID
 * @return string Generated 2FA code or false on failure
 */
function generate_2fa_code($user_id) {
    require_once dirname(__DIR__) . '/config/database.php';
    $conn = get_database_connection();
    
    // Generate a 6-digit code
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $created_at = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Delete any existing codes for this user using prepared statement
    $stmt = $conn->prepare("DELETE FROM two_factor_auth WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Store the new code using prepared statement
    $stmt = $conn->prepare("INSERT INTO two_factor_auth (user_id, auth_code, created_at, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $code, $created_at, $expires_at);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result ? $code : false;
}

/**
 * Verify 2FA code
 * @param int $user_id User ID
 * @param string $code Code to verify
 * @return bool Validity status
 */
function verify_2fa_code($user_id, $code) {
    require_once dirname(__DIR__) . '/config/database.php';
    $conn = get_database_connection();
    
    $current_time = date('Y-m-d H:i:s');
    
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM two_factor_auth 
                          WHERE user_id = ? AND auth_code = ? AND expires_at > ?");
    $stmt->bind_param("iss", $user_id, $code, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $valid = $result->num_rows > 0;
    $stmt->close();
    
    // Delete the code if it's valid (one-time use)
    if ($valid) {
        $stmt = $conn->prepare("DELETE FROM two_factor_auth WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
    return $valid;
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool Validity status
 */
function verify_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token input field
 * @return string HTML input field
 */
function csrf_token_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Format date for display
 * @param string $date_string Date string in MySQL format
 * @return string Formatted date
 */
function format_date($date_string) {
    $date = new DateTime($date_string);
    return $date->format('F j, Y, g:i a');
}

/**
 * Check if user is logged in
 * @return bool Login status
 */
function is_logged_in() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

/**
 * Check if logged in user is an admin
 * @return bool Admin status
 */
function is_admin() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

/**
 * Redirect with message
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, info)
 */
function redirect_with_message($url, $message, $type = 'info') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Display flash message if exists and clear it
 * @return string HTML for flash message or empty string
 */
function display_flash_message() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        $alert_class = 'alert-info';
        if ($type == 'success') $alert_class = 'alert-success';
        if ($type == 'error') $alert_class = 'alert-danger';
        
        return "<div class='alert $alert_class alert-dismissible fade show' role='alert'>
                  $message
                  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    }
    
    return '';
}
/**
 * Format file size from bytes to human-readable string
 * @param int $bytes File size in bytes
 * @return string Human-readable file size (e.g., 2.1 MB)
 */
function format_file_size($bytes) {
    if ($bytes < 1024) {
        return $bytes . ' B';
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, 2) . ' KB';
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, 2) . ' MB';
    } else {
        return round($bytes / 1073741824, 2) . ' GB';
    }
}

?>
