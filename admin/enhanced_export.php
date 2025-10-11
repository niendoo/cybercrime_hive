<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in and is admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Try to load a site logo from assets and return it as a data URI for embedding into PDFs.
 * Supports PNG/JPG/SVG. Returns empty string if not found.
 */
function get_logo_data_uri() {
    $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/assets/images/';
    $candidates = [
        'logo.png' => 'image/png',
        'logo.jpg' => 'image/jpeg',
        'logo.jpeg' => 'image/jpeg',
        'logo.svg' => 'image/svg+xml',
    ];
    foreach ($candidates as $file => $mime) {
        $path = $baseDir . $file;
        if (file_exists($path)) {
            $data = file_get_contents($path);
            if ($data !== false) {
                $base64 = base64_encode($data);
                return 'data:' . $mime . ';base64,' . $base64;
            }
        }
    }
    return '';
}

/**
 * Build a consistent branded header block for PDFs with logo, site name, title, and timestamp.
 */
function build_pdf_header($titleText) {
    $logo = get_logo_data_uri();
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'Site';
    $generated = date('Y-m-d H:i:s');
    $logoImg = $logo ? '<img src="' . $logo . '" alt="' . htmlspecialchars($siteName) . '" style="height:40px; width:auto; margin-right:12px;" />' : '';
    return '<div class="brand-header">'
        . ($logoImg ? '<div class="brand-logo">' . $logoImg . '</div>' : '')
        . '<div class="brand-meta">'
        . '<div class="brand-title">' . htmlspecialchars($siteName) . '</div>'
        . '<div class="report-title">' . htmlspecialchars($titleText) . '</div>'
        . '<div class="generated">Generated on: ' . $generated . '</div>'
        . '</div>'
        . '</div>';
}

/**
 * Render HTML as a real PDF file using Dompdf and stream it to the browser
 * @param string $html The full HTML document to render
 * @param string $filename The filename for download (should end with .pdf)
 */
function render_pdf($html, $filename, $orientation = 'portrait') {
    // Clean any previous output to avoid corrupting PDF bytes
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', $orientation);
    $dompdf->render();
    // Add page numbers footer centered at bottom
    $canvas = $dompdf->get_canvas();
    if ($canvas) {
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
        $size = 8;
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
        $textWidth = $fontMetrics->getTextWidth($text, $font, $size);
        $x = ($w - $textWidth) / 2;
        $y = $h - 28; // 28pt from bottom
        $canvas->page_text($x, $y, $text, $font, $size, [0,0,0]);
    }
    // Stream as attachment (download)
    $dompdf->stream($filename, ['Attachment' => true]);
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$export_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'csv';
$export_scope = isset($_GET['scope']) ? sanitize_input($_GET['scope']) : 'reports';
$analytics_section = isset($_GET['section']) ? sanitize_input($_GET['section']) : 'all'; // 'category', 'status', or 'all'
$date_range = isset($_GET['date_range']) ? sanitize_input($_GET['date_range']) : 'all';
$category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$region_id = isset($_GET['region_id']) ? (int)$_GET['region_id'] : '';
$district_id = isset($_GET['district_id']) ? (int)$_GET['district_id'] : '';

// If export is requested, generate the file
if (isset($_GET['export'])) {
    $conn = get_database_connection();
    
    switch ($export_scope) {
        case 'reports':
            export_reports($conn, $export_type, $date_range, $category, $status, $region_id, $district_id);
            break;
        case 'regional_summary':
            export_regional_summary($conn, $export_type, $date_range);
            break;
        case 'district_summary':
            export_district_summary($conn, $export_type, $date_range, $region_id);
            break;
        case 'analytics':
            export_analytics($conn, $export_type, $date_range, $analytics_section);
            break;
    }
    
    $conn->close();
    exit();
}

function export_reports($conn, $export_type, $date_range, $category, $status, $region_id, $district_id) {
    $query = "SELECT r.report_id, r.tracking_code, r.title, r.description, r.category, 
              r.incident_date, r.status, r.created_at, r.updated_at, 
              u.username, u.email, u.phone,
              reg.name as region_name, dist.name as district_name
              FROM reports r 
              JOIN users u ON r.user_id = u.user_id
              LEFT JOIN regions reg ON r.region_id = reg.id
              LEFT JOIN districts dist ON r.district_id = dist.id";
    
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
        $where_clauses[] = "r.category = '" . $conn->real_escape_string($category) . "'";
    }
    
    // Status filter
    if (!empty($status)) {
        $where_clauses[] = "r.status = '" . $conn->real_escape_string($status) . "'";
    }
    
    // Region filter
    if (!empty($region_id)) {
        $where_clauses[] = "r.region_id = $region_id";
    }
    
    // District filter
    if (!empty($district_id)) {
        $where_clauses[] = "r.district_id = $district_id";
    }
    
    // Finalize query
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $query .= " ORDER BY r.created_at DESC";
    
    $result = $conn->query($query);
    
    if ($export_type == 'csv') {
        export_reports_csv($result);
    } elseif ($export_type == 'pdf') {
        export_reports_pdf($result);
    }
}

