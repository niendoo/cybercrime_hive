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

// Initialize variables
$reports = [];
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$category_filter = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 20;
$total_reports = 0;

// Build query based on filters
$conn = get_database_connection();

// Base query
$query = "SELECT r.*, u.username 
          FROM reports r 
          JOIN users u ON r.user_id = u.user_id";

// Count query
$count_query = "SELECT COUNT(*) as total FROM reports r";

// Add WHERE clauses based on filters
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($status_filter)) {
    $where_clauses[] = "r.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($category_filter)) {
    $where_clauses[] = "r.category = ?";
    $params[] = $category_filter;
    $param_types .= 's';
}

if (!empty($search_term)) {
    $where_clauses[] = "(r.title LIKE ? OR r.description LIKE ? OR r.tracking_code LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

// Finalize query
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
    $count_query .= " JOIN users u ON r.user_id = u.user_id WHERE " . implode(" AND ", $where_clauses);
}

// Get total count for pagination
$stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_reports = $row['total'];
$total_pages = ceil($total_reports / $items_per_page);

// Adjust page if out of bounds
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Add pagination
$offset = ($page - 1) * $items_per_page;
$query .= " ORDER BY r.created_at DESC LIMIT $offset, $items_per_page";

// Execute final query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($report = $result->fetch_assoc()) {
    $reports[] = $report;
}

$stmt->close();
$conn->close();

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Main Content -->
<main class="py-4 px-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Manage Reports</h1>
            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <!-- Filter Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filter Reports</h6>
            </div>
            <div class="card-body">
                <form method="get" action="" class="mb-0">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="Submitted" <?php if($status_filter == 'Submitted') echo 'selected'; ?>>Submitted</option>
                                <option value="Under Review" <?php if($status_filter == 'Under Review') echo 'selected'; ?>>Under Review</option>
                                <option value="In Investigation" <?php if($status_filter == 'In Investigation') echo 'selected'; ?>>In Investigation</option>
                                <option value="Resolved" <?php if($status_filter == 'Resolved') echo 'selected'; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <option value="Phishing" <?php if($category_filter == 'Phishing') echo 'selected'; ?>>Phishing</option>
                                <option value="Hacking" <?php if($category_filter == 'Hacking') echo 'selected'; ?>>Hacking</option>
                                <option value="Fraud" <?php if($category_filter == 'Fraud') echo 'selected'; ?>>Fraud</option>
                                <option value="Other" <?php if($category_filter == 'Other') echo 'selected'; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by title, description, tracking code, or username" value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="d-grid gap-2 w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                                <?php if (!empty($status_filter) || !empty($category_filter) || !empty($search_term)): ?>
                                    <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reports List Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <?php 
                    echo 'Reports List';
                    if (!empty($status_filter)) echo ' - Status: ' . htmlspecialchars($status_filter);
                    if (!empty($category_filter)) echo ' - Category: ' . htmlspecialchars($category_filter);
                    if (!empty($search_term)) echo ' - Search: "' . htmlspecialchars($search_term) . '"';
                    ?>
                </h6>
                <div class="small text-gray-500">
                    Total: <?php echo $total_reports; ?> reports
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (count($reports) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="border-0">ID</th>
                                    <th class="border-0">Title</th>
                                    <th class="border-0">Category</th>
                                    <th class="border-0">Status</th>
                                    <th class="border-0">Submitted By</th>
                                    <th class="border-0">Date</th>
                                    <th class="border-0 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td>
                                            <small class="d-block text-muted">#<?php echo $report['report_id']; ?></small>
                                            <small class="font-weight-bold"><?php echo $report['tracking_code']; ?></small>
                                        </td>
                                        <td class="font-weight-bold"><?php echo htmlspecialchars($report['title']); ?></td>
                                        <td><span class="category-badge"><?php echo htmlspecialchars($report['category']); ?></span></td>
                                        <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>"><?php echo ucfirst($report['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($report['username']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                        <td class="text-end">
                                            <a href="<?php echo SITE_URL; ?>/admin/view_report.php?id=<?php echo $report['report_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Report">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-4">
                            <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                                <a class="page-link" href="<?php 
                                $query_params = $_GET;
                                $query_params['page'] = 1;
                                echo SITE_URL . '/admin/reports.php?' . http_build_query($query_params);
                                ?>" aria-label="First">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                                <a class="page-link" href="<?php 
                                $query_params = $_GET;
                                $query_params['page'] = $page - 1;
                                echo SITE_URL . '/admin/reports.php?' . http_build_query($query_params);
                                ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                                $query_params = $_GET;
                                $query_params['page'] = $i;
                            ?>
                            <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                <a class="page-link" href="<?php echo SITE_URL . '/admin/reports.php?' . http_build_query($query_params); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                                <a class="page-link" href="<?php 
                                $query_params = $_GET;
                                $query_params['page'] = $page + 1;
                                echo SITE_URL . '/admin/reports.php?' . http_build_query($query_params);
                                ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                                <a class="page-link" href="<?php 
                                $query_params = $_GET;
                                $query_params['page'] = $total_pages;
                                echo SITE_URL . '/admin/reports.php?' . http_build_query($query_params);
                                ?>" aria-label="Last">
                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-folder-open fa-4x text-gray-300 mb-3"></i>
                        <p>No reports found matching your criteria.</p>
                        <?php if (!empty($status_filter) || !empty($category_filter) || !empty($search_term)): ?>
                            <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="btn btn-primary">
                                <i class="fas fa-times me-2"></i>Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>


<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<style>
.report-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border-left: 4px solid #4e73df;
}

.report-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
}

.status-badge {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.35em 0.65em;
    border-radius: 0.25rem;
}

.status-submitted { background-color: #f6c23e; color: #000; }
.status-under-review { background-color: #36b9cc; color: #fff; }
.status-investigation { background-color: #4e73df; color: #fff; }
.status-resolved { background-color: #1cc88a; color: #fff; }

.category-badge {
    font-size: 0.8rem;
    padding: 0.35em 0.65em;
    border-radius: 0.25rem;
    background-color: #f8f9fa;
    border: 1px solid #e3e6f0;
    color: #5a5c69;
}
</style>
