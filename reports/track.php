<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$report = null;
$attachments = [];
$error_message = '';
$tracking_code = '';

// Check if tracking code is provided via GET or POST
if (isset($_GET['code']) && !empty($_GET['code'])) {
    $tracking_code = sanitize_input($_GET['code']);
} elseif (isset($_POST['tracking_code']) && !empty($_POST['tracking_code'])) {
    $tracking_code = sanitize_input($_POST['tracking_code']);
}

// Process tracking request if code is available
if (!empty($tracking_code)) {
    $conn = get_database_connection();
    
    // Get report by tracking code
    $stmt = $conn->prepare("SELECT r.*, u.username 
                          FROM reports r 
                          JOIN users u ON r.user_id = u.user_id 
                          WHERE r.tracking_code = ?");
    $stmt->bind_param("s", $tracking_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $report = $result->fetch_assoc();
        
        // Check if logged-in user is associated with this report
        $user_authorized = false;
        if (isset($_SESSION['user_id'])) {
            if ($_SESSION['user_id'] == $report['user_id'] || $_SESSION['role'] == 'admin') {
                $user_authorized = true;
            }
        }
        
        // If not authorized, show limited info
        if (!$user_authorized) {
            // Only keep necessary fields for public view
            $limited_report = [
                'report_id' => $report['report_id'],
                'tracking_code' => $report['tracking_code'],
                'status' => $report['status'],
                'created_at' => $report['created_at'],
                'updated_at' => $report['updated_at']
            ];
            $report = $limited_report;
        } else {
            // Get attachments if authorized
            $stmt = $conn->prepare("SELECT * FROM attachments WHERE report_id = ? ORDER BY uploaded_at DESC");
            $stmt->bind_param("i", $report['report_id']);
            $stmt->execute();
            $attachments_result = $stmt->get_result();
            
            while ($attachment = $attachments_result->fetch_assoc()) {
                $attachments[] = $attachment;
            }
        }
    } else {
        $error_message = 'Invalid tracking code. Please check and try again.';
    }
    
    $stmt->close();
    $conn->close();
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active">Track Report</li>
            </ol>
        </nav>
        
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-search me-2"></i>Track Your Report</h4>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if (!$report): ?>
                    <div class="alert alert-info">
                        <p>Enter your tracking code to check the status of your report.</p>
                    </div>
                    
                    <form method="post" action="" class="mb-4">
                        <div class="row g-3 align-items-center justify-content-center">
                            <div class="col-auto">
                                <label for="tracking_code" class="col-form-label">Tracking Code:</label>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="tracking_code" name="tracking_code" class="form-control" placeholder="Enter tracking code" required value="<?php echo $tracking_code; ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Track
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <div class="alert alert-secondary">
                        <h5><i class="fas fa-info-circle me-2"></i>Don't have a tracking code?</h5>
                        <p>If you are a registered user, you can view all your reports from your dashboard.</p>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Your Account
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/user/dashboard.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Display report details -->
                    <div class="card bg-info text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Tracking Code: <strong><?php echo $report['tracking_code']; ?></strong></h5>
                                <span class="badge bg-<?php 
                                    if ($report['status'] == 'Submitted') echo 'secondary'; 
                                    elseif ($report['status'] == 'Under Review') echo 'info';
                                    elseif ($report['status'] == 'In Investigation') echo 'primary';
                                    elseif ($report['status'] == 'Resolved') echo 'success';
                                ?> fs-6"><?php echo $report['status']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Report Status Timeline</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="timeline">
                                        <li class="timeline-item">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title">Report Submitted</h6>
                                                <p class="timeline-date"><?php echo format_date($report['created_at']); ?></p>
                                            </div>
                                        </li>
                                        
                                        <?php if ($report['status'] != 'Submitted' || $report['updated_at'] != $report['created_at']): ?>
                                        <li class="timeline-item">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title">Last Updated</h6>
                                                <p class="timeline-date"><?php echo format_date($report['updated_at']); ?></p>
                                                <p>Current Status: <strong><?php echo $report['status']; ?></strong></p>
                                            </div>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Status Information</h5>
                                </div>
                                <div class="card-body">
                                    <p class="fw-bold">What does your status mean?</p>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item">
                                            <span class="badge bg-secondary me-2">Submitted</span>
                                            Your report has been received and is awaiting initial review.
                                        </li>
                                        <li class="list-group-item">
                                            <span class="badge bg-info me-2">Under Review</span>
                                            Your report is being reviewed by our cybercrime specialists.
                                        </li>
                                        <li class="list-group-item">
                                            <span class="badge bg-primary me-2">In Investigation</span>
                                            Active investigation is underway by law enforcement.
                                        </li>
                                        <li class="list-group-item">
                                            <span class="badge bg-success me-2">Resolved</span>
                                            The investigation has been completed.
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($report['title'])): ?>
                    <!-- Full report details for authorized users -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Report Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <h4><?php echo htmlspecialchars($report['title']); ?></h4>
                                    <p class="text-muted">Submitted by: <?php echo htmlspecialchars($report['username']); ?></p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <span class="badge bg-secondary">Category: <?php echo $report['category']; ?></span>
                                    <p class="text-muted mt-2">Incident Date: <?php echo format_date($report['incident_date']); ?></p>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Description</h6>
                                </div>
                                <div class="card-body">
                                    <p><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if (count($attachments) > 0): ?>
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Attachments</h6>
                                </div>
                                <div class="card-body">
                                    <div class="list-group">
                                        <?php foreach ($attachments as $attachment): ?>
                                            <a href="<?php echo SITE_URL . '/' . ltrim(str_replace('/cybercrime_hive', '', $attachment['file_path']), '/'); ?>" class="list-group-item list-group-item-action" target="_blank">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">
                                                        <?php 
                                                        $file_ext = pathinfo($attachment['file_path'], PATHINFO_EXTENSION);
                                                        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                            echo '<i class="fas fa-file-image text-primary me-2"></i>';
                                                        } elseif ($file_ext == 'pdf') {
                                                            echo '<i class="fas fa-file-pdf text-danger me-2"></i>';
                                                        } else {
                                                            echo '<i class="fas fa-file text-secondary me-2"></i>';
                                                        }
                                                        
                                                        // Extract filename from path
                                                        echo basename($attachment['file_path']);
                                                        ?>
                                                    </h6>
                                                    <small>Uploaded: <?php echo format_date($attachment['uploaded_at']); ?></small>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($report['status'] == 'Resolved'): ?>
                    <!-- Feedback form for resolved reports -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Provide Feedback</h5>
                        </div>
                        <div class="card-body">
                            <p>Your case has been resolved. We would appreciate your feedback on our service.</p>
                            <form method="post" action="<?php echo SITE_URL; ?>/user/submit_feedback.php">
                                <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Rate your experience:</label>
                                    <div class="rating">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating-<?php echo $i; ?>">
                                            <label for="rating-<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="comments" class="form-label">Comments:</label>
                                    <textarea class="form-control" id="comments" name="comments" rows="3"></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo SITE_URL; ?>/reports/track.php" class="btn btn-outline-secondary">
                            <i class="fas fa-search me-2"></i>Track Another Report
                        </a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo SITE_URL; ?>/reports/submit.php" class="btn btn-primary">
                                <i class="fas fa-file-alt me-2"></i>Submit New Report
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Submit a Report
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline styles */
.timeline {
    list-style-type: none;
    position: relative;
    padding-left: 30px;
    margin: 0;
}

.timeline:before {
    content: ' ';
    background: #dee2e6;
    display: inline-block;
    position: absolute;
    left: 9px;
    width: 2px;
    height: 100%;
    z-index: 1;
}

.timeline-item {
    margin: 20px 0;
}

.timeline-marker {
    position: absolute;
    left: -21px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #007bff;
    border: 2px solid #fff;
    z-index: 2;
}

.timeline-content {
    padding: 0 0 0 10px;
}

.timeline-title {
    margin-top: 0;
}

.timeline-date {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Rating stars */
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

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
