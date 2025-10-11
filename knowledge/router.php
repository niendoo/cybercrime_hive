<?php
// Simple router for clean URLs
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Check if we're accessing a clean URL
if (count($path_parts) >= 2 && $path_parts[0] === 'knowledge' && !empty($path_parts[1])) {
    $slug = sanitize_input($path_parts[1]);
    
    // Check if it's a slug (not a file)
    if (!file_exists(__DIR__ . '/' . $path_parts[1])) {
        $_GET['slug'] = $slug;
        include 'article.php';
        exit();
    }
}

// Check for root-level clean URLs
if (count($path_parts) === 1 && !empty($path_parts[0]) && !file_exists(__DIR__ . '/../' . $path_parts[0])) {
    $slug = sanitize_input($path_parts[0]);
    $_GET['slug'] = $slug;
    include 'article.php';
    exit();
}

// Fall back to normal processing
return false;
?>
