<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check if user is logged in and is admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Redirect to login page
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$reports_count = 0;
$reports_by_status = ['Submitted' => 0, 'Under Review' => 0, 'In Investigation' => 0, 'Resolved' => 0];
$reports_by_category = ['Phishing' => 0, 'Hacking' => 0, 'Fraud' => 0, 'Other' => 0];
$recent_reports = [];
$user_count = 0;
$admin_logs = [];

// Get reports statistics
$conn = get_database_connection();

// Get total reports count
$result = $conn->query("SELECT COUNT(*) as total FROM reports");
$row = $result->fetch_assoc();
$reports_count = $row['total'];

// Get reports by status
$result = $conn->query("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $reports_by_status[$row['status']] = $row['count'];
}

// Initialize all categories with 0
$reports_by_category = [
    'Phishing' => 0,
    'Hacking' => 0,
    'Fraud' => 0,
    'Other' => 0
];

// Get reports by category
$result = $conn->query("SELECT category, COUNT(*) as count FROM reports GROUP BY category");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $category = ucfirst(strtolower(trim($row['category'])));
        // Map to our standard categories
        if (array_key_exists($category, $reports_by_category)) {
            $reports_by_category[$category] = (int)$row['count'];
        } else {
            // If category doesn't match, add to 'Other'
            $reports_by_category['Other'] += (int)$row['count'];
        }
    }
}

// Get reports by region
$reports_by_region = [];
$stmt = $conn->prepare("SELECT r.name as region_name, COUNT(rep.report_id) as count 
                       FROM regions r 
                       LEFT JOIN reports rep ON r.id = rep.region_id 
                       GROUP BY r.id, r.name 
                       ORDER BY count DESC LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reports_by_region[] = $row;
}

// Get reports by district
$reports_by_district = [];
$stmt = $conn->prepare("SELECT d.name as district_name, COUNT(rep.report_id) as count 
                       FROM districts d 
                       LEFT JOIN reports rep ON d.id = rep.district_id 
                       GROUP BY d.id, d.name 
                       ORDER BY count DESC LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reports_by_district[] = $row;
}

// Get recent reports
$result = $conn->query("SELECT r.*, u.username FROM reports r 
                        JOIN users u ON r.user_id = u.user_id 
                        ORDER BY r.created_at DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    $recent_reports[] = $row;
}

// Get user count
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$row = $result->fetch_assoc();
$user_count = $row['total'];

// Get recent admin logs
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT l.*, u.username 
                       FROM admin_logs l 
                       JOIN users u ON l.admin_id = u.user_id 
                       ORDER BY l.timestamp DESC LIMIT 10");
$stmt->execute();
$logs_result = $stmt->get_result();

while ($log = $logs_result->fetch_assoc()) {
    $admin_logs[] = $log;
}

$stmt->close();
$conn->close();

// Include header
include dirname(__DIR__) . '/includes/header.php'; 
?>

<!-- Main Content -->
<main class="py-4 px-4">
    <div class="container">
        <div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active">Admin Dashboard</li>
            </ol>
        </nav>
        
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Admin Dashboard</h1>
            <div>
                <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="btn btn-primary me-2">
                    <i class="fas fa-file-alt me-2"></i>Manage Reports
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-users me-2"></i>Manage Users
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/feedback.php" class="btn btn-outline-success me-2">
                    <i class="fas fa-star me-2"></i>Manage Feedback
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/knowledge_cms.php" class="btn btn-outline-info">
                    <i class="fas fa-book me-2"></i>Knowledge Base
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
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $reports_count; ?></div>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolved Reports</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $reports_by_status['Resolved']; ?></div>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pending Reports</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $reports_by_status['Submitted']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Registered Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
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
                <h6 class="m-0 font-weight-bold text-primary">Reports by Category</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Export Options:</div>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=analytics&section=category&type=csv" target="_blank">
                            <i class="fas fa-file-csv text-success"></i> Category Analytics CSV
                        </a>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=analytics&section=category&type=pdf" target="_blank">
                            <i class="fas fa-file-pdf text-danger"></i> Category Analytics PDF
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="reportsCategoryChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span class="me-2">
                        <i class="fas fa-circle text-primary"></i> Phishing
                    </span>
                    <span class="me-2">
                        <i class="fas fa-circle text-success"></i> Hacking
                    </span>
                    <span class="me-2">
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
                <h6 class="m-0 font-weight-bold text-primary">Reports by Status</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Export Options:</div>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=reports&type=csv" target="_blank">
                            <i class="fas fa-file-csv text-success"></i> Detailed CSV
                        </a>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=reports&type=pdf" target="_blank">
                            <i class="fas fa-file-pdf text-danger"></i> Detailed PDF
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=analytics&section=status&type=csv" target="_blank">
                            <i class="fas fa-chart-bar text-primary"></i> Analytics CSV
                        </a>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=analytics&section=status&type=pdf" target="_blank">
                            <i class="fas fa-chart-bar text-warning"></i> Analytics PDF
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="reportsStatusChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span class="me-2">
                        <i class="fas fa-circle text-secondary"></i> Submitted
                    </span>
                    <span class="me-2">
                        <i class="fas fa-circle text-info"></i> Under Review
                    </span>
                    <span class="me-2">
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

