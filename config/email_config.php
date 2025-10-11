<?php
/**
 * Email Configuration Override
 * 
 * This file allows you to override email settings for development/testing
 * without modifying the main config.php file
 */

// Override email settings for development
// Set to true to enable actual email sending in development
// WARNING: Only set to true for testing with real email addresses
// Set to false to log emails instead of sending (safer for development)
define('FORCE_EMAIL_SENDING', true); // Set to true to actually send emails

// Alternative SMTP settings for testing
// Uncomment and configure if you want to use different SMTP settings for testing
/*
define('DEV_SMTP_HOST', 'smtp.gmail.com');
define('DEV_SMTP_PORT', 587);
define('DEV_SMTP_USERNAME', 'your-test-email@gmail.com');
define('DEV_SMTP_PASSWORD', 'your-test-password');
define('DEV_SMTP_SECURE', 'tls');
*/

// Test email addresses for development
// Set to empty string to send emails to actual user addresses
// Only set this to a specific email during testing to avoid spamming real users
define('DEV_EMAIL_OVERRIDE', ''); // Empty = send to actual user email addresses

// Email debugging mode - adds extra logging
define('EMAIL_DEBUG_MODE', true);

// Email queue for batch processing (future feature)
define('ENABLE_EMAIL_QUEUE', false);

// Rate limiting for emails (per minute)
define('EMAIL_RATE_LIMIT', 10);

// Email validation settings
// Set to true to validate email addresses before sending
define('VALIDATE_EMAIL_ADDRESSES', true);

?>
