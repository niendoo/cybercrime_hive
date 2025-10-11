<?php
// Application Configuration with Environment Detection
require_once __DIR__ . '/environment.php';

// All configuration constants are now set by EnvironmentManager
// This includes: SITE_NAME, SITE_URL, ADMIN_EMAIL, DB_*, SMTP_*, etc.

// Session Settings
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// File Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Notification Settings
define('ENABLE_EMAIL', true);
define('ENABLE_SMS', false); // Set to true when SMS API is configured

// Security Settings
define('PASSWORD_MIN_LENGTH', 8);

// Additional constants that don't change between environments
define('APP_VERSION', '1.0.0');
define('APP_NAME', 'CyberCrime Hive');

// Environment-specific settings are handled by EnvironmentManager
// No need to manually set error reporting, SMTP, or database settings here

?>
