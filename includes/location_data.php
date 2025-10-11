<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Get all regions from the database
 * @return array Array of regions with id and name
 */
function get_all_regions() {
    $conn = get_database_connection();
    $stmt = $conn->prepare("SELECT id, name FROM regions ORDER BY name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $regions = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $regions;
}

/**
 * Get districts for a specific region
 * @param int $region_id The region ID
 * @return array Array of districts with id and name
 */
function get_districts_by_region($region_id) {
    $conn = get_database_connection();
    $stmt = $conn->prepare("SELECT id, name FROM districts WHERE region_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $region_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $districts = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $districts;
}

/**
 * Get region name by ID
 * @param int $region_id The region ID
 * @return string|null Region name or null if not found
 */
function get_region_name($region_id) {
    $conn = get_database_connection();
    $stmt = $conn->prepare("SELECT name FROM regions WHERE id = ?");
    $stmt->bind_param("i", $region_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $region = $result->fetch_assoc();
    $conn->close();
    return $region ? $region['name'] : null;
}

/**
 * Get district name by ID
 * @param int $district_id The district ID
 * @return string|null District name or null if not found
 */
function get_district_name($district_id) {
    $conn = get_database_connection();
    $stmt = $conn->prepare("SELECT name FROM districts WHERE id = ?");
    $stmt->bind_param("i", $district_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $district = $result->fetch_assoc();
    $conn->close();
    return $district ? $district['name'] : null;
}

/**
 * Get district and region info by district ID
 * @param int $district_id The district ID
 * @return array|null Array with district and region info or null if not found
 */
function get_location_info($district_id) {
    $conn = get_database_connection();
    $stmt = $conn->prepare("SELECT 
        d.id as district_id, 
        d.name as district_name,
        r.id as region_id,
        r.name as region_name
        FROM districts d
        JOIN regions r ON d.region_id = r.id
        WHERE d.id = ?");
    $stmt->bind_param("i", $district_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $location = $result->fetch_assoc();
    $conn->close();
    return $location;
}
?>