function export_reports_csv($result) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cybercrime_reports_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, "\xEF\xBB\xBF"); // BOM for UTF-8
    
    fputcsv($output, [
        'Report ID', 'Tracking Code', 'Title', 'Description', 'Category',
        'Incident Date', 'Status', 'Created At', 'Updated At',
        'Region', 'District', 'Reported By', 'Email', 'Phone'
    ]);
    
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
            $row['region_name'] ?? 'N/A',
            $row['district_name'] ?? 'N/A',
            $row['username'],
            $row['email'],
            $row['phone']
        ]);
    }
    
    fclose($output);
}

function export_reports_pdf($result) {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
        . '<title>Cybercrime Reports Export</title>'
        . '<style>'
        . 'body { font-family: DejaVu Sans, Arial, sans-serif; margin: 24px; }'
        . '.brand-header { display: flex; align-items: center; border-bottom: 2px solid #4e73df; padding-bottom: 10px; margin-bottom: 16px; }'
        . '.brand-title { font-size: 14px; color: #4e73df; font-weight: 700; }'
        . '.report-title { font-size: 18px; font-weight: 700; color: #333; }'
        . '.generated { font-size: 12px; color: #666; }'
        . 'table { width: 100%; border-collapse: collapse; margin-top: 12px; }'
        . 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }'
        . 'th { background-color: #f8f9fc; font-weight: bold; }'
        . 'thead { display: table-header-group; }'
        . 'tfoot { display: table-row-group; }'
        . 'tr { page-break-inside: avoid; }'
        . '</style></head><body>';

    $html .= build_pdf_header('Cybercrime Reports Export');
    $html .= '<div style="font-size:12px; color:#666; margin-top:4px;">Total Reports: ' . $result->num_rows . '</div>';

    $html .= '<table>'
        . '<thead><tr>'
        . '<th>ID</th><th>Tracking Code</th><th>Title</th><th>Category</th>'
        . '<th>Status</th><th>Region</th><th>District</th><th>Created</th><th>Reporter</th>'
        . '</tr></thead><tbody>';

    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>'
            . '<td>' . htmlspecialchars($row['report_id']) . '</td>'
            . '<td>' . htmlspecialchars($row['tracking_code']) . '</td>'
            . '<td>' . htmlspecialchars(substr($row['title'], 0, 50)) . '</td>'
            . '<td>' . htmlspecialchars($row['category']) . '</td>'
            . '<td>' . htmlspecialchars($row['status']) . '</td>'
            . '<td>' . htmlspecialchars($row['region_name'] ?? 'N/A') . '</td>'
            . '<td>' . htmlspecialchars($row['district_name'] ?? 'N/A') . '</td>'
            . '<td>' . htmlspecialchars($row['created_at']) . '</td>'
            . '<td>' . htmlspecialchars($row['username']) . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table></body></html>';
    render_pdf($html, 'cybercrime_reports_' . date('Y-m-d') . '.pdf');
}

