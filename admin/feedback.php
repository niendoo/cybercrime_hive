<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = get_database_connection();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'mark_reviewed':
            $feedback_id = intval($_GET['id']);
            $stmt = $conn->prepare("UPDATE feedback SET reviewed = 1, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $feedback_id);
            echo json_encode(['success' => $stmt->execute()]);
            exit();
            
        case 'export':
            $format = $_GET['format'] ?? 'csv';
            exportFeedback($conn, $format);
            exit();
            
        case 'analytics':
            echo json_encode(getFeedbackAnalytics($conn));
            exit();
    }
}

// Pagination and filtering
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$rating_filter = $_GET['rating'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(f.comments LIKE ? OR r.report_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($rating_filter)) {
    $where_conditions[] = "f.rating = ?";
    $params[] = intval($rating_filter);
    $types .= 'i';
}

if (!empty($status_filter)) {
    if ($status_filter === 'reviewed') {
        $where_conditions[] = "f.reviewed = 1";
    } elseif ($status_filter === 'pending') {
        $where_conditions[] = "f.reviewed = 0";
    }
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(f.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(f.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM feedback f 
              LEFT JOIN reports r ON f.report_id = r.report_id 
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get feedback data
$sql = "SELECT f.*, r.report_id, r.status as report_status, r.category as incident_type,
               u.username, u.email
        FROM feedback f
        LEFT JOIN reports r ON f.report_id = r.report_id
        LEFT JOIN users u ON r.user_id = u.user_id
        $where_clause
        ORDER BY f.submitted_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}

$stmt->execute();
$feedback_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get analytics data
$analytics = getFeedbackAnalytics($conn);

function getFeedbackAnalytics($conn) {
    $analytics = [];
    
    // Average rating
    $result = $conn->query("SELECT AVG(overall_rating) as avg_rating FROM feedback WHERE overall_rating > 0");
    $analytics['avg_rating'] = round($result->fetch_assoc()['avg_rating'] ?? 0, 2);
    
    // Total feedback count
    $result = $conn->query("SELECT COUNT(*) as total FROM feedback");
    $analytics['total_feedback'] = $result->fetch_assoc()['total'];
    
    // Total feedback count (removing reviewed status as column doesn't exist)
    $analytics['status_counts'] = ['total' => $analytics['total_feedback']];
    
    // Rating distribution
    $result = $conn->query("SELECT overall_rating, COUNT(*) as count FROM feedback WHERE overall_rating > 0 GROUP BY overall_rating ORDER BY overall_rating");
    $rating_dist = [];
    while ($row = $result->fetch_assoc()) {
        $rating_dist[$row['overall_rating']] = $row['count'];
    }
    $analytics['rating_distribution'] = $rating_dist;
    
    // Monthly trends (last 6 months) - using submitted_at
    $result = $conn->query("
        SELECT DATE_FORMAT(submitted_at, '%Y-%m') as month, 
               COUNT(*) as count,
               AVG(overall_rating) as avg_rating
        FROM feedback 
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
        ORDER BY month
    ");
    $monthly_trends = [];
    while ($row = $result->fetch_assoc()) {
        $monthly_trends[] = $row;
    }
    $analytics['monthly_trends'] = $monthly_trends;
    
    return $analytics;
}

function exportFeedback($conn, $format) {
    $sql = "SELECT f.*, r.report_id, r.status as report_status, r.category as incident_type,
                   u.username, u.email
            FROM feedback f
            LEFT JOIN reports r ON f.report_id = r.report_id
            LEFT JOIN users u ON r.user_id = u.user_id
            ORDER BY f.submitted_at DESC";
    
    $result = $conn->query($sql);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="feedback_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Report ID', 'Incident Type', 'Username', 'Email', 'Rating', 'Comments', 'Report Status', 'Created At']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['feedback_id'],
                $row['report_id'],
                $row['incident_type'],
                $row['username'],
                $row['email'],
                $row['overall_rating'],
                $row['comments'],
                $row['report_status'],
                $row['submitted_at']
            ]);
        }
        fclose($output);
    } elseif ($format === 'pdf') {
        // Simple HTML to PDF conversion
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="feedback_export_' . date('Y-m-d') . '.pdf"');
        
        // Start HTML content
        $html = '<html><head><style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .star { color: #ffc107; }
        </style></head><body>';
        
        $html .= '<div class="header">';
        $html .= '<h2>Feedback Export Report</h2>';
        $html .= '<p>Generated on ' . date('F j, Y g:i A') . '</p>';
        $html .= '</div>';
        
        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>ID</th><th>Report ID</th><th>Username</th><th>Rating</th><th>Comments</th><th>Status</th><th>Created At</th>';
        $html .= '</tr></thead><tbody>';
        
        while ($row = $result->fetch_assoc()) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($row['feedback_id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['report_id'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['username'] ?? 'Anonymous') . '</td>';
            $html .= '<td>';
            for ($i = 1; $i <= 5; $i++) {
                $html .= '<span class="star">' . ($i <= $row['overall_rating'] ? '★' : '☆') . '</span>';
            }
            $html .= ' (' . $row['overall_rating'] . '/5)</td>';
            $html .= '<td>' . htmlspecialchars(substr($row['comments'] ?? '', 0, 100)) . (strlen($row['comments'] ?? '') > 100 ? '...' : '') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['report_status'] ?? 'N/A') . '</td>';
            $html .= '<td>' . date('M j, Y g:i A', strtotime($row['submitted_at'])) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></body></html>';
        
        // For basic PDF generation without external libraries
        // This creates an HTML file that browsers can print to PDF
        header('Content-Type: text/html');
        header('Content-Disposition: inline; filename="feedback_export_' . date('Y-m-d') . '.html"');
        echo $html;
        echo '<script>window.print();</script>';
    }
}
include dirname(__DIR__) . '/includes/header.php'; 
?>




<style>
    .star-rating {
        color: #ffc107;
    }
    .star-rating .empty {
        color: #e4e5e9;
    }
    .feedback-card {
        transition: all 0.3s ease;
    }
    .feedback-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .analytics-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .chart-container {
        position: relative;
        height: 300px;
    }
    .filter-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .status-badge {
        font-size: 0.8em;
    }
    .stat-card {
        transition: transform 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
    .dashboard-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
    }
</style>

<div class="container-fluid">
    <div class="p-4">
            
        <!-- Page Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-comments"></i> Feedback Management</h1>
                    <p class="mb-0">Monitor and analyze user feedback submissions</p>
                </div>
                <div>
                    <div class="btn-group">
                        <button class="btn btn-light" onclick="exportData('csv')">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                        <button class="btn btn-light" onclick="exportData('pdf')">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
                    
                    <!-- Analytics Section -->
        <div id="analyticsSection" class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo number_format($analytics['avg_rating'], 1); ?></h3>
                                            <p class="mb-0">Average Rating</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-star fa-2x text-muted"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $analytics['total_feedback']; ?></h3>
                                            <p class="mb-0">Total Feedback</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-comments fa-2x text-muted"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo count($feedback_data); ?></h3>
                                            <p class="mb-0">Current Page</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-file-alt fa-2x text-muted"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $total_pages; ?></h3>
                                            <p class="mb-0">Total Pages</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-list fa-2x text-muted"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mt-3">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Rating Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="ratingChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mt-3">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Monthly Trends</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="trendsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
        <!-- Filters -->
        <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search comments or report ID...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Rating</label>
                                <select class="form-select" name="rating">
                                    <option value="">All Ratings</option>
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $rating_filter == $i ? 'selected' : ''; ?>>
                                            <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Recommend</label>
                                <select class="form-select" name="recommend">
                                    <option value="">All</option>
                                    <option value="1" <?php echo isset($_GET['recommend']) && $_GET['recommend'] === '1' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="0" <?php echo isset($_GET['recommend']) && $_GET['recommend'] === '0' ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    
        <!-- Feedback Table -->
        <div class="card">
                        <div class="card-header">
                            <h5>Feedback Submissions (<?php echo $total_records; ?> total)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($feedback_data)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No feedback found</h5>
                                    <p class="text-muted">No feedback submissions match your current filters.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Report</th>
                                                <th>User</th>
                                                <th>Rating</th>
                                                <th>Comments</th>
                                                <th>Submitted</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($feedback_data as $feedback): ?>
                                                <tr>
                                                    <td><?php echo $feedback['feedback_id']; ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($feedback['report_id'] ?? 'N/A'); ?></span>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($feedback['incident_type'] ?? ''); ?></small>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($feedback['username'] ?? 'Anonymous'); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($feedback['email'] ?? ''); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="star-rating">
                                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                                <i class="fas fa-star <?php echo $i <= $feedback['overall_rating'] ? '' : 'empty'; ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <small class="text-muted">(<?php echo $feedback['overall_rating']; ?>/5)</small>
                                                    </td>
                                                    <td>
                                                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                                            <?php echo htmlspecialchars(substr($feedback['comments'] ?? '', 0, 100)); ?>
                                                            <?php if (strlen($feedback['comments'] ?? '') > 100): ?>..<?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info status-badge">Submitted</span>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('M j, Y g:i A', strtotime($feedback['submitted_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDetails(<?php echo $feedback['feedback_id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Feedback pagination">
                                        <ul class="pagination justify-content-center">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                        Previous
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                        Next
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
        </div>
    </div>
</div>
    
    <!-- Feedback Details Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: #ffffff; border: 1px solid #dee2e6; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="border-bottom: 1px solid #dee2e6; background: #f8f9fa; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title text-dark fw-bold"><i class="fas fa-comment-dots me-2"></i>Feedback Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-dark" id="feedbackModalBody" style="background: #ffffff; border-radius: 0 0 15px 15px;">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>
    

    <script>
        // Analytics data from PHP
        const analyticsData = <?php echo json_encode($analytics); ?>;
        
        function initCharts() {
            // Rating Distribution Chart
            const ratingCtx = document.getElementById('ratingChart').getContext('2d');
            new Chart(ratingCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(analyticsData.rating_distribution).map(r => r + ' Star' + (r > 1 ? 's' : '')),
                    datasets: [{
                        data: Object.values(analyticsData.rating_distribution),
                        backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#198754']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Monthly Trends Chart
            const trendsCtx = document.getElementById('trendsChart').getContext('2d');
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: analyticsData.monthly_trends.map(t => t.month),
                    datasets: [{
                        label: 'Feedback Count',
                        data: analyticsData.monthly_trends.map(t => t.count),
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#0d6efd',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        yAxisID: 'y'
                    }, {
                        label: 'Average Rating',
                        data: analyticsData.monthly_trends.map(t => parseFloat(t.avg_rating)),
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#198754',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            },
                            ticks: {
                                stepSize: 1
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            min: 0,
                            max: 5,
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                stepSize: 0.5
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                }
            });
        }
        
        // Removed markReviewed function as reviewed status is not supported
        
        function viewDetails(feedbackId) {
            // Find feedback data
            const feedbackData = <?php echo json_encode($feedback_data); ?>;
            const feedback = feedbackData.find(f => f.feedback_id == feedbackId);
            
            if (feedback) {
                const modalBody = document.getElementById('feedbackModalBody');
                modalBody.innerHTML = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="card" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px;">
                                <div class="card-body">
                                    <h6 class="text-dark mb-3"><i class="fas fa-file-alt me-2"></i>Report Information</h6>
                                    <p class="text-muted mb-2"><strong class="text-dark">Report ID:</strong> ${feedback.report_id || 'N/A'}</p>
                                    <p class="text-muted mb-2"><strong class="text-dark">Incident Type:</strong> ${feedback.incident_type || 'N/A'}</p>
                                    <p class="text-muted mb-0"><strong class="text-dark">Report Status:</strong> <span class="badge bg-info">${feedback.report_status || 'N/A'}</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px;">
                                <div class="card-body">
                                    <h6 class="text-dark mb-3"><i class="fas fa-user me-2"></i>User Information</h6>
                                    <p class="text-muted mb-2"><strong class="text-dark">Username:</strong> ${feedback.username || 'Anonymous'}</p>
                                    <p class="text-muted mb-0"><strong class="text-dark">Email:</strong> ${feedback.email || 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="card" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px;">
                                <div class="card-body">
                                    <h6 class="text-dark mb-3"><i class="fas fa-star me-2"></i>Rating</h6>
                                    <div class="star-rating fs-4 mb-2">
                                        ${Array.from({length: 5}, (_, i) => 
                                            `<i class="fas fa-star ${i < feedback.overall_rating ? 'text-warning' : 'text-muted'}"></i>`
                                        ).join('')}
                                    </div>
                                    <p class="text-muted mb-0">${feedback.overall_rating}/5 stars</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px;">
                                <div class="card-body">
                                    <h6 class="text-dark mb-3"><i class="fas fa-check-circle me-2"></i>Status</h6>
                                    <span class="badge bg-success fs-6">Submitted</span>
                                    <p class="text-muted mt-2 mb-0"><small>Submitted on ${new Date(feedback.submitted_at).toLocaleString()}</small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px;">
                        <div class="card-body">
                            <h6 class="text-dark mb-3"><i class="fas fa-comment me-2"></i>Comments</h6>
                            <div class="p-3 rounded" style="background: #ffffff; border: 1px solid #dee2e6;">
                                <p class="text-muted mb-0">${feedback.comments || '<em>No comments provided</em>'}</p>
                            </div>
                        </div>
                    </div>
                `;
                
                new bootstrap.Modal(document.getElementById('feedbackModal')).show();
            }
        }
        
        function exportData(format) {
            window.location.href = `?action=export&format=${format}`;
        }
        
        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
        });
    </script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>