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
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Get analytic data
$conn = get_database_connection();

// Total reports count
$result = $conn->query("SELECT COUNT(*) as total FROM reports");
$total_reports = $result->fetch_assoc()['total'];

// Reports by status
$result = $conn->query("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
$reports_by_status = [];
while ($row = $result->fetch_assoc()) {
    $reports_by_status[$row['status']] = $row['count'];
}

// Reports by category
$result = $conn->query("SELECT category, COUNT(*) as count FROM reports GROUP BY category");
$reports_by_category = [];
while ($row = $result->fetch_assoc()) {
    $reports_by_category[$row['category']] = $row['count'];
}

// Monthly report counts for the past 12 months
$monthly_reports = [];
$result = $conn->query("SELECT 
                       DATE_FORMAT(created_at, '%Y-%m') as month,
                       COUNT(*) as count 
                       FROM reports 
                       WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) 
                       GROUP BY month 
                       ORDER BY month");
while ($row = $result->fetch_assoc()) {
    $monthly_reports[$row['month']] = $row['count'];
}

// Last 12 months as labels
$months_labels = [];
$months_data = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months_labels[] = date('M Y', strtotime("-$i months"));
    $months_data[] = isset($monthly_reports[$month]) ? $monthly_reports[$month] : 0;
}

// Average resolution time
$result = $conn->query("SELECT 
                       AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)) as avg_days 
                       FROM reports 
                       WHERE status = 'Resolved'");
$avg_resolution_days = $result->fetch_assoc()['avg_days'];
$avg_resolution_days = round($avg_resolution_days, 1);

// Resolve rate (Resolved reports / Total reports) as percentage
$resolved_rate = 0;
if ($total_reports > 0 && isset($reports_by_status['Resolved'])) {
    $resolved_rate = round(($reports_by_status['Resolved'] / $total_reports) * 100, 1);
}

// Recent reports by day of week
$result = $conn->query("SELECT 
                       DAYOFWEEK(created_at) as day_num, 
                       COUNT(*) as count 
                       FROM reports 
                       WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) 
                       GROUP BY day_num 
                       ORDER BY day_num");
$reports_by_day = array_fill(1, 7, 0); // Initialize all days to 0
while ($row = $result->fetch_assoc()) {
    $reports_by_day[$row['day_num']] = $row['count'];
}

// Day of week labels (Sunday=1, Saturday=7 in MySQL)
$day_labels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$day_data = array_values($reports_by_day);

// Users reporting multiple incidents
$result = $conn->query("SELECT 
                       COUNT(DISTINCT user_id) as repeat_users 
                       FROM reports 
                       GROUP BY user_id 
                       HAVING COUNT(*) > 1");
$repeat_reporters = $result->num_rows;

// Feedback Analytics Data
// Total feedback count
$result = $conn->query("SELECT COUNT(*) as total FROM feedback");
$total_feedback = $result->fetch_assoc()['total'];

// Average overall rating
$result = $conn->query("SELECT AVG(overall_rating) as avg_rating FROM feedback");
$avg_rating = round($result->fetch_assoc()['avg_rating'], 1);

// Feedback response rate
$result = $conn->query("SELECT COUNT(DISTINCT report_id) as resolved_reports FROM reports WHERE status = 'Resolved'");
$resolved_reports = $result->fetch_assoc()['resolved_reports'];
$feedback_response_rate = $resolved_reports > 0 ? round(($total_feedback / $resolved_reports) * 100, 1) : 0;

// Feedback ratings distribution
$result = $conn->query("SELECT overall_rating, COUNT(*) as count FROM feedback GROUP BY overall_rating ORDER BY overall_rating");
$ratings_distribution = [];
for ($i = 1; $i <= 5; $i++) {
    $ratings_distribution[$i] = 0;
}
while ($row = $result->fetch_assoc()) {
    $ratings_distribution[$row['overall_rating']] = $row['count'];
}

// Monthly feedback trends
$monthly_feedback = [];
$result = $conn->query("SELECT 
                       DATE_FORMAT(submitted_at, '%Y-%m') as month,
                       COUNT(*) as count,
                       AVG(overall_rating) as avg_rating
                       FROM feedback 
                       WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) 
                       GROUP BY month 
                       ORDER BY month");
while ($row = $result->fetch_assoc()) {
    $monthly_feedback[$row['month']] = [
        'count' => $row['count'],
        'avg_rating' => round($row['avg_rating'], 1)
    ];
}

// Feedback metrics for charts
$feedback_months_data = [];
$feedback_ratings_data = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $feedback_months_data[] = isset($monthly_feedback[$month]) ? $monthly_feedback[$month]['count'] : 0;
    $feedback_ratings_data[] = isset($monthly_feedback[$month]) ? $monthly_feedback[$month]['avg_rating'] : 0;
}

