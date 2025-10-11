<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    // Redirect to login page
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$reports = [];
$notifications = [];

// Get user reports
$conn = get_database_connection();

$stmt = $conn->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reports_result = $stmt->get_result();

while ($report = $reports_result->fetch_assoc()) {
    $reports[] = $report;
}

// Get recent notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY sent_timestamp DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications_result = $stmt->get_result();

while ($notification = $notifications_result->fetch_assoc()) {
    $notifications[] = $notification;
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

$stmt->close();
$conn->close();

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Main Content -->
<main class="py-4">
    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                        <li class="breadcrumb-item active">User Dashboard</li>
                    </ol>
                </nav>
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Welcome, <?php echo htmlspecialchars($user['username']); ?></h1>
                    <div>
                        <a href="<?php echo SITE_URL; ?>/reports/submit.php" class="btn btn-primary">
                            <i class="fas fa-file-alt me-2"></i>Submit New Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

<!-- Status Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Reports</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($reports); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolved</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            $resolved_count = 0;
                            foreach ($reports as $report) {
                                if ($report['status'] == 'Resolved') $resolved_count++;
                            }
                            echo $resolved_count;
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">In Progress</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            $in_progress_count = 0;
                            foreach ($reports as $report) {
                                if ($report['status'] == 'Under Review' || $report['status'] == 'In Investigation') $in_progress_count++;
                            }
                            echo $in_progress_count;
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-spinner fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            $pending_count = 0;
                            foreach ($reports as $report) {
                                if ($report['status'] == 'Submitted') $pending_count++;
                            }
                            echo $pending_count;
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Your Reports by Category</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Export Options:</div>
                        <a class="dropdown-item" href="#">Export as CSV</a>
                        <a class="dropdown-item" href="#">Export as PDF</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="userReportsCategoryChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span>
                        <i class="fas fa-circle text-primary"></i> Phishing
                    </span>
                    <span class="mx-2">
                        <i class="fas fa-circle text-success"></i> Hacking
                    </span>
                    <span class="mx-2">
                        <i class="fas fa-circle text-info"></i> Fraud
                    </span>
                    <span>
                        <i class="fas fa-circle text-secondary"></i> Other
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Your Reports by Status</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Export Options:</div>
                        <a class="dropdown-item" href="#">Export as CSV</a>
                        <a class="dropdown-item" href="#">Export as PDF</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="userReportsStatusChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span>
                        <i class="fas fa-circle text-secondary"></i> Submitted
                    </span>
                    <span class="mx-2">
                        <i class="fas fa-circle text-info"></i> Under Review
                    </span>
                    <span class="mx-2">
                        <i class="fas fa-circle text-primary"></i> In Investigation
                    </span>
                    <span>
                        <i class="fas fa-circle text-success"></i> Resolved
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Your Reports</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Filter by:</div>
                        <a class="dropdown-item" href="#" data-filter="all">All Reports</a>
                        <a class="dropdown-item" href="#" data-filter="Submitted">Pending</a>
                        <a class="dropdown-item" href="#" data-filter="progress">In Progress</a>
                        <a class="dropdown-item" href="#" data-filter="Resolved">Resolved</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($reports) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="reports-table">
                            <thead>
                                <tr>
                                    <th>Tracking Code</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <tr data-status="<?php echo $report['status']; ?>">
                                        <td>
                                            <span class="font-weight-bold"><?php echo $report['tracking_code']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($report['title']); ?></td>
                                        <td>
                                            <span class="badge rounded-pill bg-<?php 
                                                if ($report['category'] == 'Phishing') echo 'warning';
                                                elseif ($report['category'] == 'Hacking') echo 'danger';
                                                elseif ($report['category'] == 'Fraud') echo 'info';
                                                else echo 'secondary';
                                            ?>">
                                                <?php echo $report['category']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill bg-<?php 
                                                if ($report['status'] == 'Submitted') echo 'secondary';
                                                elseif ($report['status'] == 'Under Review') echo 'info';
                                                elseif ($report['status'] == 'In Investigation') echo 'primary';
                                                elseif ($report['status'] == 'Resolved') echo 'success';
                                            ?>">
                                                <?php echo $report['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_date($report['created_at']); ?></td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/reports/track.php?code=<?php echo $report['tracking_code']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-folder-open fa-4x text-gray-300 mb-3"></i>
                        <p>You haven't submitted any reports yet.</p>
                        <a href="<?php echo SITE_URL; ?>/reports/submit.php" class="btn btn-primary">
                            <i class="fas fa-file-alt me-2"></i>Submit Your First Report
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Notifications</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownNotificationsLink" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownNotificationsLink">
                        <a class="dropdown-item" href="#"><i class="fas fa-check fa-sm fa-fw mr-2 text-gray-400"></i> Mark all as read</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#"><i class="fas fa-cog fa-sm fa-fw mr-2 text-gray-400"></i> Notification settings</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)) : ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bell fa-3x text-gray-300 mb-3"></i>
                        <p class="text-gray-600">No notifications at this time.</p>
                    </div>
                <?php else : ?>
                    <div class="list-group">
                        <?php foreach ($notifications as $notification) : ?>
                            <div class="list-group-item list-group-item-action border-left-primary py-3">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1 font-weight-bold"><?php echo htmlspecialchars($notification['notification_type']); ?> Notification</h6>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($notification['sent_timestamp'])); ?></small>
                                </div>
                                <p class="mb-1 text-gray-800"><?php echo htmlspecialchars($notification['message_content']); ?></p>
                                <span class="badge bg-primary">New</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="btn btn-sm btn-primary">View All Notifications</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Account Information</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="font-weight-bold">Username:</span> <?php echo htmlspecialchars($user['username']); ?>
                </div>
                <div class="mb-3">
                    <span class="font-weight-bold">Email:</span> <?php echo htmlspecialchars($user['email']); ?>
                </div>
                <div class="mb-3">
                    <span class="font-weight-bold">Phone:</span> <?php echo htmlspecialchars($user['phone']); ?>
                </div>
                <div class="mb-3">
                    <span class="font-weight-bold">Member Since:</span> <?php echo format_date($user['registered_at']); ?>
                </div>
                <div class="d-grid gap-2">
                    <a href="<?php echo SITE_URL; ?>/user/profile.php" class="btn btn-outline-primary">
                        <i class="fas fa-user-edit me-2"></i>Edit Profile
                    </a>
                    <a href="<?php echo SITE_URL; ?>/user/change_password.php" class="btn btn-outline-secondary">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Report filtering
document.addEventListener('DOMContentLoaded', function() {
    const filterLinks = document.querySelectorAll('[data-filter]');
    const reportRows = document.querySelectorAll('#reports-table tbody tr');
    
    filterLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const filter = this.getAttribute('data-filter');
            
            reportRows.forEach(row => {
                const status = row.getAttribute('data-status');
                
                if (filter === 'all') {
                    row.style.display = '';
                } else if (filter === 'progress' && (status === 'Under Review' || status === 'In Investigation')) {
                    row.style.display = '';
                } else if (status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
});
</script>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item:last-child {
    border-bottom: none !important;
}
</style>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script>
// Process report data for charts
document.addEventListener('DOMContentLoaded', function() {
    // Prepare data for category chart
    const categoryData = {
        labels: [],
        counts: [],
        colors: ['#4e73df', '#1cc88a', '#36b9cc', '#858796']
    };
    
    // Prepare data for status chart
    const statusData = {
        labels: ['Submitted', 'Under Review', 'In Investigation', 'Resolved'],
        counts: [<?php echo $pending_count; ?>, 
                <?php 
                $under_review_count = 0;
                foreach ($reports as $report) {
                    if ($report['status'] == 'Under Review') $under_review_count++;
                }
                echo $under_review_count;
                ?>, 
                <?php 
                $in_investigation_count = 0;
                foreach ($reports as $report) {
                    if ($report['status'] == 'In Investigation') $in_investigation_count++;
                }
                echo $in_investigation_count;
                ?>, 
                <?php echo $resolved_count; ?>],
        colors: ['#858796', '#36b9cc', '#4e73df', '#1cc88a']
    };
    
    // Count reports by category
    <?php
    $categories = [];
    $category_counts = [];
    
    foreach ($reports as $report) {
        if (!in_array($report['category'], $categories)) {
            $categories[] = $report['category'];
            $category_counts[$report['category']] = 1;
        } else {
            $category_counts[$report['category']]++;
        }
    }
    ?>
    
    // Add PHP data to JavaScript
    <?php foreach ($categories as $index => $category): ?>
        categoryData.labels.push('<?php echo $category; ?>');
        categoryData.counts.push(<?php echo $category_counts[$category]; ?>);
    <?php endforeach; ?>
    
    // If no categories, add placeholder
    if (categoryData.labels.length === 0) {
        categoryData.labels = ['No Data'];
        categoryData.counts = [1];
        categoryData.colors = ['#858796'];
    }
    
    // Create category chart
    const ctxCategory = document.getElementById('userReportsCategoryChart');
    if (ctxCategory) {
        new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
                labels: categoryData.labels,
                datasets: [{
                    data: categoryData.counts,
                    backgroundColor: categoryData.colors.slice(0, categoryData.labels.length),
                    hoverBackgroundColor: categoryData.colors.slice(0, categoryData.labels.length),
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        titleColor: '#6e707e',
                        titleMarginBottom: 10,
                        displayColors: false
                    }
                },
                cutout: '70%',
            },
        });
    }
    
    // Create status chart
    const ctxStatus = document.getElementById('userReportsStatusChart');
    if (ctxStatus) {
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.counts,
                    backgroundColor: statusData.colors,
                    hoverBackgroundColor: statusData.colors,
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        titleColor: '#6e707e',
                        titleMarginBottom: 10,
                        displayColors: false
                    }
                },
                cutout: '70%',
            },
        });
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
