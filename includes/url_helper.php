<?php
/**
 * URL Helper Functions
 * Provides reliable URL generation for different environments
 */

class URLHelper {
    /**
     * Generate the correct site URL based on environment
     * @return string
     */
    public static function getSiteURL() {
        // Check if SITE_URL is already correctly defined
        if (defined('SITE_URL') && !empty(SITE_URL) && SITE_URL !== 'http://localhost') {
            return SITE_URL;
        }
        
        // Detect environment
        $env = EnvironmentManager::detectEnvironment();
        
        if ($env === 'production') {
            // For production, use the HTTP_HOST
            $host = $_SERVER['HTTP_HOST'] ?? 'cybercrimehive.abdulrauf.xyz';
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'https'; // Force HTTPS in production
            return $protocol . '://' . $host;
        } else {
            // For local development
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $protocol = 'http';
            
            // Check if we're in a subdirectory (like XAMPP)
            $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
            if (strpos($script_name, '/cybercrime_hive/') !== false) {
                return $protocol . '://' . $host . '/cybercrime_hive';
            }
            
            return $protocol . '://' . $host;
        }
    }
    
    /**
     * Generate a feedback URL
     * @param string $token
     * @return string
     */
    public static function generateFeedbackURL($token) {
        // Server expects a feedback directory path
        return self::getSiteURL() . '/feedback/?token=' . urlencode($token);
    }
    
    /**
     * Generate any application URL
     * @param string $path
     * @return string
     */
    public static function generateURL($path) {
        $path = ltrim($path, '/');
        return self::getSiteURL() . '/' . $path;
    }
}
?>