<!-- Regional and District Charts Row -->
<div class="row">
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Reports by Region</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Export Options:</div>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=regional_summary&type=csv" target="_blank">
                            <i class="fas fa-file-csv text-success"></i> Regional CSV
                        </a>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=regional_summary&type=pdf" target="_blank">
                            <i class="fas fa-file-pdf text-danger"></i> Regional PDF
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="reportsRegionChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <?php foreach ($reports_by_region as $index => $region): ?>
                        <?php if ($index < 4): ?>
                            <span class="me-2">
                                <i class="fas fa-circle" style="color: <?php echo ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'][$index % 4]; ?>"></i>
                                <?php echo htmlspecialchars($region['region_name']); ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($reports_by_region) > 4): ?>
                        <span>+<?php echo count($reports_by_region) - 4; ?> more</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Reports by District (Top 10)</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Export Options:</div>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=district_summary&type=csv" target="_blank">
                            <i class="fas fa-file-csv text-success"></i> District CSV
                        </a>
                        <a class="dropdown-item" href="enhanced_export.php?export=1&scope=district_summary&type=pdf" target="_blank">
                            <i class="fas fa-file-pdf text-danger"></i> District PDF
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="reportsDistrictChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <?php foreach ($reports_by_district as $index => $district): ?>
                        <?php if ($index < 4): ?>
                            <span class="me-2">
                                <i class="fas fa-circle" style="color: <?php echo ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'][$index % 4]; ?>"></i>
                                <?php echo htmlspecialchars($district['district_name']); ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($reports_by_district) > 4): ?>
                        <span>+<?php echo count($reports_by_district) - 4; ?> more</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Reports and Admin Logs -->
<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Reports</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID/Code</th>
                                <th>Title</th>
<th>Description</th>
                                <th>User</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_reports as $report): ?>
                                <tr>
                                    <td>
                                        <small class="d-block text-muted">#<?php echo $report['report_id']; ?></small>
                                        <small class="font-weight-bold"><?php echo $report['tracking_code']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($report['title']); ?></td>
<td>
    <?php
        $desc = strip_tags($report['description']);
        if (mb_strlen($desc) > 80) {
            echo htmlspecialchars(mb_substr($desc, 0, 80)) . '...';
        } else {
            echo htmlspecialchars($desc);
        }
    ?>
</td>
                                    <td><?php echo htmlspecialchars($report['username']); ?></td>
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
                                    <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/admin/view_report.php?id=<?php echo $report['report_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="btn btn-outline-primary btn-sm">
                        View All Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Admin Activities</h6>
            </div>
            <div class="card-body">
                <div class="admin-logs-container">
                    <?php foreach ($admin_logs as $log): ?>
                        <div class="admin-log-item d-flex align-items-start mb-3 pb-3 border-bottom">
                            <div class="me-3">
                                <div class="icon-circle bg-primary text-white">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500"><?php echo format_date($log['timestamp']); ?></div>
                                <span class="font-weight-bold"><?php echo htmlspecialchars($log['username']); ?></span>
                                <div><?php echo htmlspecialchars($log['action']); ?></div>
                                <?php if ($log['report_id']): ?>
                                    <a href="<?php echo SITE_URL; ?>/admin/view_report.php?id=<?php echo $log['report_id']; ?>" class="small">
                                        View Report #<?php echo $log['report_id']; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo SITE_URL; ?>/admin/logs.php" class="btn btn-outline-primary btn-sm">
                        View All Activities
                    </a>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-3">
                        <a href="<?php echo SITE_URL; ?>/admin/reports.php?status=Submitted" class="btn btn-block btn-outline-secondary w-100">
                            <i class="fas fa-clock me-1"></i> Pending Reports
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="<?php echo SITE_URL; ?>/admin/knowledge_cms.php" class="btn btn-block btn-outline-info w-100">
                            <i class="fas fa-book me-1"></i> Knowledge Base
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-block btn-outline-success w-100">
                            <i class="fas fa-users me-1"></i> Manage Users
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="<?php echo SITE_URL; ?>/admin/analytics.php" class="btn btn-block btn-outline-primary w-100">
                            <i class="fas fa-chart-bar me-1"></i> Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
</main>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }

.admin-logs-container {
    max-height: 400px;
    overflow-y: auto;
}

