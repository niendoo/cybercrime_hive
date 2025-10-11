<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

// Check if region_id is provided
if (!isset($_GET['region_id']) || empty($_GET['region_id'])) {
    echo json_encode(['success' => false, 'message' => 'Region ID is required']);
    exit;
}

$region_id = intval($_GET['region_id']);

$conn = get_database_connection();

// Fetch districts for the given region
$stmt = $conn->prepare("SELECT id, name FROM districts WHERE region_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $region_id);
$stmt->execute();
$result = $stmt->get_result();

$districts = [];
while ($row = $result->fetch_assoc()) {
    $districts[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

$conn->close();

echo json_encode([
    'success' => true,
    'districts' => $districts
]);
?>
