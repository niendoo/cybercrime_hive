<?php
/**
 * Two-Factor Authentication functions for CyberCrime Hive
 * Uses TOTP (Time-based One-Time Password) algorithm
 */

// Require necessary libraries
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Generate a random secret key for TOTP
 * @return string Base32 encoded secret key
 */
function generate_tfa_secret() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 character set
    $secret = '';
    
    // Generate a 32-character random string
    for ($i = 0; $i < 32; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    
    return $secret;
}

/**
 * Generate a set of backup codes for 2FA recovery
 * @param int $count Number of backup codes to generate
 * @return array Array of backup codes
 */
function generate_backup_codes($count = 8) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
    }
    return $codes;
}

/**
 * Calculate the current TOTP code from a secret
 * @param string $secret The base32 encoded secret
 * @param int $digits Number of digits in the code (default 6)
 * @param int $period Time period for code validity in seconds (default 30)
 * @return string The TOTP code
 */
function calculate_totp($secret, $digits = 6, $period = 30) {
    // Decode the secret from base32
    $decoded = base32_decode($secret);
    
    // Calculate counter value based on current time
    $counter = floor(time() / $period);
    
    // Convert counter to binary string
    $binary = pack('N*', 0, $counter); // 'N' = unsigned long, big-endian
    
    // Generate HMAC-SHA1 hash
    $hash = hash_hmac('sha1', $binary, $decoded, true);
    
    // Extract 4 bytes from the hash based on the last nibble
    $offset = ord($hash[19]) & 0x0F;
    $extract = substr($hash, $offset, 4);
    
    // Convert to integer
    $value = unpack('N', $extract)[1] & 0x7FFFFFFF;
    
    // Generate the code with specified number of digits
    $code = $value % pow(10, $digits);
    return str_pad($code, $digits, '0', STR_PAD_LEFT);
}

/**
 * Decode a base32 string
 * @param string $string The base32 encoded string
 * @return string Decoded binary string
 */
function base32_decode($string) {
    $lut = array(
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
        'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
        'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
        'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
    );
    
    // Remove padding characters
    $string = strtoupper(rtrim($string, '='));
    
    // Initialize variables
    $result = '';
    $buffer = 0;
    $bits = 0;
    
    // Process each character
    for ($i = 0; $i < strlen($string); $i++) {
        $char = $string[$i];
        if (!isset($lut[$char])) {
            continue; // Skip invalid characters
        }
        
        // Add 5 bits to buffer
        $buffer = ($buffer << 5) | $lut[$char];
        $bits += 5;
        
        // Extract 8-bit bytes when we have enough bits
        if ($bits >= 8) {
            $bits -= 8;
            $result .= chr(($buffer >> $bits) & 0xFF);
        }
    }
    
    return $result;
}

/**
 * Generate a QR code URL for use with authenticator apps
 * @param string $issuer The issuer name (e.g., "CyberCrime Hive")
 * @param string $account The user account (usually email)
 * @param string $secret The TOTP secret
 * @return string URL for QR code generation
 */
function get_qr_code_url($issuer, $account, $secret) {
    $issuer = urlencode($issuer);
    $account = urlencode($account);
    $secret = urlencode($secret);
    
    return "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=otpauth://totp/$issuer:$account?secret=$secret&issuer=$issuer";
}

/**
 * Verify a TOTP code against a secret
 * @param string $secret The user's TOTP secret
 * @param string $code The code entered by the user
 * @param int $window Time window for code validity (default 1)
 * @return bool True if the code is valid, false otherwise
 */
function verify_totp_code($secret, $code, $window = 1) {
    // Check current time period
    $current = calculate_totp($secret);
    if ($current === $code) {
        return true;
    }
    
    // Check previous and next time periods within the window
    for ($i = 1; $i <= $window; $i++) {
        // Check previous period
        $prev_counter = floor((time() - (30 * $i)) / 30);
        $binary = pack('N*', 0, $prev_counter);
        $hash = hash_hmac('sha1', $binary, base32_decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        $prev_code = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
        
        if ($prev_code === $code) {
            return true;
        }
        
        // Check next period
        $next_counter = floor((time() + (30 * $i)) / 30);
        $binary = pack('N*', 0, $next_counter);
        $hash = hash_hmac('sha1', $binary, base32_decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        $next_code = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
        
        if ($next_code === $code) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if a backup code is valid for a user
 * @param int $user_id The user ID
 * @param string $code The backup code to check
 * @return bool True if valid, false otherwise
 */
function verify_backup_code($user_id, $code) {
    $conn = get_database_connection();
    $stmt = $conn->prepare("SELECT backup_codes FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $backup_codes = json_decode($user['backup_codes'], true);
        
        if (is_array($backup_codes) && in_array($code, $backup_codes)) {
            // Remove used backup code
            $backup_codes = array_diff($backup_codes, [$code]);
            
            // Update the backup codes in the database
            $updated_codes = json_encode($backup_codes);
            $update = $conn->prepare("UPDATE users SET backup_codes = ? WHERE user_id = ?");
            $update->bind_param("si", $updated_codes, $user_id);
            $update->execute();
            $update->close();
            
            return true;
        }
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

/**
 * Enable 2FA for a user
 * @param int $user_id The user ID
 * @param string $secret The generated TOTP secret
 * @return array Array with status and backup codes
 */
function enable_tfa($user_id, $secret) {
    $conn = get_database_connection();
    
    // Generate backup codes
    $backup_codes = generate_backup_codes();
    $backup_codes_json = json_encode($backup_codes);
    
    // Update user record
    $stmt = $conn->prepare("UPDATE users SET tfa_enabled = 1, tfa_secret = ?, backup_codes = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $secret, $backup_codes_json, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    if ($result) {
        return [
            'success' => true,
            'backup_codes' => $backup_codes
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to enable 2FA'
        ];
    }
}

/**
 * Disable 2FA for a user
 * @param int $user_id The user ID
 * @return bool True if successful, false otherwise
 */
function disable_tfa($user_id) {
    $conn = get_database_connection();
    $stmt = $conn->prepare("UPDATE users SET tfa_enabled = 0, tfa_secret = NULL, backup_codes = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}
