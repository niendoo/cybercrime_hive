<?php
// Database Configuration with Environment Detection
require_once __DIR__ . '/environment.php';

// Database constants are now set by EnvironmentManager
// No need to define them here as they're handled automatically

// Create database connection
function get_database_connection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        $error_msg = "Connection failed: " . $conn->connect_error;
        
        // In development, show detailed error
        if (EnvironmentManager::isLocal()) {
            die($error_msg);
        } else {
            // In production, log error and show generic message
            error_log($error_msg);
            die("Database connection error. Please contact administrator.");
        }
    }
    
    return $conn;
}
?>
