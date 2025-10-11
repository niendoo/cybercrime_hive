<?php
require_once 'database.php';

function initialize_database() {
    $conn = get_database_connection();
    
    // Create database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $conn->select_db(DB_NAME);
    
    // Users table
    $users_table = "CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE,
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        phone VARCHAR(20),
        registered_at DATETIME,
        role ENUM('user', 'admin') DEFAULT 'user'
    )";
    
    // Reports table
    $reports_table = "CREATE TABLE IF NOT EXISTS reports (
        report_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(150) NOT NULL,
        description TEXT NOT NULL,
        category ENUM(
            'Online Banking Fraud',
            'Credit Card Fraud',
            'Identity Theft',
            'Phishing',
            'Advance Fee Fraud',
            'Investment Scam',
            'Online Auction Fraud',
            'Fake Job Offer Scam',
            'Lottery Scam',
            'Ransomware Attack',
            'Email Spoofing',
            'Business Email Compromise',
            'SMS Phishing (Smishing)',
            'Voice Phishing (Vishing)',
            'Social Engineering',
            'Fake Social Media Profiles',
            'Spam Messaging',
            'Unauthorized Access',
            'Password Cracking',
            'Website Defacement',
            'Database Breach',
            'Brute Force Attack',
            'Keylogging',
            'Malware Distribution',
            'Trojan/Rootkit Infection',
            'Network Intrusion',
            'Social Media Account Hijacking',
            'Impersonation',
            'Online Dating Scam',
            'Deepfake Distribution',
            'Fake Apps or Websites',
            'Cyberbullying',
            'Cyberstalking',
            'Online Defamation',
            'Doxxing',
            'Revenge Porn',
            'Online Threats',
            'Software Piracy',
            'Illegal Downloads',
            'Copyright Infringement',
            'Hacking',
            'Fraud',
            'Hacked Game Distribution',
            'Cracked Software',
            'DDoS Attack',
            'Email Bombing',
            'Ping of Death',
            'Botnet Activity',
            'Dark Web Drug Trade',
            'Human Trafficking',
            'Stolen Data Sales',
            'Weapons Trade',
            'Fake Document Sales',
            'Cyber Espionage',
            'Hacktivism',
            'Disinformation Campaign',
            'Gov Infrastructure Attack',
            'State Data Leak',
            'Other'
        ) NOT NULL,
        incident_date DATETIME NOT NULL,
        status ENUM('Submitted', 'Under Review', 'In Investigation', 'Resolved') DEFAULT 'Submitted',
        tracking_code VARCHAR(20) UNIQUE NOT NULL,
        created_at DATETIME,
        updated_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";
    
    // Attachments table
    $attachments_table = "CREATE TABLE IF NOT EXISTS attachments (
        attachment_id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at DATETIME,
        FOREIGN KEY (report_id) REFERENCES reports(report_id)
    )";
    
    // Notifications table
    $notifications_table = "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        user_id INT NOT NULL,
        notification_type ENUM('SMS', 'Email') NOT NULL,
        message_content TEXT NOT NULL,
        sent_timestamp DATETIME,
        status ENUM('Sent', 'Failed') DEFAULT 'Sent',
        FOREIGN KEY (report_id) REFERENCES reports(report_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";
    
    // Admin logs table
    $admin_logs_table = "CREATE TABLE IF NOT EXISTS admin_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        report_id INT,
        timestamp DATETIME,
        FOREIGN KEY (admin_id) REFERENCES users(user_id),
        FOREIGN KEY (report_id) REFERENCES reports(report_id)
    )";
    
    // Feedback table
    $feedback_table = "CREATE TABLE IF NOT EXISTS feedback (
        feedback_id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        comments TEXT,
        submitted_at DATETIME,
        FOREIGN KEY (report_id) REFERENCES reports(report_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";
    
    // 2FA table for admin security
    $twofa_table = "CREATE TABLE IF NOT EXISTS two_factor_auth (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        auth_code VARCHAR(6) NOT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";
    
    // Knowledge base table
    $kb_table = "CREATE TABLE IF NOT EXISTS knowledge_base (
        kb_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        category VARCHAR(100) NOT NULL,
        created_at DATETIME,
        updated_at DATETIME
    )";
    
    // User logs table
    $user_logs_table = "CREATE TABLE IF NOT EXISTS user_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        report_id INT,
        log_time DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (report_id) REFERENCES reports(report_id)
    )";
    
    // Execute the queries
    $conn->query($users_table);
    $conn->query($reports_table);
    $conn->query($attachments_table);
    $conn->query($notifications_table);
    $conn->query($admin_logs_table);
    $conn->query($feedback_table);
    $conn->query($twofa_table);
    $conn->query($kb_table);
    $conn->query($user_logs_table);
    
    // Create a default admin user
    $admin_check = $conn->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
    if ($admin_check->num_rows == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $timestamp = date('Y-m-d H:i:s');
        $conn->query("INSERT INTO users (username, email, password, phone, registered_at, role) 
                     VALUES ('admin', 'admin@cybercrimehive.com', '$admin_password', '1234567890', '$timestamp', 'admin')");
        echo "Default admin created.\n";
    }
    
    echo "Database initialization complete.\n";
    $conn->close();
}

// Uncomment to run the initialization
// initialize_database();
?>
