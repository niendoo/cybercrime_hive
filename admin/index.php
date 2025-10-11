<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$conn = get_database_connection();

// Get dashboard statistics
$stats = [];

// Total reports
$result = $conn->query("SELECT COUNT(*) as total FROM reports");
$stats['total_reports'] = $result->fetch_assoc()['total'];

// Pending reports
$result = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status = 'pending'");
$stats['pending_reports'] = $result->fetch_assoc()['total'];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Total feedback
$result = $conn->query("SELECT COUNT(*) as total FROM feedback");
$stats['total_feedback'] = $result->fetch_assoc()['total'];

// Pending feedback
$result = $conn->query("SELECT COUNT(*) as total FROM feedback WHERE reviewed = 0");
$stats['pending_feedback'] = $result->fetch_assoc()['total'];

// Average feedback rating
$result = $conn->query("SELECT AVG(rating) as avg_rating FROM feedback WHERE rating > 0");
$stats['avg_rating'] = round($result->fetch_assoc()['avg_rating'] ?? 0, 2);

// Recent reports
$recent_reports = $conn->query("
    SELECT r.*, u.username 
    FROM reports r 
    LEFT JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Recent feedback
$recent_feedback = $conn->query("
    SELECT f.*, r.report_id, u.username 
    FROM feedback f 
    LEFT JOIN reports r ON f.report_id = r.id 
    LEFT JOIN users u ON r.user_id = u.id 
    ORDER BY f.created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cybercrime Hive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .nav-link {
            border-radius: 10px;
            margin: 5px 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .nav-link.active {
            background-color: rgba(255,255,255,0.2);
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar text-white p-0">
                <div class="p-4">
                    <h4 class="mb-4">
                        <i class="fas fa-shield-alt"></i> Admin Panel
                    </h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../view_reports.php">
                                <i class="fas fa-file-alt"></i> Manage Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="feedback.php">
                                <i class="fas fa-comments"></i> Feedback Management
                                <?php if ($stats['pending_feedback'] > 0): ?>
                                    <span class="badge bg-warning ms-2"><?php echo $stats['pending_feedback']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="users.php">
                                <i class="fas fa-users"></i> User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-white" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="p-4">
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                        <p class="mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Here's your system overview.</p>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $stats['total_reports']; ?></h3>
                                            <p class="mb-0">Total Reports</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-file-alt fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $stats['pending_reports']; ?></h3>
                                            <p class="mb-0">Pending Reports</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clock fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $stats['total_users']; ?></h3>
                                            <p class="mb-0">Total Users</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-users fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $stats['avg_rating']; ?>/5</h3>
                                            <p class="mb-0">Avg Feedback Rating</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-star fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <a href="../view_reports.php" class="btn btn-primary w-100 mb-2">
                                                <i class="fas fa-file-alt"></i> View All Reports
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="feedback.php" class="btn btn-success w-100 mb-2">
                                                <i class="fas fa-comments"></i> Manage Feedback
                                                <?php if ($stats['pending_feedback'] > 0): ?>
                                                    <span class="badge bg-warning ms-1"><?php echo $stats['pending_feedback']; ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="users.php" class="btn btn-info w-100 mb-2">
                                                <i class="fas fa-users"></i> Manage Users
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="settings.php" class="btn btn-secondary w-100 mb-2">
                                                <i class="fas fa-cog"></i> System Settings
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="row">
                        <!-- Recent Reports -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-file-alt"></i> Recent Reports</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_reports)): ?>
                                        <p class="text-muted">No recent reports</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($recent_reports as $report): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                                    <div class="ms-2 me-auto">
                                                        <div class="fw-bold"><?php echo htmlspecialchars($report['incident_type']); ?></div>
                                                        <small class="text-muted">by <?php echo htmlspecialchars($report['username'] ?? 'Anonymous'); ?></small>
                                                    </div>
                                                    <span class="badge bg-<?php echo $report['status'] === 'pending' ? 'warning' : ($report['status'] === 'resolved' ? 'success' : 'info'); ?> rounded-pill">
                                                        <?php echo ucfirst($report['status']); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-center mt-3">
                                            <a href="../view_reports.php" class="btn btn-outline-primary btn-sm">
                                                View All Reports <i class="fas fa-arrow-right"></i>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Feedback -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-comments"></i> Recent Feedback</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_feedback)): ?>
                                        <p class="text-muted">No recent feedback</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($recent_feedback as $feedback): ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="ms-2 me-auto">
                                                            <div class="fw-bold">
                                                                Report #<?php echo htmlspecialchars($feedback['report_id'] ?? 'N/A'); ?>
                                                            </div>
                                                            <div class="text-warning">
                                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? '' : 'text-muted'; ?>"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <small class="text-muted">by <?php echo htmlspecialchars($feedback['username'] ?? 'Anonymous'); ?></small>
                                                        </div>
                                                        <span class="badge bg-<?php echo $feedback['reviewed'] ? 'success' : 'warning'; ?> rounded-pill">
                                                            <?php echo $feedback['reviewed'] ? 'Reviewed' : 'Pending'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-center mt-3">
                                            <a href="feedback.php" class="btn btn-outline-success btn-sm">
                                                Manage Feedback <i class="fas fa-arrow-right"></i>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>