function export_regional_summary($conn, $export_type, $date_range) {
    $date_filter = get_date_filter($date_range);
    
    $query = "SELECT reg.name as region_name, reg.id as region_id, 
              COUNT(r.report_id) as total_reports,
              GROUP_CONCAT(DISTINCT r.category) as categories,
              COUNT(CASE WHEN r.status = 'Submitted' THEN 1 END) as submitted,
              COUNT(CASE WHEN r.status = 'Under Review' THEN 1 END) as under_review,
              COUNT(CASE WHEN r.status = 'In Investigation' THEN 1 END) as in_investigation,
              COUNT(CASE WHEN r.status = 'Resolved' THEN 1 END) as resolved
              FROM regions reg
              LEFT JOIN reports r ON reg.id = r.region_id";
    
    if ($date_filter) {
        $query .= " WHERE $date_filter";
    }
    
    $query .= " GROUP BY reg.id, reg.name ORDER BY total_reports DESC";
    
    $result = $conn->query($query);
    
    if ($export_type == 'csv') {
        export_regional_csv($result);
    } else {
        export_regional_pdf($result);
    }
}

function export_district_summary($conn, $export_type, $date_range, $region_id) {
    $date_filter = get_date_filter($date_range);
    
    $query = "SELECT dist.name as district_name, dist.id as district_id, reg.name as region_name,
              COUNT(r.report_id) as total_reports,
              COUNT(CASE WHEN r.status = 'Submitted' THEN 1 END) as submitted,
              COUNT(CASE WHEN r.status = 'Under Review' THEN 1 END) as under_review,
              COUNT(CASE WHEN r.status = 'In Investigation' THEN 1 END) as in_investigation,
              COUNT(CASE WHEN r.status = 'Resolved' THEN 1 END) as resolved
              FROM districts dist
              JOIN regions reg ON dist.region_id = reg.id
              LEFT JOIN reports r ON dist.id = r.district_id";
    
    $where_clauses = [];
    if ($date_filter) {
        $where_clauses[] = $date_filter;
    }
    if ($region_id) {
        $where_clauses[] = "reg.id = $region_id";
    }
    
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $query .= " GROUP BY dist.id, dist.name, reg.name ORDER BY total_reports DESC";
    
    $result = $conn->query($query);
    
    if ($export_type == 'csv') {
        export_district_csv($result);
    } else {
        export_district_pdf($result);
    }
}

function export_analytics($conn, $export_type, $date_range, $section = 'all') {
    $date_filter = get_date_filter($date_range);
    
    // Get summary data
    $summary_query = "SELECT 
        COUNT(*) as total_reports,
        COUNT(DISTINCT category) as total_categories,
        COUNT(DISTINCT region_id) as total_regions,
        COUNT(DISTINCT district_id) as total_districts
        FROM reports";
    
    if ($date_filter) {
        $summary_query .= " WHERE $date_filter";
    }
    
    $summary = $conn->query($summary_query)->fetch_assoc();
    
    // Get category breakdown
    $category_query = "SELECT category, COUNT(*) as count FROM reports";
    if ($date_filter) {
        $category_query .= " WHERE $date_filter";
    }
    $category_query .= " GROUP BY category ORDER BY count DESC";
    
    $categories = $conn->query($category_query);
    
    // Get status breakdown
    $status_query = "SELECT status, COUNT(*) as count FROM reports";
    if ($date_filter) {
        $status_query .= " WHERE $date_filter";
    }
    $status_query .= " GROUP BY status ORDER BY count DESC";
    
    $statuses = $conn->query($status_query);
    
    if ($export_type == 'csv') {
        export_analytics_csv($summary, $categories, $statuses, $section);
    } else {
        export_analytics_pdf($summary, $categories, $statuses, $section);
    }
}

function get_date_filter($date_range) {
    switch ($date_range) {
        case 'today':
            return "DATE(created_at) = CURDATE()";
        case 'week':
            return "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        case 'month':
            return "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        case 'year':
            return "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        default:
            return '';
    }
}

function export_regional_csv($result) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=regional_summary_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, "\xEF\xBB\xBF");
    
    fputcsv($output, [
        'Region', 'Total Reports', 'Submitted', 'Under Review', 
        'In Investigation', 'Resolved', 'Categories'
    ]);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['region_name'],
            $row['total_reports'],
            $row['submitted'],
            $row['under_review'],
            $row['in_investigation'],
            $row['resolved'],
            $row['categories'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
}

