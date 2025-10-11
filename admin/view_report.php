<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/feedback_system.php';

// Check if user is logged in and is admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Redirect to login page
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$report_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$report = null;
$user = null;
$attachments = [];
$error_message = '';
$success_message = '';

// Check if report exists
if ($report_id > 0) {
    $conn = get_database_connection();
    
    // Get report details
    $stmt = $conn->prepare("SELECT r.*, u.username, u.email, u.phone 
                          FROM reports r 
                          JOIN users u ON r.user_id = u.user_id 
                          WHERE r.report_id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $report = $result->fetch_assoc();
        
        // Get attachments
        $stmt = $conn->prepare("SELECT * FROM attachments WHERE report_id = ? ORDER BY uploaded_at DESC");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $attachments_result = $stmt->get_result();
        
        while ($attachment = $attachments_result->fetch_assoc()) {
            $attachments[] = $attachment;
        }
        
        // Process status update form
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] == 'update_status' && isset($_POST['status'])) {
                $new_status = sanitize_input($_POST['status']);
                
                $updated_at = date('Y-m-d H:i:s');
$user_notes = isset($_POST['user_notes']) ? sanitize_input($_POST['user_notes']) : '';
                
                // Update report status
                $stmt = $conn->prepare("UPDATE reports SET status = ?, updated_at = ? WHERE report_id = ?");
                $stmt->bind_param("ssi", $new_status, $updated_at, $report_id);
                
                if ($stmt->execute()) {
                    // Log admin action
                    $action = "Updated report status to '$new_status'";
                    log_admin_action($admin_id, $action, $report_id, $user_notes);
                    
                    // Send notification to user
                    $message = "Your cybercrime report (Tracking Code: {$report['tracking_code']}) status has been updated to '$new_status'.";
                    if (!empty($user_notes)) {
                        $message .= "\n\nAdditional notes: $user_notes";
                    }
                    
                    // If status is resolved, generate and send feedback link
                    if ($new_status === 'Resolved') {
                        try {
                            $feedbackSystem = getFeedbackSystem();
                            $feedback_token = $feedbackSystem->generateFeedbackToken($report_id, $report['user_id']);
                            if ($feedback_token) {
                                // Use URLHelper to generate a robust feedback URL
                                if (class_exists('URLHelper')) {
                                    $feedback_url = URLHelper::generateFeedbackURL($feedback_token['token']);
                                } else {
                                    $feedback_url = SITE_URL . "/feedback/?token=" . $feedback_token['token'];
                                }
                                $email_sent = $feedbackSystem->sendFeedbackEmail($report_id, $report['email'], $feedback_url);
                                if ($email_sent) {
                                    $success_message = "Report status updated to '$new_status', notification sent to user, and feedback request email sent.";
                                } else {
                                    $success_message = "Report status updated to '$new_status' and notification sent to user. Note: Feedback email sending failed - please check email configuration or contact system administrator.";
                                    // Log the feedback URL for manual sending if needed
                                    error_log("Feedback email failed for report {$report_id}. Feedback URL: $feedback_url");
                                }
                            } else {
                                $success_message = "Report status updated to '$new_status' and notification sent to user. Note: Feedback link generation failed.";
                            }
                        } catch (Exception $e) {
                            error_log("Feedback system error: " . $e->getMessage());
                            $success_message = "Report status updated to '$new_status' and notification sent to user. Note: Feedback email could not be sent.";
                        }
                    } else {
                        $success_message = "Report status updated to '$new_status' and notification sent to user.";
                    }
                    
                    create_notification($report_id, $report['user_id'], 'Email', $message);
                    
                    // Refresh report data
                    $stmt = $conn->prepare("SELECT r.*, u.username, u.email, u.phone 
                                          FROM reports r 
                                          JOIN users u ON r.user_id = u.user_id 
                                          WHERE r.report_id = ?");
                    $stmt->bind_param("i", $report_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $report = $result->fetch_assoc();
                } else {
                    $error_message = "Failed to update report status.";
                }
            }
        }
    } else {
        $error_message = "Report not found.";
    }
    
    $stmt->close();
    $conn->close();
} else {
    $error_message = "Invalid report ID.";
}

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Main Content -->
<main class="m-4 px-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Report Details</h1>
            <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Reports
            </a>
        </div>
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/reports.php">Reports</a></li>
                <li class="breadcrumb-item active">View Report #<?php echo $report_id; ?></li>
            </ol>
        </nav>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Reports
                </a>
            </div>
        <?php elseif ($report): ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <div class="card shadow-sm border-0 report-detail-card">
                <div class="card-header d-flex justify-content-between align-items-center py-3">
                    <div>
                        <h4 class="m-0 font-weight-bold text-primary d-flex align-items-center">
                            <i class="fas fa-file-alt me-2"></i>
                            <span>Report #<?php echo $report['report_id']; ?></span>
                            <span class="badge bg-primary-soft text-primary ms-2 align-middle"><?php echo $report['tracking_code']; ?></span>
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $report['status'])); ?> ms-2">
                                <?php echo $report['status']; ?>
                            </span>
                        </h4>
                        <div class="mt-2">
                            <span class="badge bg-light text-dark">
                                <i class="far fa-calendar-alt me-1"></i>
                                <?php echo date('F j, Y \a\t g:i A', strtotime($report['created_at'])); ?>
                            </span>
                            <?php if ($report['created_at'] != $report['updated_at']): ?>
                                <span class="badge bg-light text-dark ms-1">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Updated: <?php echo date('M j, Y', strtotime($report['updated_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                            <i class="fas fa-edit me-1"></i>Update Status
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header bg-light py-2">
                                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Report Information</h5>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <th width="30%">Title</th>
                                            <td><?php echo htmlspecialchars($report['title']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Category</th>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    if ($report['category'] == 'Phishing') echo 'warning';
                                                    elseif ($report['category'] == 'Hacking') echo 'danger';
                                                    elseif ($report['category'] == 'Fraud') echo 'info';
                                                    else echo 'secondary';
                                                ?>">
                                                    <?php echo $report['category']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Incident Date</th>
                                            <td><?php echo format_date($report['incident_date']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Submitted</th>
                                            <td><?php echo format_date($report['created_at']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Last Updated</th>
                                            <td><?php echo format_date($report['updated_at']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <!-- Description and Details -->
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header bg-light py-2">
                                    <h5 class="mb-0"><i class="fas fa-align-left me-2"></i>Description & Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h6 class="text-uppercase text-muted small mb-2">Description</h6>
                                        <div class="p-3 bg-light rounded">
                                            <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($report['location'])): ?>
                                    <div class="mb-3">
                                        <h6 class="text-uppercase text-muted small mb-2">Location</h6>
                                        <p class="mb-0"><?php echo htmlspecialchars($report['location']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($report['incident_date'])): ?>
                                    <div class="mb-3">
                                        <h6 class="text-uppercase text-muted small mb-2">Incident Date</h6>
                                        <p class="mb-0">
                                            <i class="far fa-calendar me-2 text-primary"></i>
                                            <?php echo date('F j, Y', strtotime($report['incident_date'])); ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($report['suspect_information'])): ?>
                                    <div class="mb-3">
                                        <h6 class="text-uppercase text-muted small mb-2">Suspect Information</h6>
                                        <div class="p-3 bg-light rounded">
                                            <?php echo nl2br(htmlspecialchars($report['suspect_information'])); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($report['additional_notes'])): ?>
                                    <div class="mb-3">
                                        <h6 class="text-uppercase text-muted small mb-2">Additional Notes</h6>
                                        <div class="p-3 bg-light rounded">
                                            <?php echo nl2br(htmlspecialchars($report['additional_notes'])); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Timeline -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white py-3 d-flex align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-history text-primary me-2"></i>Status Update Timeline</h5>
                                </div>
                                <div class="card-body">
                                    <?php
// Status Timeline Logic - Moved from partial
$timeline_report_id = $report['report_id'];

$conn = get_database_connection();
// Try to fetch admin_notes and user_notes if the columns exist
$user_notes_exists = false;
$columns_result = $conn->query("SHOW COLUMNS FROM admin_logs LIKE 'user_notes'");
if ($columns_result && $columns_result->num_rows > 0) {
    $user_notes_exists = true;
}

// Build the query based on available columns
if ($user_notes_exists) {
    $stmt = $conn->prepare("SELECT l.action, l.timestamp, u.username, l.user_notes FROM admin_logs l 
JOIN users u ON l.admin_id = u.user_id 
WHERE l.report_id = ? AND l.action LIKE 'Updated report status to%' 
ORDER BY l.timestamp ASC");
} else {
    $stmt = $conn->prepare("SELECT l.action, l.timestamp, u.username FROM admin_logs l 
JOIN users u ON l.admin_id = u.user_id 
WHERE l.report_id = ? AND l.action LIKE 'Updated report status to%' 
ORDER BY l.timestamp ASC");
}

$stmt->bind_param("i", $timeline_report_id);
$stmt->execute();
$result = $stmt->get_result();
$status_updates = [];

while ($row = $result->fetch_assoc()) {
    // Extract status from action string
    if (preg_match("/Updated report status to '([^']+)'/", $row['action'], $matches)) {
        $row['status'] = $matches[1];
        $status_updates[] = $row;
    }
}

$stmt->close();

if (empty($status_updates)) {
    echo '<div class="text-muted">No status updates found for this report.</div>';
} else {
    ?>
    <table class="table table-bordered status-timeline-table">
        <thead class="table-light">
            <tr>
                <th style="width: 20%">Date & Time</th>
                <th style="width: 20%">Status</th>
                <th style="width: 60%">Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($status_updates as $update): ?>
            <tr>
                <td>
                    <i class="fas fa-clock me-1 text-secondary"></i>
                    <?php echo date('M j, Y H:i', strtotime($update['timestamp'])); ?>
                </td>
                <td>
                    <div>
                        <span class="badge bg-<?php echo strtolower(str_replace(' ', '-', $update['status'])) == 'submitted' ? 'warning' : 
                            (strtolower(str_replace(' ', '-', $update['status'])) == 'under-review' ? 'info' : 
                            (strtolower(str_replace(' ', '-', $update['status'])) == 'in-investigation' ? 'primary' : 
                            (strtolower(str_replace(' ', '-', $update['status'])) == 'resolved' ? 'success' : 'secondary'))); ?> text-white">
                            <?php echo htmlspecialchars($update['status']); ?>
                        </span>
                    </div>
                    <small class="text-muted">
                        by <?php echo htmlspecialchars($update['username']); ?>
                    </small>
                </td>
                <td>
                    <?php if (isset($update['user_notes']) && !empty(trim($update['user_notes']))): ?>
                    <div class="p-2 border-start border-warning border-3">
                        <i class="fas fa-sticky-note text-warning me-1"></i>
                        <?php echo nl2br(htmlspecialchars($update['user_notes'])); ?>
                    </div>
                    <?php else: ?>
                    <span class="text-muted fst-italic">No additional notes</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (count($attachments) > 0): ?>
                    <!-- Attachments -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-paperclip text-primary me-2"></i>
                                Attachments
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill ms-2">
                                    <?php echo count($attachments); ?>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($attachments) > 0): ?>
                                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                                    <?php foreach ($attachments as $attachment): ?>
                                        <div class="col">
                                            <div class="attachment-card h-100">
                                                <div class="card-body p-3">
                                                    <div class="d-flex align-items-start">
                                                        <?php
                                                        $file_extension = strtolower(pathinfo($attachment['file_path'], PATHINFO_EXTENSION));
                                                        $image_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                                                        $document_extensions = ['pdf', 'doc', 'docx', 'txt'];
                                                        
                                                        if (in_array($file_extension, $image_extensions)) {
                                                            $icon_class = 'fa-file-image text-primary';
                                                        } elseif (in_array($file_extension, $document_extensions)) {
                                                            $icon_class = 'fa-file-alt text-info';
                                                        } else {
                                                            $icon_class = 'fa-file text-secondary';
                                                        }
                                                        ?>
                                                        <div class="me-3">
                                                            <i class="fas <?php echo $icon_class; ?> fa-2x"></i>
                                                        </div>
                                                        <div class="flex-grow-1 overflow-hidden">
                                                            <h6 class="card-title text-truncate mb-1" title="<?php echo htmlspecialchars($attachment['original_filename']); ?>">
                                                                <?php echo htmlspecialchars($attachment['original_filename']); ?>
                                                            </h6>
                                                            <p class="text-muted small mb-2">
                                                                <?php echo format_file_size($attachment['file_size']); ?>
                                                                <span class="mx-1">•</span>
                                                                <?php echo strtoupper($file_extension); ?>
                                                            </p>
                                                            <div class="d-flex gap-2">
                                                                <a href="<?php echo SITE_URL . str_replace('/cybercrime_hive', '', $attachment['file_path']); ?>" 
                                                                   class="btn btn-sm btn-outline-primary" 
                                                                   target="_blank"
                                                                   data-bs-toggle="tooltip"
                                                                   title="Download">
                                                                    <i class="fas fa-download"></i>
                                                                </a>
                                                                <?php if (in_array($file_extension, $image_extensions)): ?>
                                                                <a href="<?php echo SITE_URL . str_replace('/cybercrime_hive', '', $attachment['file_path']); ?>" 
                                                                   class="btn btn-sm btn-outline-secondary" 
                                                                   data-fancybox="gallery"
                                                                   data-bs-toggle="tooltip"
                                                                   title="Preview">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-paperclip fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">No attachments found for this report.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Reports
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                            <i class="fas fa-edit me-2"></i>Update Status
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Status Update Modal -->
            <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="" id="updateStatusForm">
                            <input type="hidden" name="action" value="update_status">
                            <div class="modal-header">
                                <h5 class="modal-title" id="updateStatusModalLabel">Update Report Status</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Submitted" <?php if ($report['status'] == 'Submitted') echo 'selected'; ?>>Submitted</option>
                                        <option value="Under Review" <?php if ($report['status'] == 'Under Review') echo 'selected'; ?>>Under Review</option>
                                        <option value="In Investigation" <?php if ($report['status'] == 'In Investigation') echo 'selected'; ?>>In Investigation</option>
                                        <option value="Resolved" <?php if ($report['status'] == 'Resolved') echo 'selected'; ?>>Resolved</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="user_notes" class="form-label">Notes (Optional)</label>
<textarea class="form-control" id="user_notes" name="user_notes" rows="4" placeholder="These notes will be saved and included in the notification to the user"></textarea>
                                </div>
                                <div class="bg-light border rounded p-2 mb-2">
    <small class="d-flex align-items-center text-muted">
        <i class="fas fa-info-circle me-2"></i>
        Updating the status will automatically send a notification to the user and log your action.
    </small>
</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Status</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/includes/footer.php'; ?>



<style>
.report-detail-card {
    border-left: 4px solid #4e73df;
    transition: all 0.3s ease;
}

.report-detail-card .card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}

.info-label {
    font-weight: 600;
    color: #5a5c69;
    min-width: 150px;
}

.info-value {
    color: #4e73df;
    word-break: break-word;
}

.attachment-card {
    border: 1px solid #e3e6f0;
    border-radius: 0.35rem;
    transition: all 0.2s ease-in-out;
}

.attachment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.status-badge {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.35em 0.65em;
    border-radius: 0.25rem;
}

/* Status badge styles - match the dynamic class names */
.status-submitted { background-color: #f6c23e; color: #000; }
.status-under-review { background-color: #36b9cc; color: #fff; }
.status-investigation { background-color: #4e73df; color: #fff; }
.status-resolved { background-color: #1cc88a; color: #000; }

/* Dynamic status classes based on status text */
.status-badge[class*='status-'] {
    white-space: nowrap;
    display: inline-block;
    text-transform: capitalize;
}

/* Ensure text is always visible */
.status-badge {
    color: #000 !important;
    text-shadow: 0 0 1px rgba(255,255,255,0.5);
}

.category-badge {
    font-size: 0.9rem;
    padding: 0.35em 0.65em;
    border-radius: 0.25rem;
    background-color: #f8f9fa;
    border: 1px solid #e3e6f0;
    color: #5a5c69;
}

/* Status Timeline Table Styling */
.status-timeline-table {
    border: 1px solid #e3e6f0;
    margin-bottom: 0;
}

.status-timeline-table th {
    font-weight: 600;
    color: #4e73df;
    background-color: #f8f9fc;
}

.status-timeline-table td {
    vertical-align: middle;
}

.status-timeline-table .border-warning {
    border-color: #f6c23e !important;
}

.status-timeline-table .badge {
    font-size: 0.8rem;
    padding: 0.35em 0.65em;
    text-transform: capitalize;
    white-space: nowrap;
}
</style>
