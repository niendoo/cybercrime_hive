<?php
/**
 * Session Manager
 * Handles session initialization and management functions
 * Include this file at the top of any page that needs session access
 */

// Only start a session if one doesn't already exist
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Updates the last activity timestamp in the session
 */
function update_session_activity() {
    $_SESSION['last_activity'] = time();
}

/**
 * Check if session has timed out and handle it
 * @return bool True if session is active, false if timed out
 */
function check_session_timeout() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        if ($inactive_time >= SESSION_TIMEOUT) {
            // Session has timed out, destroy it
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    // Update last activity time
    update_session_activity();
    return true;
}

// Run timeout check whenever this file is included
check_session_timeout();
?>