function export_district_csv($result) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=district_summary_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, "\xEF\xBB\xBF");
    
    fputcsv($output, [
        'Region', 'District', 'Total Reports', 'Submitted', 'Under Review', 
        'In Investigation', 'Resolved'
    ]);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['region_name'],
            $row['district_name'],
            $row['total_reports'],
            $row['submitted'],
            $row['under_review'],
            $row['in_investigation'],
            $row['resolved']
        ]);
    }
    
    fclose($output);
}

function export_analytics_csv($summary, $categories, $statuses, $section = 'all') {
    header('Content-Type: text/csv; charset=utf-8');
    $suffix = ($section === 'category') ? '_category' : (($section === 'status') ? '_status' : '_summary');
    header('Content-Disposition: attachment; filename=analytics' . $suffix . '_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, "\xEF\xBB\xBF");
    
    if ($section === 'all' || $section === 'summary') {
        // Summary section
        fputcsv($output, ['Summary Metrics']);
        fputcsv($output, ['Total Reports', $summary['total_reports']]);
        fputcsv($output, ['Total Categories', $summary['total_categories']]);
        fputcsv($output, ['Total Regions', $summary['total_regions']]);
        fputcsv($output, ['Total Districts', $summary['total_districts']]);
        fputcsv($output, []);
    }

    if ($section === 'all' || $section === 'category') {
        // Categories breakdown
        fputcsv($output, ['Category Breakdown']);
        fputcsv($output, ['Category', 'Count', 'Percentage']);
        while ($cat = $categories->fetch_assoc()) {
            $percentage = $summary['total_reports'] > 0 ? round(($cat['count'] / $summary['total_reports']) * 100, 2) : 0;
            fputcsv($output, [$cat['category'], $cat['count'], $percentage . '%']);
        }
        fputcsv($output, []);
        // Reset pointer not necessary since we consumed categories for category-only
    }

    if ($section === 'all' || $section === 'status') {
        // Status breakdown
        fputcsv($output, ['Status Breakdown']);
        fputcsv($output, ['Status', 'Count', 'Percentage']);
        while ($status = $statuses->fetch_assoc()) {
            $percentage = $summary['total_reports'] > 0 ? round(($status['count'] / $summary['total_reports']) * 100, 2) : 0;
            fputcsv($output, [$status['status'], $status['count'], $percentage . '%']);
        }
    }
    
    fclose($output);
}

function export_regional_pdf($result) {
    export_summary_pdf($result, 'Regional Summary Report', [
        'Region', 'Total', 'Submitted', 'Review', 'Investigation', 'Resolved'
    ], 'region_name');
}

function export_district_pdf($result) {
    export_summary_pdf($result, 'District Summary Report', [
        'Region', 'District', 'Total', 'Submitted', 'Review', 'Investigation', 'Resolved'
    ], 'district_name', 'landscape');
}

function export_analytics_pdf($summary, $categories, $statuses, $section = 'all') {
    $titleText = ($section === 'category') ? 'Category Analytics' : (($section === 'status') ? 'Status Analytics' : 'Analytics Summary Report');
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
        . '<title>' . htmlspecialchars($titleText) . '</title>'
        . '<style>'
        . 'body { font-family: DejaVu Sans, Arial, sans-serif; margin: 24px; }'
        . '.brand-header { display: flex; align-items: center; border-bottom: 2px solid #4e73df; padding-bottom: 10px; margin-bottom: 16px; }'
        . '.brand-title { font-size: 14px; color: #4e73df; font-weight: 700; }'
        . '.report-title { font-size: 18px; font-weight: 700; color: #333; }'
        . '.generated { font-size: 12px; color: #666; }'
        . '.summary-box { background: #f8f9fc; padding: 12px; margin: 10px 0; border-radius: 5px; }'
        . 'table { width: 100%; border-collapse: collapse; margin: 12px 0; }'
        . 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }'
        . 'th { background-color: #f8f9fc; font-weight: bold; }'
        . 'thead { display: table-header-group; }'
        . 'tfoot { display: table-row-group; }'
        . 'tr { page-break-inside: avoid; }'
        . '.metric { font-size: 18px; font-weight: bold; color: #4e73df; }'
        . '</style></head><body>';

    $html .= build_pdf_header('Cybercrime ' . htmlspecialchars($titleText));

    if ($section === 'all' || $section === 'summary') {
        $html .= '<div class="summary-box">'
            . '<h2>Summary Metrics</h2>'
            . '<p><span class="metric">' . $summary['total_reports'] . '</span> Total Reports</p>'
            . '<p><span class="metric">' . $summary['total_categories'] . '</span> Categories</p>'
            . '<p><span class="metric">' . $summary['total_regions'] . '</span> Regions</p>'
            . '<p><span class="metric">' . $summary['total_districts'] . '</span> Districts</p>'
            . '</div>';
    }

    if ($section === 'all' || $section === 'category') {
        $html .= '<h2>Category Distribution</h2>'
            . '<table><thead><tr><th>Category</th><th>Count</th><th>Percentage</th></tr></thead><tbody>';
        while ($cat = $categories->fetch_assoc()) {
            $percentage = $summary['total_reports'] > 0 ? round(($cat['count'] / $summary['total_reports']) * 100, 2) : 0;
            $html .= '<tr><td>' . htmlspecialchars($cat['category']) . '</td>'
                . '<td>' . $cat['count'] . '</td>'
                . '<td>' . $percentage . '%</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    if ($section === 'all' || $section === 'status') {
        $html .= '<h2>Status Distribution</h2>'
            . '<table><thead><tr><th>Status</th><th>Count</th><th>Percentage</th></tr></thead><tbody>';
        while ($status = $statuses->fetch_assoc()) {
            $percentage = $summary['total_reports'] > 0 ? round(($status['count'] / $summary['total_reports']) * 100, 2) : 0;
            $html .= '<tr><td>' . htmlspecialchars($status['status']) . '</td>'
                . '<td>' . $status['count'] . '</td>'
                . '<td>' . $percentage . '%</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</body></html>';
    render_pdf($html, 'analytics_summary_' . date('Y-m-d') . '.pdf');
}

function export_summary_pdf($result, $title, $headers, $name_field, $orientation = 'portrait') {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
        . '<title>' . htmlspecialchars($title) . '</title>'
        . '<style>'
        . 'body { font-family: DejaVu Sans, Arial, sans-serif; margin: 24px; }'
        . '.brand-header { display: flex; align-items: center; border-bottom: 2px solid #4e73df; padding-bottom: 10px; margin-bottom: 16px; }'
        . '.brand-title { font-size: 14px; color: #4e73df; font-weight: 700; }'
        . '.report-title { font-size: 18px; font-weight: 700; color: #333; }'
        . '.generated { font-size: 12px; color: #666; }'
        . 'table { width: 100%; border-collapse: collapse; margin-top: 12px; }'
        . 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }'
        . 'th { background-color: #f8f9fc; font-weight: bold; }'
        . 'thead { display: table-header-group; }'
        . 'tfoot { display: table-row-group; }'
        . 'tr { page-break-inside: avoid; }'
        . '</style></head><body>';

    $html .= build_pdf_header($title);
    $html .= '<div style="font-size:12px; color:#666; margin-top:4px;">Total Records: ' . $result->num_rows . '</div>';

    $html .= '<table><thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>';
        if ($title == 'District Summary Report') {
            $html .= '<td>' . htmlspecialchars($row['region_name']) . '</td>';
        }
        $html .= '<td>' . htmlspecialchars($row[$name_field]) . '</td>'
            . '<td>' . $row['total_reports'] . '</td>'
            . '<td>' . $row['submitted'] . '</td>'
            . '<td>' . $row['under_review'] . '</td>'
            . '<td>' . $row['in_investigation'] . '</td>'
            . '<td>' . $row['resolved'] . '</td>'
            . '</tr>';
    }

    $html .= '</tbody></table></body></html>';
    $safe = str_replace(' ', '_', strtolower($title));
    render_pdf($html, $safe . '_' . date('Y-m-d') . '.pdf', $orientation);
}

// Log admin action
log_admin_action($admin_id, "Accessed enhanced export page", null);

// Include header
include dirname(__DIR__) . '/includes/header.php';

// Get regions and districts for filters
$conn = get_database_connection();
$regions = $conn->query("SELECT id, name FROM regions ORDER BY name");
$districts = $conn->query("SELECT id, name, region_id FROM districts ORDER BY name");
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Export Reports</li>
            </ol>
        </nav>
        
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Export Reports & Analytics</h1>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Export Options</h6>
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <input type="hidden" name="export" value="1">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="scope" class="form-label">Export Scope</label>
                            <select class="form-select" id="scope" name="scope" required>
                                <option value="reports">Detailed Reports</option>
                                <option value="regional_summary">Regional Summary</option>
                                <option value="district_summary">District Summary</option>
                                <option value="analytics">Analytics Overview</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="type" class="form-label">Export Format</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="csv">CSV (Spreadsheet)</option>
                                <option value="pdf">PDF (Document)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_range" class="form-label">Date Range</label>
                            <select class="form-select" id="date_range" name="date_range">
                                <option value="all">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">Last 7 Days</option>
                                <option value="month">Last 30 Days</option>
                                <option value="year">Last 12 Months</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category Filter</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <option value="Phishing">Phishing</option>
                                <option value="Hacking">Hacking</option>
                                <option value="Fraud">Fraud</option>
                                <option value="Malware">Malware</option>
                                <option value="Identity Theft">Identity Theft</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status Filter</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Under Review">Under Review</option>
                                <option value="In Investigation">In Investigation</option>
                                <option value="Resolved">Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="region_id" class="form-label">Region Filter</label>
                            <select class="form-select" id="region_id" name="region_id">
                                <option value="">All Regions</option>
                                <?php while($region = $regions->fetch_assoc()): ?>
                                    <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="district_container" style="display: none;">
                        <div class="col-md-6">
                            <label for="district_id" class="form-label">District Filter</label>
                            <select class="form-select" id="district_id" name="district_id">
                                <option value="">All Districts</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="alert-heading">Export Information</h5>
                                <p class="mb-0">Choose your export scope and format. CSV files are ideal for spreadsheet analysis, while PDF files provide formatted reports.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-export me-2"></i>Export Data
                        </button>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Export Types</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6><i class="fas fa-file-csv text-success"></i> CSV Exports</h6>
                    <ul class="list-unstyled small">
                        <li>• Compatible with Excel, Google Sheets</li>
                        <li>• Easy data manipulation and analysis</li>
                        <li>• Raw data format</li>
                    </ul>
                </div>
                <div class="mb-3">
                    <h6><i class="fas fa-file-pdf text-danger"></i> PDF Exports</h6>
                    <ul class="list-unstyled small">
                        <li>• Professional formatted reports</li>
                        <li>• Print-ready documents</li>
                        <li>• Preserved formatting</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const regionSelect = document.getElementById('region_id');
    const districtSelect = document.getElementById('district_id');
    const districtContainer = document.getElementById('district_container');
    
    const districtsByRegion = <?php 
        $districts_data = [];
        $districts_result = $conn->query("SELECT id, name, region_id FROM districts ORDER BY name");
        while($d = $districts_result->fetch_assoc()) {
            $districts_data[$d['region_id']][] = ['id' => $d['id'], 'name' => $d['name']];
        }
        echo json_encode($districts_data);
    ?>;
    
    regionSelect.addEventListener('change', function() {
        const regionId = this.value;
        districtSelect.innerHTML = '<option value="">All Districts</option>';
        
        if (regionId) {
            districtContainer.style.display = 'block';
            const districts = districtsByRegion[regionId] || [];
            districts.forEach(district => {
                const option = document.createElement('option');
                option.value = district.id;
                option.textContent = district.name;
                districtSelect.appendChild(option);
            });
        } else {
            districtContainer.style.display = 'none';
        }
    });
});
</script>

<?php 
$conn->close();
include dirname(__DIR__) . '/includes/footer.php'; 
?>
