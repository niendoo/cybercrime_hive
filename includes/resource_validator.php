<?php
/**
 * Server-side Resource Validation Utility
 * Validates that referenced CSS, JS, and other assets exist
 * Author: CyberCrime Hive Team
 * Version: 1.0
 */

class ResourceValidator {
    private $documentRoot;
    private $siteUrl;
    private $missingResources = [];
    
    public function __construct($documentRoot = null, $siteUrl = null) {
        $this->documentRoot = $documentRoot ?: $_SERVER['DOCUMENT_ROOT'];
        $this->siteUrl = $siteUrl ?: (defined('SITE_URL') ? SITE_URL : '');
    }
    
    /**
     * Validate a single resource file
     * @param string $resourcePath - Path to the resource file
     * @param string $type - Type of resource (css, js, image, etc.)
     * @return bool - True if resource exists, false otherwise
     */
    public function validateResource($resourcePath, $type = 'unknown') {
        // Convert URL to file path if needed
        if (strpos($resourcePath, 'http') === 0) {
            // External resource - skip validation
            return true;
        }
        
        // Remove site URL prefix if present
        if ($this->siteUrl && strpos($resourcePath, $this->siteUrl) === 0) {
            $resourcePath = substr($resourcePath, strlen($this->siteUrl));
        }
        
        // Ensure path starts with /
        if (strpos($resourcePath, '/') !== 0) {
            $resourcePath = '/' . $resourcePath;
        }
        
        $fullPath = $this->documentRoot . $resourcePath;
        $exists = file_exists($fullPath);
        
        if (!$exists) {
            $this->missingResources[] = [
                'path' => $resourcePath,
                'fullPath' => $fullPath,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return $exists;
    }
    
    /**
     * Validate resources referenced in a PHP file
     * @param string $filePath - Path to the PHP file to scan
     * @return array - Array of validation results
     */
    public function validateFileReferences($filePath) {
        if (!file_exists($filePath)) {
            return ['error' => 'File not found: ' . $filePath];
        }
        
        $content = file_get_contents($filePath);
        $results = [];
        
        // Find CSS references
        preg_match_all('/href=["\']([^"\']*\.css[^"\']*)["\']/', $content, $cssMatches);
        foreach ($cssMatches[1] as $cssPath) {
            if (strpos($cssPath, 'http') !== 0) { // Skip external URLs
                $results['css'][] = [
                    'path' => $cssPath,
                    'valid' => $this->validateResource($cssPath, 'css')
                ];
            }
        }
        
        // Find JS references
        preg_match_all('/src=["\']([^"\']*\.js[^"\']*)["\']/', $content, $jsMatches);
        foreach ($jsMatches[1] as $jsPath) {
            if (strpos($jsPath, 'http') !== 0) { // Skip external URLs
                $results['js'][] = [
                    'path' => $jsPath,
                    'valid' => $this->validateResource($jsPath, 'js')
                ];
            }
        }
        
        // Find image references
        preg_match_all('/src=["\']([^"\']*\.(jpg|jpeg|png|gif|svg|webp)[^"\']*)["\']/', $content, $imgMatches);
        foreach ($imgMatches[1] as $imgPath) {
            if (strpos($imgPath, 'http') !== 0) { // Skip external URLs
                $results['images'][] = [
                    'path' => $imgPath,
                    'valid' => $this->validateResource($imgPath, 'image')
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Validate all common template files
     * @return array - Comprehensive validation results
     */
    public function validateCommonFiles() {
        $filesToCheck = [
            'includes/header.php',
            'includes/footer.php'
        ];
        
        $results = [];
        foreach ($filesToCheck as $file) {
            $fullPath = $this->documentRoot . '/cybercrime_hive/' . $file;
            if (file_exists($fullPath)) {
                $results[$file] = $this->validateFileReferences($fullPath);
            }
        }
        
        return $results;
    }
    
    /**
     * Get all missing resources found during validation
     * @return array - Array of missing resources
     */
    public function getMissingResources() {
        return $this->missingResources;
    }
    
    /**
     * Generate a report of missing resources
     * @return string - HTML report
     */
    public function generateReport() {
        if (empty($this->missingResources)) {
            return '<div class="alert alert-success">All resources validated successfully!</div>';
        }
        
        $report = '<div class="alert alert-warning">';
        $report .= '<h5>Missing Resources Found (' . count($this->missingResources) . '):</h5>';
        $report .= '<ul>';
        
        foreach ($this->missingResources as $resource) {
            $report .= '<li>';
            $report .= '<strong>' . strtoupper($resource['type']) . ':</strong> ';
            $report .= htmlspecialchars($resource['path']);
            $report .= ' <small class="text-muted">(' . $resource['timestamp'] . ')</small>';
            $report .= '</li>';
        }
        
        $report .= '</ul></div>';
        return $report;
    }
    
    /**
     * Log missing resources to error log
     */
    public function logMissingResources() {
        if (!empty($this->missingResources)) {
            $message = 'Missing resources found: ' . json_encode($this->missingResources);
            error_log($message);
        }
    }
}

// Function to quickly validate current page resources
function validate_page_resources() {
    $validator = new ResourceValidator();
    $results = $validator->validateCommonFiles();
    
    if (!empty($validator->getMissingResources())) {
        $validator->logMissingResources();
    }
    
    return $results;
}
?>