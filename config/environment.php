<?php
/**
 * Environment Detection and Configuration Manager
 * Automatically detects local vs production environment and loads appropriate settings
 */

// Load .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

class EnvironmentManager {
    private static $environment = null;
    private static $config = [];
    
    /**
     * Detect the current environment
     * @return string 'local' or 'production'
     */
    public static function detectEnvironment() {
        if (self::$environment !== null) {
            return self::$environment;
        }
        
        // Method 1: Check for environment variable
        if (isset($_ENV['APP_ENV'])) {
            self::$environment = $_ENV['APP_ENV'];
            return self::$environment;
        }
        
        // Method 2: Check document root for XAMPP first (most reliable for local)
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (strpos(strtolower($documentRoot), 'xampp') !== false || 
            strpos(strtolower($documentRoot), 'wamp') !== false ||
            strpos(strtolower($documentRoot), 'mamp') !== false) {
            self::$environment = 'local';
            return self::$environment;
        }
        
        // Method 3: Check if we're in CLI mode and in XAMPP directory structure
        if (php_sapi_name() === 'cli') {
            $currentPath = __DIR__;
            if (strpos(strtolower($currentPath), 'xampp') !== false ||
                strpos(strtolower($currentPath), 'wamp') !== false ||
                strpos(strtolower($currentPath), 'mamp') !== false) {
                self::$environment = 'local';
                return self::$environment;
            }
        }
        
        // Method 4: Check server name
        $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
        
        // Check server name for local indicators (if server name is available)
        if (!empty($serverName)) {
            $localIndicators = [
                'localhost',
                '127.0.0.1',
                '::1',
                '.local',
                '.test',
                '.dev'
            ];
            
            foreach ($localIndicators as $indicator) {
                if (strpos($serverName, $indicator) !== false) {
                    self::$environment = 'local';
                    return self::$environment;
                }
            }
        }
        
        // Method 5: Check for development file markers
        $devMarkers = [
            __DIR__ . '/../.env.local',
            __DIR__ . '/../development.flag',
            __DIR__ . '/../composer.json' // If composer.json exists in root, likely development
        ];
        
        foreach ($devMarkers as $marker) {
            if (file_exists($marker)) {
                self::$environment = 'local';
                return self::$environment;
            }
        }
        
        // Default to production if none of the above match
        self::$environment = 'production';
        return self::$environment;
    }
    
    /**
     * Get configuration for current environment
     * @return array
     */
    public static function getConfig() {
        if (!empty(self::$config)) {
            return self::$config;
        }
        
        $env = self::detectEnvironment();
        
        if ($env === 'local') {
            self::$config = self::getLocalConfig();
        } else {
            self::$config = self::getProductionConfig();
        }
        
        return self::$config;
    }
    
