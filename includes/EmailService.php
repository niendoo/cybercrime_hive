<?php
/**
 * Enhanced Email Service with Fallback Support
 * Supports primary SMTP and Gmail fallback with comprehensive error handling
 */

// Load composer autoload
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $primaryConfig;
    private $fallbackConfig;
    private $fromName;
    private $fromEmail;
    private $debugMode;
    private $phpmailerLoaded = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load email configuration from config file
        require_once dirname(__DIR__) . '/config/config.php';
        
        // Load email override configuration
        if (file_exists(dirname(__DIR__) . '/config/email_config.php')) {
            require_once dirname(__DIR__) . '/config/email_config.php';
        }
        
        // Load environment variables
        $this->loadEnvironmentConfig();
        
        // Load PHPMailer
        $this->loadPHPMailer();
        
        // Set up primary SMTP configuration using EMAIL_ variables
        $this->primaryConfig = [
            'host' => $_ENV['EMAIL_HOST'] ?? 'smtp.gmail.com',
            'port' => $_ENV['EMAIL_PORT'] ?? 465,
            'username' => $_ENV['EMAIL_USER'] ?? '',
            'password' => $_ENV['EMAIL_PASSWORD'] ?? '',
            'secure' => $_ENV['SMTP_SECURE'] ?? 'ssl'
        ];
        
        // No fallback configuration needed - using Gmail as primary
        
        $this->fromName = SITE_NAME;
        $this->fromEmail = $_ENV['EMAIL_FROM'] ?? ADMIN_EMAIL;
        $this->debugMode = $_ENV['EMAIL_DEBUG_MODE'] ?? false;
    }
    
    /**
     * Load PHPMailer classes
     */
    private function loadPHPMailer() {
        // PHPMailer is loaded via autoload at the top of the file
        $this->phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    
    /**
     * Load environment configuration
     */
    private function loadEnvironmentConfig() {
        if (file_exists(dirname(__DIR__) . '/.env')) {
            $lines = file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value, '"\'');
                }
            }
        }
    }
    
    /**
     * Send an email with fallback support
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email message (can be HTML)
     * @param string $plainText Plain text version of the message
     * @param array $attachments Optional array of attachment file paths
     * @return array Status array with success flag and message
     */
    public function send($to, $subject, $message, $plainText = '', $attachments = array()) {
        // Check if PHPMailer is loaded
        if (!$this->phpmailerLoaded) {
            return array('success' => false, 'message' => 'PHPMailer is not available');
        }
        
        // Check if email is enabled
        if (!ENABLE_EMAIL) {
            return array('success' => false, 'message' => 'Email sending is disabled');
        }
        
        // Always try to send emails - remove environment restrictions
        // Only log if explicitly requested via FORCE_EMAIL_SENDING=false AND in development
        $is_local = $this->isLocalEnvironment();
        $force_send = ($_ENV['FORCE_EMAIL_SENDING'] ?? 'true') === 'true';
        
        // Override recipient for development if specified
        if ($is_local && !empty($_ENV['DEV_EMAIL_OVERRIDE'])) {
            $original_to = $to;
            $to = $_ENV['DEV_EMAIL_OVERRIDE'];
            $this->debugLog("Development mode: Redirecting email from {$original_to} to {$to}");
        }
        
        // Only log instead of send if explicitly disabled AND in local environment
        if (!$force_send && $is_local && ($_ENV['FORCE_EMAIL_SENDING'] ?? 'true') === 'false') {
            return $this->logEmail($to, $subject, $message, 'Development mode - Email logged instead of sent (FORCE_EMAIL_SENDING=false)');
        }
        
        // Send using Gmail SMTP configuration
        $result = $this->sendWithConfig($to, $subject, $message, $plainText, $attachments, $this->primaryConfig, 'Gmail SMTP');
        
        // If sending fails, log the email for debugging
        if (!$result['success']) {
            $this->logEmail($to, $subject, $message, 'Gmail SMTP failed: ' . $result['message']);
        }
        
        return $result;
    }
    
    /**
     * Send email with specific configuration
     */
    private function sendWithConfig($to, $subject, $message, $plainText, $attachments, $config, $configName) {
        // Validate configuration
        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            return array('success' => false, 'message' => $configName . ' configuration incomplete');
        }
        
        try {
            // Create PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Enable debug output if in debug mode
            if ($this->debugMode) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function($str, $level) {
                    $this->debugLog("PHPMailer Debug: " . $str);
                };
            }
            
            // Configure SMTP with working settings
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            
            // Use port 465 with SSL for Gmail (working configuration)
            if ($config['host'] === 'smtp.gmail.com') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
                $mail->Port = 465; // SSL port
            } else {
                $mail->SMTPSecure = $config['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $config['port'];
            }
            
            $mail->Timeout = 30;
            
            // Add minimal SSL options for Gmail
            if ($config['host'] === 'smtp.gmail.com') {
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
            }
            
            // Set email content
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Set plain text version
            if (!empty($plainText)) {
                $mail->AltBody = $plainText;
            } else {
                $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
            }
            
            // Add attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment)) {
                        $mail->addAttachment($attachment);
                    }
                }
            }
            
            // Send the email
            $mail->send();
            
            $this->debugLog("Email sent successfully using " . $configName . " to: " . $to);
            return array('success' => true, 'message' => 'Email sent successfully using ' . $configName);
            
        } catch (Exception $e) {
            $errorMsg = $configName . ' connection error: ' . $e->getMessage();
            $this->debugLog($errorMsg);
            return array('success' => false, 'message' => $errorMsg);
        }
    }
    
    /**
     * Test SMTP connection
     */
    public function testConnection($config = null, $configName = 'Default') {
        if (!$this->phpmailerLoaded) {
            return array('success' => false, 'message' => 'PHPMailer is not available');
        }
        
        if ($config === null) {
            $config = $this->primaryConfig;
        }
        
        // Validate configuration
        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            return array('success' => false, 'message' => $configName . ' configuration incomplete');
        }
        
        try {
            // Create PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Configure SMTP with working settings
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            
            // Use port 465 with SSL for Gmail (working configuration)
            if ($config['host'] === 'smtp.gmail.com') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
                $mail->Port = 465; // SSL port
            } else {
                $mail->SMTPSecure = $config['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $config['port'];
            }
            $mail->Timeout = 30;
            
            // Test connection
            if ($mail->smtpConnect()) {
                $mail->smtpClose();
                return array('success' => true, 'message' => $configName . ' connection successful');
            } else {
                return array('success' => false, 'message' => $configName . ' connection failed');
            }
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => $configName . ' connection error: ' . $e->getMessage());
        }
    }

    /**
     * Check if running in local environment
     */
    private function isLocalEnvironment() {
        // Check environment variable first
        $env = $_ENV['APP_ENV'] ?? '';
        if ($env === 'local' || $env === 'development') {
            return true;
        }
        if ($env === 'production') {
            return false;
        }
        
        // Auto-detect based on common local development indicators
        $server_name = $_SERVER['SERVER_NAME'] ?? '';
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        
        $local_indicators = ['localhost', '127.0.0.1', '.local', 'xampp', 'wamp', 'mamp'];
        
        foreach ($local_indicators as $indicator) {
            if (strpos($server_name, $indicator) !== false || strpos($document_root, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log email for debugging
     */
    private function logEmail($to, $subject, $message, $reason) {
        $log_dir = dirname(__DIR__) . '/logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . '/email_log.txt';
        $log_entry = sprintf(
            "[%s] TO: %s | SUBJECT: %s | REASON: %s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $reason
        );
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        return array('success' => true, 'message' => 'Email logged: ' . $reason);
    }
    
    /**
     * Debug logging
     */
    private function debugLog($message) {
        if (!$this->debugMode) return;
        
        $log_dir = dirname(__DIR__) . '/logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . '/email_debug.log';
        $log_entry = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get configuration status
     */
    public function getConfigStatus() {
        return [
            'environment' => $this->isLocalEnvironment() ? 'local' : 'production',
            'email_enabled' => ENABLE_EMAIL,
            'phpmailer_loaded' => $this->phpmailerLoaded,
            'primary_smtp' => [
                'configured' => !empty($this->primaryConfig['host']) && !empty($this->primaryConfig['username']),
                'host' => $this->primaryConfig['host'],
                'port' => $this->primaryConfig['port'],
                'secure' => $this->primaryConfig['secure']
            ],
            'gmail_fallback' => [
                'configured' => !empty($this->fallbackConfig['username']) && !empty($this->fallbackConfig['password']),
                'host' => $this->fallbackConfig['host'],
                'port' => $this->fallbackConfig['port'],
                'secure' => $this->fallbackConfig['secure']
            ],
            'fallback_enabled' => $_ENV['ENABLE_EMAIL_FALLBACK'] ?? 'true',
            'debug_mode' => $this->debugMode,
            'force_sending' => $_ENV['FORCE_EMAIL_SENDING'] ?? 'false'
        ];
    }
}
?>
