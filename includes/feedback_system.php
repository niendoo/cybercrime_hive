<?php
/**
 * Feedback System Core Functions
 * Handles secure token generation, link creation, and feedback processing
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/url_helper.php';

class FeedbackSystem {
    private $conn;
    
    public function __construct() {
        $this->conn = get_database_connection();
    }
    
    /**
     * Generate a secure feedback token for a resolved report
     * @param int $report_id The ID of the resolved report
     * @param int $user_id The ID of the user who submitted the report
     * @param int $expiry_hours Hours until token expires (default: 168 = 7 days)
     * @return array|false Token data or false on failure
     */
    public function generateFeedbackToken($report_id, $user_id, $expiry_hours = 168) {
        try {
            // Verify the report exists and is resolved
            $stmt = $this->conn->prepare("SELECT status FROM reports WHERE report_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $report_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Report not found or access denied");
            }
            
            $report = $result->fetch_assoc();
            if ($report['status'] !== 'Resolved') {
                throw new Exception("Report must be resolved to generate feedback token");
            }
            
            // Check if a valid token already exists
            $existing_token = $this->getValidToken($report_id, $user_id);
            if ($existing_token) {
                return $existing_token;
            }
            
            // Generate secure token
            $token = $this->generateSecureToken();
            $token_hash = hash('sha256', $token);
            
            // Calculate expiry time
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));
            
            // Get client information
            $ip_address = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Insert token into database
            $stmt = $this->conn->prepare("
                INSERT INTO feedback_tokens 
                (report_id, user_id, token_hash, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissss", $report_id, $user_id, $token_hash, $expires_at, $ip_address, $user_agent);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to save token: " . $stmt->error);
            }
            
            $token_id = $this->conn->insert_id;
            
            // Create metrics entry
            $this->createFeedbackMetrics($report_id);
            
            return [
                'token_id' => $token_id,
                'token' => $token,
                'expires_at' => $expires_at,
                'feedback_url' => $this->generateFeedbackURL($token)
            ];
            
        } catch (Exception $e) {
            error_log("Feedback token generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a cryptographically secure token
     * @return string 64-character hexadecimal token
     */
    private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Get client IP address (handles proxies)
     * @return string Client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if a valid token already exists for the report
     * @param int $report_id
     * @param int $user_id
     * @return array|false Existing token data or false
     */
    private function getValidToken($report_id, $user_id) {
        $stmt = $this->conn->prepare("
            SELECT token_id, token_hash, expires_at 
            FROM feedback_tokens 
            WHERE report_id = ? AND user_id = ? AND is_active = 1 AND expires_at > NOW() AND used_at IS NULL
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->bind_param("ii", $report_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $token_data = $result->fetch_assoc();
            // Note: We can't return the original token since it's hashed
            // The existing token would need to be regenerated or extended
            return false; // Force new token generation for security
        }
        
        return false;
    }
    
    /**
     * Generate the complete feedback URL
     * @param string $token The secure token
     * @return string Complete feedback URL
     */
    private function generateFeedbackURL($token) {
        // Use the new URL helper for reliable URL generation
        if (class_exists('URLHelper')) {
            return URLHelper::generateFeedbackURL($token);
        }
        // Fallback to original method
        return SITE_URL . '/feedback/?token=' . urlencode($token);
    }
    
    /**
     * Create initial metrics entry for tracking
     * @param int $report_id
     */
    private function createFeedbackMetrics($report_id) {
        $stmt = $this->conn->prepare("
            INSERT INTO feedback_metrics (report_id, token_generated_at) 
            VALUES (?, NOW())
        ");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
    }
    
    /**
     * Validate a feedback token
     * @param string $token The token to validate
     * @return array|false Token data if valid, false otherwise
     */
    public function validateToken($token) {
        try {
            $token_hash = hash('sha256', $token);
            
            $stmt = $this->conn->prepare("
                SELECT ft.token_id, ft.report_id, ft.user_id, ft.expires_at, ft.used_at,
                       r.title as report_title, u.email as user_email
                FROM feedback_tokens ft
                JOIN reports r ON ft.report_id = r.report_id
                JOIN users u ON ft.user_id = u.user_id
                WHERE ft.token_hash = ? AND ft.is_active = 1
            ");
            $stmt->bind_param("s", $token_hash);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false;
            }
            
            $token_data = $result->fetch_assoc();
            
            // Check if token has expired
            if (strtotime($token_data['expires_at']) < time()) {
                return false;
            }
            
            // Check if token has already been used
            if ($token_data['used_at'] !== null) {
                return false;
            }
            
            // Update metrics - link clicked
            $this->updateMetrics($token_data['report_id'], 'link_clicked_at');
            
            return $token_data;
            
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark token as used
     * @param string $token The token to mark as used
     * @return bool Success status
     */
    public function markTokenAsUsed($token) {
        try {
            $token_hash = hash('sha256', $token);
            
            $stmt = $this->conn->prepare("
                UPDATE feedback_tokens 
                SET used_at = NOW() 
                WHERE token_hash = ? AND is_active = 1
            ");
            $stmt->bind_param("s", $token_hash);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Mark token as used error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update feedback metrics
     * @param int $report_id
     * @param string $field The field to update (e.g., 'link_clicked_at', 'feedback_started_at')
     */
    public function updateMetrics($report_id, $field) {
        try {
            $allowed_fields = ['token_sent_at', 'link_clicked_at', 'feedback_started_at', 'feedback_completed_at'];
            
            if (!in_array($field, $allowed_fields)) {
                throw new Exception("Invalid metrics field: $field");
            }
            
            $stmt = $this->conn->prepare("
                UPDATE feedback_metrics 
                SET $field = NOW(),
                    time_to_click_hours = CASE 
                        WHEN '$field' = 'link_clicked_at' AND link_clicked_at IS NULL 
                        THEN TIMESTAMPDIFF(HOUR, token_generated_at, NOW())
                        ELSE time_to_click_hours 
                    END,
                    time_to_complete_hours = CASE 
                        WHEN '$field' = 'feedback_completed_at' AND feedback_completed_at IS NULL 
                        THEN TIMESTAMPDIFF(HOUR, token_generated_at, NOW())
                        ELSE time_to_complete_hours 
                    END
                WHERE report_id = ?
            ");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Update metrics error: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up expired tokens
     * @return int Number of tokens cleaned up
     */
    public function cleanupExpiredTokens() {
        try {
            $stmt = $this->conn->prepare("
                UPDATE feedback_tokens 
                SET is_active = 0 
                WHERE expires_at < NOW() AND is_active = 1
            ");
            $stmt->execute();
            
            return $stmt->affected_rows;
            
        } catch (Exception $e) {
            error_log("Token cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Send feedback request email
     * @param int $report_id
     * @param string $user_email
     * @param string $feedback_url
     * @return bool Success status
     */
    public function sendFeedbackEmail($report_id, $user_email, $feedback_url) {
        try {
            // Get user_id from report_id
            $stmt = $this->conn->prepare("SELECT user_id, title, created_at FROM reports WHERE report_id = ?");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return false;
            }
            
            $report = $result->fetch_assoc();
            $user_id = $report['user_id'];
            
            // Create feedback email message
            $message = "Dear User,\n\n";
            $message .= "Your cybercrime report has been resolved. We would appreciate your feedback to help us improve our services.\n\n";
            $message .= "Please click the following link to provide your feedback:\n";
            $message .= $feedback_url . "\n\n";
            $message .= "This feedback link will expire in 7 days.\n\n";
            $message .= "Thank you for using our service.\n\n";
            $message .= "Best regards,\n";
            $message .= "CyberCrime Hive Team";
            
            // Update metrics - email sent
            $this->updateMetrics($report_id, 'token_sent_at');
            
            // Use the existing working email notification system
            require_once __DIR__ . '/functions.php';
            return send_email_notification($user_id, $message);
            
        } catch (Exception $e) {
            error_log("Send feedback email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate email content for feedback request
     * @param array $report Report data
     * @param string $feedback_url Feedback URL
     * @return string Email content
     */
    private function generateFeedbackEmailContent($report, $feedback_url) {
        $content = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #2c3e50;'>Your Report Has Been Resolved</h2>
                
                <p>Dear User,</p>
                
                <p>We're pleased to inform you that your cybercrime report has been successfully resolved:</p>
                
                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <strong>Report:</strong> " . htmlspecialchars($report['title']) . "<br>
                    <strong>Submitted:</strong> " . date('F j, Y', strtotime($report['created_at'])) . "
                </div>
                
                <p>Your feedback is important to us and helps improve our services. Please take a few minutes to share your experience:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$feedback_url' style='background-color: #3498db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Provide Feedback</a>
                </div>
                
                <p><strong>Important:</strong></p>
                <ul>
                    <li>This feedback link is secure and expires in 7 days</li>
                    <li>Your responses will remain anonymous unless you choose to provide contact information</li>
                    <li>The survey takes approximately 2-3 minutes to complete</li>
                </ul>
                
                <p>Thank you for helping us serve you better.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    This is an automated message. Please do not reply to this email.<br>
                    If you have questions, please contact our support team.
                </p>
            </div>
        </body>
        </html>
        ";
        
        return $content;
    }
    

}

// Utility function to get feedback system instance
function getFeedbackSystem() {
    return new FeedbackSystem();
}
?>