    /**
     * Get local development configuration
     * @return array
     */
    private static function getLocalConfig() {
        // Determine the correct SITE_URL based on the server environment
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check if we're running under XAMPP/Apache (not PHP dev server)
        $isXampp = strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'xampp') !== false ||
                   strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'htdocs') !== false;
        
        // For XAMPP, we need to include the subdirectory path
        if ($isXampp && strpos($scriptName, '/cybercrime_hive/') !== false) {
            $siteUrl = 'http://' . $host . '/cybercrime_hive';
        } else {
            // For PHP dev server or other setups
            $siteUrl = 'http://' . $host;
        }
        
        return [
            // Site Settings
            'SITE_NAME' => 'CyberCrime Hive',
            'SITE_URL' => $siteUrl,
            'ADMIN_EMAIL' => 'niendoo2@gmail.com',
            
            // Database Settings
            'DB_HOST' => 'localhost',
            'DB_USER' => 'root',
            'DB_PASS' => '',
            'DB_NAME' => 'cybercrime_hive',
            
            // Path Settings
            'BASE_PATH' => dirname(__DIR__),
            'UPLOAD_PATH_ATTACHMENTS' => dirname(__DIR__) . '/uploads/attachments/',
            'UPLOAD_PATH_PROFILE' => dirname(__DIR__) . '/uploads/profile_pics/',
            
            // SMTP Settings (Local - using Gmail for testing)
            'SMTP_HOST' => 'smtp.gmail.com',
            'SMTP_PORT' => 587,
            'SMTP_USERNAME' => 'niendoo2@gmail.com',
            'SMTP_PASSWORD' => 'orbp fcvx laxe fvhz',
            'SMTP_SECURE' => 'tls',
            
            // Error Reporting
            'DISPLAY_ERRORS' => true,
            'ERROR_REPORTING' => E_ALL,
            
            // Security
            'ENABLE_2FA_FOR_ADMIN' => false,
            'API_KEY' => 'cybercrime_hive_api_key_12345_dev',
            
            // Environment
            'ENVIRONMENT' => 'local',
            'DEBUG_MODE' => true
        ];
    }
    
    /**
     * Get production configuration
     * @return array
     */
    private static function getProductionConfig() {
        return [
            // Site Settings
            'SITE_NAME' => 'CyberCrime Hive',
            'SITE_URL' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'cybercrimehive.abdulrauf.xyz'),
            'ADMIN_EMAIL' => 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'cybercrimehive.abdulrauf.xyz'),
            
            // Database Settings (These should be set via environment variables in production)
            'DB_HOST' => $_ENV['DB_HOST'] ?? 'localhost',
            'DB_USER' => $_ENV['DB_USER'] ?? 'cybercrime_user',
            'DB_PASS' => $_ENV['DB_PASS'] ?? 'secure_password_here',
            'DB_NAME' => $_ENV['DB_NAME'] ?? 'cybercrime_hive',
            
            // Path Settings (Production uses relative paths)
            'BASE_PATH' => $_SERVER['DOCUMENT_ROOT'],
            'UPLOAD_PATH_ATTACHMENTS' => $_SERVER['DOCUMENT_ROOT'] . '/uploads/attachments/',
            'UPLOAD_PATH_PROFILE' => $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile_pics/',
            
            // SMTP Settings (Production - should use environment variables)
            'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? 'mail.yourdomain.com',
            'SMTP_PORT' => $_ENV['SMTP_PORT'] ?? 587,
            'SMTP_USERNAME' => $_ENV['SMTP_USERNAME'] ?? 'noreply@yourdomain.com',
            'SMTP_PASSWORD' => $_ENV['SMTP_PASSWORD'] ?? 'smtp_password_here',
            'SMTP_SECURE' => $_ENV['SMTP_SECURE'] ?? 'tls',
            
            // Error Reporting (Production - minimal error display)
            'DISPLAY_ERRORS' => false,
            'ERROR_REPORTING' => E_ERROR | E_WARNING | E_PARSE,
            
            // Security
            'ENABLE_2FA_FOR_ADMIN' => true,
            'API_KEY' => $_ENV['API_KEY'] ?? 'production_api_key_here',
            
            // Environment
            'ENVIRONMENT' => 'production',
            'DEBUG_MODE' => false
        ];
    }
    
    /**
     * Get a specific configuration value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null) {
        $config = self::getConfig();
        return $config[$key] ?? $default;
    }
    
    /**
     * Check if running in local environment
     * @return bool
     */
    public static function isLocal() {
        return self::detectEnvironment() === 'local';
    }
    
    /**
     * Check if running in production environment
     * @return bool
     */
    public static function isProduction() {
        return self::detectEnvironment() === 'production';
    }
    
    /**
     * Initialize environment configuration
     * This should be called early in the application bootstrap
     */
    public static function initialize() {
        $config = self::getConfig();
        
        // Set error reporting based on environment
        ini_set('display_errors', $config['DISPLAY_ERRORS'] ? 1 : 0);
        ini_set('display_startup_errors', $config['DISPLAY_ERRORS'] ? 1 : 0);
        error_reporting($config['ERROR_REPORTING']);
        
        // Set timezone
        date_default_timezone_set('UTC');
        
        // Define constants for backward compatibility
        foreach ($config as $key => $value) {
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Auto-initialize when this file is included
EnvironmentManager::initialize();
?>