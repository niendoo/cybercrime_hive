<?php
header('Content-Type: application/json');

// Set CORS headers to allow controlled access
header('Access-Control-Allow-Origin: *'); // Or specify allowed domains
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API entry point
require_once $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/includes/functions.php';

// Extract the requested endpoint
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);

// Find the API endpoint in the URI (after /api/)
for ($i = 0; $i < count($uri); $i++) {
    if ($uri[$i] === 'api') {
        $endpoint = isset($uri[$i+1]) ? $uri[$i+1] : '';
        $resource = isset($uri[$i+2]) ? $uri[$i+2] : '';
        break;
    }
}

// Default response structure
$response = [
    'status' => 'error',
    'message' => 'Invalid request',
    'data' => null
];

// Authentication middleware
function authenticate_api_request() {
    // Check for API key in header
    $headers = getallheaders();
    $api_key = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : '';
    
    if (empty($api_key)) {
        // Also check for API key in query string
        $api_key = isset($_GET['api_key']) ? $_GET['api_key'] : '';
    }
    
    // API keys should be validated against a database table in production
    // For this example, we'll use a constant
    if ($api_key !== API_KEY) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized. Invalid API key.',
            'data' => null
        ]);
        exit;
    }
    
    return true;
}

// Handle API request based on endpoint
switch ($endpoint) {
    case 'stats':
        // Public statistics endpoint - no authentication required
        $response = get_public_stats();
        break;
        
    case 'reports':
        // Requires authentication
        authenticate_api_request();
        
        if ($resource) {
            // Get specific report by tracking code
            $response = get_report_by_tracking_code($resource);
        } else {
            // List reports with optional filters
            $response = get_filtered_reports($_GET);
        }
        break;
        
    case 'categories':
        // Requires authentication
        authenticate_api_request();
        
        // List all categories with their count
        $response = get_categories();
        break;
        
    case 'submit':
        // Requires authentication
        authenticate_api_request();
        
        // Handle report submission via API
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $response = submit_report();
        } else {
            $response['message'] = 'Method not allowed. Use POST for submitting reports.';
            http_response_code(405);
        }
        break;
        
    default:
        // Show API documentation for the root endpoint
        if (empty($endpoint)) {
            $response = [
                'status' => 'success',
                'message' => 'Cybercrime Hive API',
                'version' => '1.0',
                'endpoints' => [
                    'GET /api/stats' => 'Public statistics about cybercrime reports',
                    'GET /api/reports' => 'List all reports (requires API key)',
                    'GET /api/reports/{tracking_code}' => 'Get report details by tracking code (requires API key)',
                    'GET /api/categories' => 'List all report categories (requires API key)',
                    'POST /api/submit' => 'Submit a new report (requires API key)'
                ]
            ];
        } else {
            http_response_code(404);
            $response['message'] = 'Endpoint not found';
        }
}

// Output the response as JSON
echo json_encode($response, JSON_PRETTY_PRINT);
exit;

/**
 * API Functions
 */

// Get public statistics
function get_public_stats() {
    $conn = get_database_connection();
    
    // Total reports
    $result = $conn->query("SELECT COUNT(*) as total FROM reports");
    $total_reports = $result->fetch_assoc()['total'];
    
    // Reports by category (aggregated)
    $result = $conn->query("SELECT category, COUNT(*) as count FROM reports GROUP BY category");
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[$row['category']] = (int)$row['count'];
    }
    
    // Reports by status (aggregated)
    $result = $conn->query("SELECT status, COUNT(*) as count FROM reports GROUP BY status");
    $statuses = [];
    while ($row = $result->fetch_assoc()) {
        $statuses[$row['status']] = (int)$row['count'];
    }
    
    // Reports trend by month (last 6 months)
    $result = $conn->query("SELECT 
                           DATE_FORMAT(created_at, '%Y-%m') as month,
                           COUNT(*) as count 
                           FROM reports 
                           WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                           GROUP BY month 
                           ORDER BY month");
    $monthly_trend = [];
    while ($row = $result->fetch_assoc()) {
        $monthly_trend[$row['month']] = (int)$row['count'];
    }
    
    $conn->close();
    
    return [
        'status' => 'success',
        'message' => 'Public statistics retrieved successfully',
        'data' => [
            'total_reports' => $total_reports,
            'by_category' => $categories,
            'by_status' => $statuses,
            'monthly_trend' => $monthly_trend
        ]
    ];
}

