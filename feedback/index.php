<?php
// Feedback submission page for CyberCrime Hive
session_start();
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/feedback_system.php';

$page_title = "Feedback";
$body_class = "feedback-page";
$success_message = '';
$error_message = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'] ?? '';
    $overall_rating = $_POST['rating'] ?? '';
    $communication_rating = $_POST['communication_rating'] ?? '';
    $resolution_speed_rating = $_POST['resolution_speed_rating'] ?? '';
    $professionalism_rating = $_POST['professionalism_rating'] ?? '';
    $would_recommend = $_POST['would_recommend'] ?? '';
    $feedback = $_POST['feedback'] ?? '';
    
    if (empty($token)) {
        $error_message = "Invalid feedback token.";
    } elseif (empty($overall_rating)) {
        $error_message = "Please provide an overall rating.";
    } else {
        // Process the feedback using FeedbackSystem
        $feedbackSystem = getFeedbackSystem();
        $token_data = $feedbackSystem->validateToken($token);
        
        if (!$token_data) {
            $error_message = "Invalid or expired feedback token.";
        } else {
            // Get database connection
            $conn = get_database_connection();
            
            // Insert the feedback with multi-category ratings
            $stmt = $conn->prepare("INSERT INTO feedback (report_id, user_id, token_id, overall_rating, communication_rating, resolution_speed_rating, professionalism_rating, would_recommend, comments, submitted_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt->bind_param("iiiiiiissss", 
                $token_data['report_id'], 
                $token_data['user_id'], 
                $token_data['token_id'],
                $overall_rating,
                $communication_rating,
                $resolution_speed_rating, 
                $professionalism_rating,
                $would_recommend,
                $feedback,
                $ip_address,
                $user_agent
            );
            
            if ($stmt->execute()) {
                // Mark token as used
                $feedbackSystem->markTokenAsUsed($token);
                
                // Update metrics
                $feedbackSystem->updateMetrics($token_data['report_id'], 'feedback_completed_at');
                
                $success_message = "Thank you for your feedback! Your response has been recorded.";
                
                // Clear form data
                $token = '';
                $overall_rating = '';
                $communication_rating = '';
                $resolution_speed_rating = '';
                $professionalism_rating = '';
                $would_recommend = '';
                $feedback = '';
            } else {
                $error_message = "Error saving feedback. Please try again.";
            }
        }
    }
} else {
    // Get token from URL
    $token = $_GET['token'] ?? '';
}

// If no token provided, show error
if (empty($token) && empty($success_message)) {
    $error_message = "No feedback token provided.";
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Feedback</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        </div>
                        <div class="text-center">
                            <a href="<?php echo SITE_URL; ?>" class="btn btn-primary">Return to Home</a>
                        </div>
                    <?php elseif (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        </div>
                    <?php else: ?>
                        <p>We value your feedback on how we handled your report. Please take a moment to share your experience.</p>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Overall satisfaction with our service:</label>
                                <div class="rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating-<?php echo $i; ?>" <?php echo isset($overall_rating) && (string)$overall_rating === (string)$i ? 'checked' : ''; ?>>
                                        <label for="rating-<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                                <small class="text-muted">Click a star to rate.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Communication quality:</label>
                                <div class="rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="communication_rating" value="<?php echo $i; ?>" id="communication-rating-<?php echo $i; ?>" <?php echo isset($_POST['communication_rating']) && (string)$_POST['communication_rating'] === (string)$i ? 'checked' : ''; ?>>
                                        <label for="communication-rating-<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Resolution speed:</label>
                                <div class="rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="resolution_speed_rating" value="<?php echo $i; ?>" id="resolution-rating-<?php echo $i; ?>" <?php echo isset($_POST['resolution_speed_rating']) && (string)$_POST['resolution_speed_rating'] === (string)$i ? 'checked' : ''; ?>>
                                        <label for="resolution-rating-<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Professionalism:</label>
                                <div class="rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="professionalism_rating" value="<?php echo $i; ?>" id="professionalism-rating-<?php echo $i; ?>" <?php echo isset($_POST['professionalism_rating']) && (string)$_POST['professionalism_rating'] === (string)$i ? 'checked' : ''; ?>>
                                        <label for="professionalism-rating-<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Would you recommend us?</label>
                                <div class="d-flex gap-3">
                                    <?php foreach (["Yes","No","Maybe"] as $opt): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="would_recommend" id="recommend-<?php echo strtolower($opt); ?>" value="<?php echo $opt; ?>" <?php echo (isset($_POST['would_recommend']) && $_POST['would_recommend'] === $opt) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="recommend-<?php echo strtolower($opt); ?>"><?php echo $opt; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="feedback">Additional comments (optional):</label>
                                <textarea class="form-control" id="feedback" name="feedback" rows="4" placeholder="Please share any additional thoughts or suggestions..."><?php echo isset($_POST['feedback']) ? htmlspecialchars($_POST['feedback']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-primary">Submit Feedback</button>
                                <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<style>
/* Inline styles for star rating UI (kept local to this page) */
.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.rating input {
    display: none;
}
.rating label {
    color: #ddd;
    font-size: 24px;
    padding: 0 5px;
    cursor: pointer;
}
.rating input:checked ~ label {
    color: #ffc107;
}
.rating label:hover,
.rating label:hover ~ label {
    color: #ffc107;
}
</style>