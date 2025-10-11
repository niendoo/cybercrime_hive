<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Process feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['report_id'])) {
    $report_id = intval($_POST['report_id']);
    // Map legacy 'rating' to new 'overall_rating' if present
    $overall_rating = isset($_POST['overall_rating']) ? intval($_POST['overall_rating']) : (isset($_POST['rating']) ? intval($_POST['rating']) : 0);
    $communication_rating = isset($_POST['communication_rating']) && $_POST['communication_rating'] !== '' ? intval($_POST['communication_rating']) : null;
    $resolution_speed_rating = isset($_POST['resolution_speed_rating']) && $_POST['resolution_speed_rating'] !== '' ? intval($_POST['resolution_speed_rating']) : null;
    $professionalism_rating = isset($_POST['professionalism_rating']) && $_POST['professionalism_rating'] !== '' ? intval($_POST['professionalism_rating']) : null;
    $would_recommend = isset($_POST['would_recommend']) ? sanitize_input($_POST['would_recommend']) : null;
    $comments = isset($_POST['comments']) ? sanitize_input($_POST['comments']) : '';

    // Validate input
    if ($overall_rating < 1 || $overall_rating > 5) {
        redirect_with_message(SITE_URL . "/reports/track.php?code=" . $_POST['tracking_code'], "Please provide a valid overall rating (1-5 stars).", "error");
    } else {
        // Verify that the report belongs to the user and is resolved
        $conn = get_database_connection();
        $stmt = $conn->prepare("SELECT * FROM reports WHERE report_id = ? AND user_id = ? AND status = 'Resolved'");
        $stmt->bind_param("ii", $report_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            redirect_with_message(SITE_URL . "/user/dashboard.php", "You can only provide feedback for your own resolved reports.", "error");
        } else {
            $report = $result->fetch_assoc();
            
            // Check if feedback already exists
            $stmt = $conn->prepare("SELECT * FROM feedback WHERE report_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $report_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing feedback
                $feedback = $result->fetch_assoc();
                $submitted_at = date('Y-m-d H:i:s');
                // Update multi-category ratings where available
                $stmt = $conn->prepare("UPDATE feedback SET overall_rating = ?, communication_rating = ?, resolution_speed_rating = ?, professionalism_rating = ?, comments = ?, would_recommend = ?, submitted_at = ? WHERE feedback_id = ?");
                $stmt->bind_param(
                    "iiiisssi",
                    $overall_rating,
                    $communication_rating,
                    $resolution_speed_rating,
                    $professionalism_rating,
                    $comments,
                    $would_recommend,
                    $submitted_at,
                    $feedback['feedback_id']
                );
                
                if ($stmt->execute()) {
                    redirect_with_message(SITE_URL . "/reports/track.php?code=" . $report['tracking_code'], "Your feedback has been updated successfully. Thank you!", "success");
                } else {
                    redirect_with_message(SITE_URL . "/reports/track.php?code=" . $report['tracking_code'], "Failed to update feedback. Please try again.", "error");
                }
            } else {
                // Insert new feedback
                $submitted_at = date('Y-m-d H:i:s');
                // Insert using enhanced feedback schema
                $stmt = $conn->prepare("INSERT INTO feedback (report_id, user_id, overall_rating, communication_rating, resolution_speed_rating, professionalism_rating, comments, would_recommend, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "iiiiii sss"
                    , $report_id
                    , $user_id
                    , $overall_rating
                    , $communication_rating
                    , $resolution_speed_rating
                    , $professionalism_rating
                    , $comments
                    , $would_recommend
                    , $submitted_at
                );
                
                if ($stmt->execute()) {
                    // Notify admin about new feedback
                    $admin_message = "New feedback received for report #{$report_id} (Tracking Code: {$report['tracking_code']})\n";
                    $admin_message .= "Overall Rating: $overall_rating/5 stars\n";
                    if (!empty($comments)) {
                        $admin_message .= "Comments: $comments";
                    }
                    
                    // Get admin users
                    $admins_stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
                    $admins_stmt->execute();
                    $admins_result = $admins_stmt->get_result();
                    if ($admin = $admins_result->fetch_assoc()) {
                        create_notification($report_id, $admin['user_id'], 'Email', $admin_message);
                    }
                    $admins_stmt->close();
                    
                    redirect_with_message(SITE_URL . "/reports/track.php?code=" . $report['tracking_code'], "Your feedback has been submitted successfully. Thank you!", "success");
                } else {
                    redirect_with_message(SITE_URL . "/reports/track.php?code=" . $report['tracking_code'], "Failed to submit feedback. Please try again.", "error");
                }
            }
        }
        
        $stmt->close();
        $conn->close();
    }
} else {
    // Redirect to dashboard if not coming from a form submission
    header("Location: " . SITE_URL . "/user/dashboard.php");
    exit();
}
?>