.icon-circle {
    height: 2.5rem;
    width: 2.5rem;
    border-radius: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.main-container {
    padding: 20px;
}
</style>



<script>
// Chart.js code for the dashboard charts
document.addEventListener('DOMContentLoaded', function() {
    // Category Chart
    var ctxCategory = document.getElementById('reportsCategoryChart');
    var categoryChart = new Chart(ctxCategory, {
        type: 'doughnut',
        data: {
            labels: ['Phishing', 'Hacking', 'Fraud', 'Other'],
            datasets: [{
                data: [
                    <?php echo $reports_by_category['Phishing']; ?>, 
                    <?php echo $reports_by_category['Hacking']; ?>, 
                    <?php echo $reports_by_category['Fraud']; ?>, 
                    <?php echo $reports_by_category['Other']; ?>
                ],
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#858796'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#6e707e'],
                hoverBorderColor: 'rgba(234, 236, 244, 1)',
            }],
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                backgroundColor: 'rgb(255,255,255)',
                bodyFontColor: '#858796',
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                caretPadding: 10,
            },
            legend: {
                display: false
            },
            cutoutPercentage: 80,
        },
    });

    // Status Chart
    var ctxStatus = document.getElementById('reportsStatusChart');
    var statusChart = new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['Submitted', 'Under Review', 'In Investigation', 'Resolved'],
            datasets: [{
                data: [
                    <?php echo $reports_by_status['Submitted']; ?>, 
                    <?php echo $reports_by_status['Under Review']; ?>, 
                    <?php echo $reports_by_status['In Investigation']; ?>, 
                    <?php echo $reports_by_status['Resolved']; ?>
                ],
                backgroundColor: ['#858796', '#36b9cc', '#4e73df', '#1cc88a'],
                hoverBackgroundColor: ['#6e707e', '#2c9faf', '#2e59d9', '#17a673'],
                hoverBorderColor: 'rgba(234, 236, 244, 1)',
            }],
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                backgroundColor: 'rgb(255,255,255)',
                bodyFontColor: '#858796',
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                caretPadding: 10,
            },
            legend: {
                display: false
            },
            cutoutPercentage: 80,
        },
    });

    // Region Chart
    var ctxRegion = document.getElementById('reportsRegionChart');
    var regionChart = new Chart(ctxRegion, {
        type: 'doughnut',
        data: {
            labels: [
                <?php 
                $labels = [];
                foreach ($reports_by_region as $region) {
                    $labels[] = "'" . addslashes($region['region_name']) . "'";
                }
                if (empty($labels)) {
                    $labels[] = "'No Data'";
                }
                echo implode(',', $labels);
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    $data = [];
                    foreach ($reports_by_region as $region) {
                        $data[] = (int)$region['count'];
                    }
                    if (empty($data)) {
                        $data[] = 1;
                    }
                    echo implode(',', $data);
                    ?>
                ],
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6f42c1',
                    '#fd7e14', '#20c9a6', '#858796', '#5a5c69', '#1f77b4', '#ff7f0e',
                    '#2ca02c', '#d62728', '#9467bd', '#8c564b'
                ],
                hoverBackgroundColor: [
                    '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617', '#59359a',
                    '#be7c10', '#169d7f', '#6e707e', '#484a54', '#1862a1', '#e66a00',
                    '#208020', '#b01c1c', '#7d4a9c', '#704a3a'
                ],
                hoverBorderColor: 'rgba(234, 236, 244, 1)',
            }],
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                backgroundColor: 'rgb(255,255,255)',
                bodyFontColor: '#858796',
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                caretPadding: 10,
            },
            legend: {
                display: false
            },
            cutoutPercentage: 80,
        },
    });

    // District Chart
    var ctxDistrict = document.getElementById('reportsDistrictChart');
    var districtChart = new Chart(ctxDistrict, {
        type: 'doughnut',
        data: {
            labels: [
                <?php 
                $labels = [];
                foreach ($reports_by_district as $district) {
                    $labels[] = "'" . addslashes($district['district_name']) . "'";
                }
                if (empty($labels)) {
                    $labels[] = "'No Data'";
                }
                echo implode(',', $labels);
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    $data = [];
                    foreach ($reports_by_district as $district) {
                        $data[] = (int)$district['count'];
                    }
                    if (empty($data)) {
                        $data[] = 1;
                    }
                    echo implode(',', $data);
                    ?>
                ],
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6f42c1',
                    '#fd7e14', '#20c9a6', '#858796', '#5a5c69', '#1f77b4', '#ff7f0e',
                    '#2ca02c', '#d62728', '#9467bd', '#8c564b'
                ],
                hoverBackgroundColor: [
                    '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617', '#59359a',
                    '#be7c10', '#169d7f', '#6e707e', '#484a54', '#1862a1', '#e66a00',
                    '#208020', '#b01c1c', '#7d4a9c', '#704a3a'
                ],
                hoverBorderColor: 'rgba(234, 236, 244, 1)',
            }],
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                backgroundColor: 'rgb(255,255,255)',
                bodyFontColor: '#858796',
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                caretPadding: 10,
            },
            legend: {
                display: false
            },
            cutoutPercentage: 80,
        },
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
