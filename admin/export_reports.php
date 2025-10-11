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

$admin_id = $_SESSION['user_id'];
$export_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'csv';
$date_range = isset($_GET['date_range']) ? sanitize_input($_GET['date_range']) : 'all';
$category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// If export is requested, generate the file
if (isset($_GET['export'])) {
    $conn = get_database_connection();
    
    // Build query based on filters
    $query = "SELECT r.report_id, r.tracking_code, r.title, r.description, r.category, 
              r.incident_date, r.status, r.created_at, r.updated_at, 
              u.username, u.email, u.phone 
              FROM reports r 
              JOIN users u ON r.user_id = u.user_id";
    
    // Add WHERE clauses based on filters
    $where_clauses = [];
    
    // Date range filter
    if ($date_range == 'today') {
        $where_clauses[] = "DATE(r.created_at) = CURDATE()";
    } elseif ($date_range == 'week') {
        $where_clauses[] = "r.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
    } elseif ($date_range == 'month') {
        $where_clauses[] = "r.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    } elseif ($date_range == 'year') {
        $where_clauses[] = "r.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    }
    
    // Category filter
    if (!empty($category)) {
        $where_clauses[] = "r.category = '$category'";
    }
    
    // Status filter
    if (!empty($status)) {
        $where_clauses[] = "r.status = '$status'";
    }
    
    // Finalize query
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $query .= " ORDER BY r.created_at DESC";
    
    // Execute query
    $result = $conn->query($query);
    
    if ($export_type == 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=cybercrime_reports_' . date('Y-m-d') . '.csv');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 encoding in Excel
        fprintf($output, "\xEF\xBB\xBF");
        
        // CSV header row
        fputcsv($output, [
            'Report ID', 'Tracking Code', 'Title', 'Description', 'Category',
            'Incident Date', 'Status', 'Created At', 'Updated At',
            'Reported By', 'Email', 'Phone'
        ]);
        
        // Add data rows
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['report_id'],
                $row['tracking_code'],
                $row['title'],
                $row['description'],
                $row['category'],
                $row['incident_date'],
                $row['status'],
                $row['created_at'],
                $row['updated_at'],
                $row['username'],
                $row['email'],
                $row['phone']
            ]);
        }
        
        fclose($output);
        $conn->close();
        exit();
    }
    
    $conn->close();
}

// Log admin action
log_admin_action($admin_id, "Accessed export reports page", null);

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/reports.php">Reports</a></li>
                <li class="breadcrumb-item active">Export Data</li>
            </ol>
        </nav>
        
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Export Reports Data</h1>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Export Options</h6>
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <input type="hidden" name="export" value="1">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="export_type" class="form-label">Export Format</label>
                            <select class="form-select" id="export_type" name="type">
                                <option value="csv" selected>CSV (Comma Separated Values)</option>
                            </select>
                            <div class="form-text">CSV format is compatible with Microsoft Excel, Google Sheets, and other spreadsheet applications.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="date_range" class="form-label">Date Range</label>
                            <select class="form-select" id="date_range" name="date_range">
                                <option value="all" selected>All Time</option>
                                <option value="today">Today Only</option>
                                <option value="week">Last 7 Days</option>
                                <option value="month">Last 30 Days</option>
                                <option value="year">Last 12 Months</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category Filter</label>
                            <select class="form-select" id="category" name="category">
                                <option value="" selected>All Categories</option>
                                <option value="Phishing">Phishing</option>
                                <option value="Hacking">Hacking</option>
                                <option value="Fraud">Fraud</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status Filter</label>
                            <select class="form-select" id="status" name="status">
                                <option value="" selected>All Statuses</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Under Review">Under Review</option>
                                <option value="In Investigation">In Investigation</option>
                                <option value="Resolved">Resolved</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle fa-2x"></i>
                            </div>
                            <div>
                                <h5>About Data Export</h5>
                                <p>This tool allows you to export cybercrime report data for analysis or reporting purposes. The exported data will include:</p>
                                <ul>
                                    <li>Report details (ID, tracking code, title, description, etc.)</li>
                                    <li>Incident information (category, date, status)</li>
                                    <li>Reporter information (username, email, phone)</li>
                                </ul>
                                <p class="mb-0">Note: Exported data may contain sensitive information. Handle with appropriate security measures and in accordance with data protection regulations.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-export me-2"></i>Export Data
                        </button>
                        <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Reports
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Advanced Analytics</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Reporting Trends</h5>
                                <canvas id="reportingTrendsChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Category Distribution</h5>
                                <canvas id="categoryDistributionChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-secondary">
                    <i class="fas fa-chart-line me-2"></i>
                    Looking for more detailed analytics? Export the data and use specialized analysis tools for deeper insights.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Load analytics data
document.addEventListener('DOMContentLoaded', function() {
    // Sample data for charts (in a real implementation, this would come from server-side data)
    
    // Reporting trends (last 6 months)
    const trendsCtx = document.getElementById('reportingTrendsChart').getContext('2d');
    const trendLabels = [];
    const trendData = [];
    
    // Generate 6 months of labels and random data
    for (let i = 5; i >= 0; i--) {
        const date = new Date();
        date.setMonth(date.getMonth() - i);
        trendLabels.push(date.toLocaleString('default', { month: 'short' }) + ' ' + date.getFullYear());
        trendData.push(Math.floor(Math.random() * 50) + 10); // Random number between 10-60
    }
    
    const trendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Reports Submitted',
                data: trendData,
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                borderColor: 'rgba(78, 115, 223, 1)',
                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Category distribution
    const categoryCtx = document.getElementById('categoryDistributionChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: ['Phishing', 'Hacking', 'Fraud', 'Other'],
            datasets: [{
                data: [35, 25, 30, 10], // Sample percentages
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#858796'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#6e707e'],
                hoverBorderColor: 'rgba(234, 236, 244, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'right'
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