// Recent feedback comments
$result = $conn->query("SELECT f.*, r.tracking_code, r.title 
                       FROM feedback f 
                       JOIN reports r ON f.report_id = r.report_id 
                       WHERE f.comments IS NOT NULL AND f.comments != '' 
                       ORDER BY f.submitted_at DESC 
                       LIMIT 5");
$recent_feedback = [];
while ($row = $result->fetch_assoc()) {
    $recent_feedback[] = $row;
}

// Recommendation statistics
$result = $conn->query("SELECT would_recommend, COUNT(*) as count FROM feedback WHERE would_recommend IS NOT NULL GROUP BY would_recommend");
$recommendations = ['Yes' => 0, 'No' => 0, 'Maybe' => 0];
while ($row = $result->fetch_assoc()) {
    $recommendations[$row['would_recommend']] = $row['count'];
}

$conn->close();

// Log admin action
log_admin_action($admin_id, "Accessed analytics dashboard", null);

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/includes/header.php';
?>

<div class="row mb-4 px-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active">Analytics</li>
            </ol>
        </nav>
        
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Cybercrime Analytics</h1>
            <a href="<?php echo SITE_URL; ?>/admin/export_reports.php" class="btn btn-primary">
                <i class="fas fa-file-export me-2"></i>Export Data
            </a>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Reports</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_reports; ?></div>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolution Rate</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $resolved_rate; ?>%</div>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg. Resolution Time</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $avg_resolution_days; ?> days</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Repeat Reporters</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $repeat_reporters; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Feedback Analytics KPIs -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Feedback</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_feedback; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-star fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Average Rating</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $avg_rating ?: 'N/A'; ?>/5</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-thumbs-up fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Response Rate</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $feedback_response_rate; ?>%</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Would Recommend</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $recommendations['Yes']; ?> Yes</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-heart fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Charts -->
<div class="row">
    <!-- Monthly Reports Trend -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Monthly Report Trends</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Export Options:</div>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/export_reports.php">Export as CSV</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="monthlyReportsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports by Category -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Reports by Category</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">Filter View:</div>
                        <a class="category-filter dropdown-item" href="#" data-category="all">All Categories</a>
                        <div class="dropdown-divider"></div>
                        <a class="category-filter dropdown-item" href="#" data-category="Phishing">Phishing Only</a>
                        <a class="category-filter dropdown-item" href="#" data-category="Hacking">Hacking Only</a>
                        <a class="category-filter dropdown-item" href="#" data-category="Fraud">Fraud Only</a>
                        <a class="category-filter dropdown-item" href="#" data-category="Other">Other Only</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-pie mb-4">
                    <canvas id="reportsByCategoryChart"></canvas>
                </div>
                <div class="mt-4 text-center small category-legend">
                    <?php 
                    $colors = ['primary', 'success', 'info', 'secondary'];
                    $i = 0;
                    foreach ($reports_by_category as $category => $count) {
                        $color = $colors[$i % count($colors)];
                        echo "<span class='me-2'><i class='fas fa-circle text-$color'></i> $category</span>";
                        $i++;
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Analytics -->
<div class="row">
    <!-- Reports by Status -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Reports by Status</h6>
            </div>
            <div class="card-body">
                <?php
                $status_colors = [
                    'Submitted' => 'secondary',
                    'Under Review' => 'info',
                    'In Investigation' => 'primary',
                    'Resolved' => 'success'
                ];
                
                foreach ($status_colors as $status => $color) {
                    $count = isset($reports_by_status[$status]) ? $reports_by_status[$status] : 0;
                    $percentage = $total_reports > 0 ? ($count / $total_reports) * 100 : 0;
                ?>
                <h4 class="small font-weight-bold">
                    <?php echo $status; ?>
                    <span class="float-end"><?php echo $count; ?> (<?php echo round($percentage); ?>%)</span>
                </h4>
                <div class="progress mb-4">
                    <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                        aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    
    <!-- Reports by Day of Week -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Reports by Day of Week</h6>
            </div>
            <div class="card-body">
                <div class="chart-bar">
                    <canvas id="reportsByDayChart"></canvas>
                </div>
                <div class="mt-4 text-muted small">
                    <p class="mb-0">This chart shows when incidents are most frequently reported (not necessarily when they occurred).</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Feedback Analytics Charts -->
<div class="row">
    <!-- Feedback Ratings Distribution -->
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Feedback Ratings Distribution</h6>
            </div>
            <div class="card-body">
                <div class="chart-bar">
                    <canvas id="feedbackRatingsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Feedback Trends -->
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Monthly Feedback Trends</h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="monthlyFeedbackChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Feedback Comments -->
<?php if (!empty($recent_feedback)): ?>
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Feedback Comments</h6>
            </div>
            <div class="card-body">
                <?php foreach ($recent_feedback as $feedback): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">
                                Report: <?php echo htmlspecialchars($feedback['tracking_code']); ?>
                                <span class="badge badge-primary ml-2">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $feedback['overall_rating'] ? '★' : '☆';
                                    }
                                    ?>
                                </span>
                            </h6>
                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($feedback['title']); ?></p>
                            <p class="mb-0"><?php echo htmlspecialchars($feedback['comments']); ?></p>
                        </div>
                        <small class="text-muted"><?php echo date('M j, Y', strtotime($feedback['submitted_at'])); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Reports Chart
    const monthlyCtx = document.getElementById('monthlyReportsChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months_labels); ?>,
            datasets: [{
                label: 'Reports',
                data: <?php echo json_encode($months_data); ?>,
                lineTension: 0.3,
                backgroundColor: "rgba(78, 115, 223, 0.05)",
                borderColor: "rgba(78, 115, 223, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(78, 115, 223, 1)",
                pointBorderColor: "rgba(78, 115, 223, 1)",
                pointHoverRadius: 5,
                pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                fill: true
            }]
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 7
                    }
                },
                y: {
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        precision: 0
                    },
                    grid: {
                        drawBorder: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    titleColor: "#6e707e",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    padding: 15,
                    displayColors: false,
                    caretPadding: 10
                }
            }
        }
    });

    // Reports by Category Chart
    const categoryCtx = document.getElementById('reportsByCategoryChart').getContext('2d');
    const categoryData = [
        <?php 
        foreach ($reports_by_category as $category => $count) {
            echo $count . ', ';
        }
        ?>
    ];
    const categoryLabels = [
        <?php 
        foreach ($reports_by_category as $category => $count) {
            echo "'$category', ";
        }
        ?>
    ];
    new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryData,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#858796'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#6e707e'],
                hoverBorderColor: "rgba(234, 236, 244, 1)"
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    displayColors: false,
                    caretPadding: 10
                }
            }
        }
    });

    // Reports by Day Chart
    const dayCtx = document.getElementById('reportsByDayChart').getContext('2d');
    new Chart(dayCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($day_labels); ?>,
            datasets: [{
                label: 'Reports',
                data: <?php echo json_encode($day_data); ?>,
                backgroundColor: "#4e73df",
                borderColor: "#4e73df",
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // Feedback Ratings Distribution Chart
    const feedbackRatingsCtx = document.getElementById('feedbackRatingsChart').getContext('2d');
    new Chart(feedbackRatingsCtx, {
        type: 'bar',
        data: {
            labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
            datasets: [{
                label: 'Number of Ratings',
                data: <?php echo json_encode(array_values($ratings_distribution)); ?>,
                backgroundColor: [
                    '#dc3545', // Red for 1 star
                    '#fd7e14', // Orange for 2 stars
                    '#ffc107', // Yellow for 3 stars
                    '#20c997', // Teal for 4 stars
                    '#28a745'  // Green for 5 stars
                ],
                borderColor: [
                    '#dc3545',
                    '#fd7e14',
                    '#ffc107',
                    '#20c997',
                    '#28a745'
                ],
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    displayColors: false,
                    caretPadding: 10
                }
            }
        }
    });

    // Monthly Feedback Trends Chart
    const monthlyFeedbackCtx = document.getElementById('monthlyFeedbackChart').getContext('2d');
    new Chart(monthlyFeedbackCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months_labels); ?>,
            datasets: [{
                label: 'Feedback Count',
                data: <?php echo json_encode($feedback_months_data); ?>,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }, {
                label: 'Average Rating',
                data: <?php echo json_encode($feedback_ratings_data); ?>,
                borderColor: '#36b9cc',
                backgroundColor: 'rgba(54, 185, 204, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.3,
                yAxisID: 'y1'
            }]
        },
        options: {
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Month'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Feedback Count'
                    },
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Average Rating'
                    },
                    min: 0,
                    max: 5,
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    titleColor: "#6e707e",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    displayColors: true,
                    caretPadding: 10
                }
            }
        }
    });
});
</script>

<style>
.chart-area, .chart-pie, .chart-bar {
    position: relative;
    height: 20rem;
    width: 100%;
}

.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
</style>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/includes/footer.php'; ?>