// Get report by tracking code
function get_report_by_tracking_code($tracking_code) {
    $conn = get_database_connection();
    
    $stmt = $conn->prepare("SELECT r.report_id, r.tracking_code, r.title, r.category, 
                          r.incident_date, r.status, r.created_at, r.updated_at,
                          u.username as reported_by
                          FROM reports r 
                          JOIN users u ON r.user_id = u.user_id
                          WHERE r.tracking_code = ?");
    $stmt->bind_param("s", $tracking_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $report = $result->fetch_assoc();
        
        // Get report attachments
        $stmt = $conn->prepare("SELECT attachment_id, filename, file_type, file_size 
                              FROM attachments WHERE report_id = ?");
        $stmt->bind_param("i", $report['report_id']);
        $stmt->execute();
        $attachments_result = $stmt->get_result();
        
        $attachments = [];
        while ($attachment = $attachments_result->fetch_assoc()) {
            // Don't include the file path for security reasons
            $attachments[] = [
                'id' => $attachment['attachment_id'],
                'filename' => $attachment['filename'],
                'type' => $attachment['file_type'],
                'size' => $attachment['file_size']
            ];
        }
        
        $report['attachments'] = $attachments;
        
        // Remove sensitive info
        unset($report['report_id']);
        
        $response = [
            'status' => 'success',
            'message' => 'Report retrieved successfully',
            'data' => $report
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Report not found',
            'data' => null
        ];
        http_response_code(404);
    }
    
    $stmt->close();
    $conn->close();
    
    return $response;
}

// Get filtered reports
function get_filtered_reports($params) {
    $conn = get_database_connection();
    
    // Base query
    $query = "SELECT r.tracking_code, r.title, r.category, r.status, 
              r.created_at, u.username as reported_by
              FROM reports r 
              JOIN users u ON r.user_id = u.user_id";
    
    // Build WHERE clause based on filters
    $where_clauses = [];
    $bind_types = "";
    $bind_params = [];
    
    // Filter by category
    if (isset($params['category']) && !empty($params['category'])) {
        $where_clauses[] = "r.category = ?";
        $bind_types .= "s";
        $bind_params[] = $params['category'];
    }
    
    // Filter by status
    if (isset($params['status']) && !empty($params['status'])) {
        $where_clauses[] = "r.status = ?";
        $bind_types .= "s";
        $bind_params[] = $params['status'];
    }
    
    // Filter by date range
    if (isset($params['start_date']) && !empty($params['start_date'])) {
        $where_clauses[] = "r.created_at >= ?";
        $bind_types .= "s";
        $bind_params[] = $params['start_date'] . ' 00:00:00';
    }
    
    if (isset($params['end_date']) && !empty($params['end_date'])) {
        $where_clauses[] = "r.created_at <= ?";
        $bind_types .= "s";
        $bind_params[] = $params['end_date'] . ' 23:59:59';
    }
    
    // Finalize query
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // Add sorting and pagination
    $sort_field = isset($params['sort']) ? sanitize_input($params['sort']) : 'created_at';
    $sort_dir = isset($params['dir']) ? sanitize_input($params['dir']) : 'DESC';
    
    // Validate sort field to prevent injection
    $allowed_sort_fields = ['tracking_code', 'title', 'category', 'status', 'created_at', 'reported_by'];
    if (!in_array($sort_field, $allowed_sort_fields)) {
        $sort_field = 'created_at';
    }
    
    // Validate sort direction
    if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
        $sort_dir = 'DESC';
    }
    
    $query .= " ORDER BY " . ($sort_field === 'reported_by' ? 'u.username' : "r.{$sort_field}") . " {$sort_dir}";
    
    // Pagination
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
    
    // Validate limit
    if ($limit < 1 || $limit > 100) {
        $limit = 10;
    }
    
    // Validate page
    if ($page < 1) {
        $page = 1;
    }
    
    $offset = ($page - 1) * $limit;
    $query .= " LIMIT ?, ?";
    $bind_types .= "ii";
    $bind_params[] = $offset;
    $bind_params[] = $limit;
    
    // Execute query
    $stmt = $conn->prepare($query);
    
    if (!empty($bind_params)) {
        // Create a reference array for bind_param
        $bind_params_refs = [];
        foreach($bind_params as $key => $value) {
            $bind_params_refs[$key] = &$bind_params[$key];
        }
        array_unshift($bind_params_refs, $bind_types);
        call_user_func_array([$stmt, 'bind_param'], $bind_params_refs);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM reports r JOIN users u ON r.user_id = u.user_id";
    if (!empty($where_clauses)) {
        $count_query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $count_stmt = $conn->prepare($count_query);
    
    if (!empty($bind_types) && !empty($bind_params) && count($where_clauses) > 0) {
        // Remove the last two parameters (limit and offset) for the count query
        array_pop($bind_params_refs);
        array_pop($bind_params_refs);
        // Update bind_types to remove the last two 'i's
        $bind_params_refs[0] = substr($bind_types, 0, -2);
        
        call_user_func_array([$count_stmt, 'bind_param'], $bind_params_refs);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total = $count_result->fetch_assoc()['total'];
    
    $meta = [
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ];
    
    $stmt->close();
    $count_stmt->close();
    $conn->close();
    
    return [
        'status' => 'success',
        'message' => 'Reports retrieved successfully',
        'meta' => $meta,
        'data' => $reports
    ];
}

// Get report categories with count
function get_categories() {
    $conn = get_database_connection();
    
    $query = "SELECT category, COUNT(*) as count FROM reports GROUP BY category ORDER BY count DESC";
    $result = $conn->query($query);
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'name' => $row['category'],
            'count' => (int)$row['count']
        ];
    }
    
    $conn->close();
    
    return [
        'status' => 'success',
        'message' => 'Categories retrieved successfully',
        'data' => $categories
    ];
}

// Submit a report via API
function submit_report() {
    // Get JSON data from request body
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => 'error',
            'message' => 'Invalid JSON data',
            'data' => null
        ];
    }
    
    // Validate required fields
    $required_fields = ['title', 'description', 'category', 'user_id', 'incident_date'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return [
                'status' => 'error',
                'message' => "Missing required field: {$field}",
                'data' => null
            ];
        }
    }
    
    // Sanitize inputs
    $title = sanitize_input($data['title']);
    $description = sanitize_input($data['description']);
    $category = sanitize_input($data['category']);
    $user_id = (int)$data['user_id'];
    $incident_date = sanitize_input($data['incident_date']);
    
    // Verify user exists
    $conn = get_database_connection();
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        
        return [
            'status' => 'error',
            'message' => 'Invalid user ID',
            'data' => null
        ];
    }
    
    // Generate tracking code
    $tracking_code = generate_tracking_code();
    
    // Insert the report
    $stmt = $conn->prepare("INSERT INTO reports (user_id, tracking_code, title, description, category, incident_date, status) 
                         VALUES (?, ?, ?, ?, ?, ?, 'Submitted')");
    $stmt->bind_param("isssss", $user_id, $tracking_code, $title, $description, $category, $incident_date);
    
    if ($stmt->execute()) {
        $report_id = $conn->insert_id;
        
        // Success response
        $response = [
            'status' => 'success',
            'message' => 'Report submitted successfully',
            'data' => [
                'tracking_code' => $tracking_code,
                'report_id' => $report_id
            ]
        ];
        
        // Log the action
        log_action($user_id, "Submitted report via API", $report_id);
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Failed to submit report: ' . $stmt->error,
            'data' => null
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    return $response;
}
?>
