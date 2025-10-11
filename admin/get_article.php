<?php
/**
 * Get Article Data for AJAX Editing
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Article ID required']);
    exit();
}

$kb_id = intval($_GET['id']);
$conn = get_database_connection();

$stmt = $conn->prepare("SELECT * FROM knowledge_base WHERE kb_id = ?");
$stmt->bind_param("i", $kb_id);
$stmt->execute();
$result = $stmt->get_result();

if ($article = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'article' => $article]);
} else {
    echo json_encode(['success' => false, 'message' => 'Article not found']);
}

$stmt->close();
$conn->close();
?>
