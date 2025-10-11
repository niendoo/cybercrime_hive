<?php
/**
 * Generate a featured image with title text (similar to Ghost CMS)
 */
function generateFeaturedImage($title, $category = '', $width = 800, $height = 400) {
    // Create image
    $image = imagecreatetruecolor($width, $height);
    
    // Define colors based on category
    $colors = [
        'Prevention' => ['bg' => [52, 152, 219], 'text' => [255, 255, 255]], // Blue
        'Reporting Guide' => ['bg' => [46, 204, 113], 'text' => [255, 255, 255]], // Green
        'System Usage' => ['bg' => [155, 89, 182], 'text' => [255, 255, 255]], // Purple
        'Security' => ['bg' => [231, 76, 60], 'text' => [255, 255, 255]], // Red
        'default' => ['bg' => [52, 73, 94], 'text' => [255, 255, 255]] // Dark gray
    ];
    
    $colorScheme = isset($colors[$category]) ? $colors[$category] : $colors['default'];
    
    // Create gradient background
    $bgColor1 = imagecolorallocate($image, $colorScheme['bg'][0], $colorScheme['bg'][1], $colorScheme['bg'][2]);
    $bgColor2 = imagecolorallocate($image, 
        max(0, $colorScheme['bg'][0] - 30), 
        max(0, $colorScheme['bg'][1] - 30), 
        max(0, $colorScheme['bg'][2] - 30)
    );
    
    // Fill with gradient
    for ($i = 0; $i < $height; $i++) {
        $ratio = $i / $height;
        $r = $colorScheme['bg'][0] * (1 - $ratio) + max(0, $colorScheme['bg'][0] - 30) * $ratio;
        $g = $colorScheme['bg'][1] * (1 - $ratio) + max(0, $colorScheme['bg'][1] - 30) * $ratio;
        $b = $colorScheme['bg'][2] * (1 - $ratio) + max(0, $colorScheme['bg'][2] - 30) * $ratio;
        
        $lineColor = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $i, $width, $i, $lineColor);
    }
    
    // Text color
    $textColor = imagecolorallocate($image, $colorScheme['text'][0], $colorScheme['text'][1], $colorScheme['text'][2]);
    
    // Try to use a nice font, fallback to built-in
    $fontFile = $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/assets/fonts/arial.ttf';
    $useCustomFont = file_exists($fontFile);
    
    // Prepare title text
    $title = strtoupper($title);
    $maxLength = 40; // Maximum characters per line
    $lines = [];
    
    if (strlen($title) > $maxLength) {
        $words = explode(' ', $title);
        $currentLine = '';
        
        foreach ($words as $word) {
            if (strlen($currentLine . ' ' . $word) <= $maxLength) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                    $currentLine = $word;
                } else {
                    $lines[] = substr($word, 0, $maxLength);
                }
            }
        }
        if ($currentLine) {
            $lines[] = $currentLine;
        }
    } else {
        $lines[] = $title;
    }
    
    // Limit to 3 lines
    $lines = array_slice($lines, 0, 3);
    
    // Calculate text positioning
    $fontSize = $useCustomFont ? 24 : 5;
    $lineHeight = $useCustomFont ? 35 : 20;
    $totalTextHeight = count($lines) * $lineHeight;
    $startY = ($height - $totalTextHeight) / 2;
    
    // Draw text
    foreach ($lines as $index => $line) {
        $y = $startY + ($index * $lineHeight);
        
        if ($useCustomFont) {
            // Get text dimensions for centering
            $bbox = imagettfbbox($fontSize, 0, $fontFile, $line);
            $textWidth = $bbox[4] - $bbox[0];
            $x = ($width - $textWidth) / 2;
            
            // Add text shadow
            $shadowColor = imagecolorallocate($image, 0, 0, 0);
            imagettftext($image, $fontSize, 0, $x + 2, $y + 2, $shadowColor, $fontFile, $line);
            
            // Add main text
            imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontFile, $line);
        } else {
            // Use built-in font
            $textWidth = strlen($line) * imagefontwidth($fontSize);
            $x = ($width - $textWidth) / 2;
            
            // Add text shadow
            $shadowColor = imagecolorallocate($image, 0, 0, 0);
            imagestring($image, $fontSize, $x + 1, $y + 1, $line, $shadowColor);
            
            // Add main text
            imagestring($image, $fontSize, $x, $y, $line, $textColor);
        }
    }
    
    // Add category badge if provided
    if ($category) {
        $badgeColor = imagecolorallocate($image, 255, 255, 255);
        $badgeTextColor = imagecolorallocate($image, $colorScheme['bg'][0], $colorScheme['bg'][1], $colorScheme['bg'][2]);
        
        $badgeText = strtoupper($category);
        $badgeWidth = strlen($badgeText) * 8 + 20;
        $badgeHeight = 25;
        $badgeX = 20;
        $badgeY = 20;
        
        // Draw badge background
        imagefilledrectangle($image, $badgeX, $badgeY, $badgeX + $badgeWidth, $badgeY + $badgeHeight, $badgeColor);
        
        // Draw badge text
        $textX = $badgeX + 10;
        $textY = $badgeY + 5;
        imagestring($image, 3, $textX, $textY, $badgeText, $badgeTextColor);
    }
    
    return $image;
}

/**
 * Save generated image and return URL
 */
function saveGeneratedImage($title, $category = '') {
    $image = generateFeaturedImage($title, $category);
    
    // Create upload directory if it doesn't exist
    $uploadDir = dirname(__DIR__) . '/uploads/generated/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate filename
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $filename = 'generated_' . $slug . '_' . time() . '.png';
    $filepath = $uploadDir . $filename;
    
    // Save image
    if (imagepng($image, $filepath)) {
        imagedestroy($image);
        return SITE_URL . '/uploads/generated/' . $filename;
    }
    
    imagedestroy($image);
    return false;
}

/**
 * Get or generate featured image for an article
 */
function getFeaturedImageUrl($article) {
    // If article already has a featured image, validate and return it
    if (!empty($article['featured_image'])) {
        $featuredImage = trim($article['featured_image']);
        
        // Get the document root - handle both web and CLI environments
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (empty($documentRoot)) {
            // Fallback for CLI - assume we're in the project directory
            $documentRoot = dirname(dirname(__FILE__));
            if (basename($documentRoot) !== 'cybercrime_hive') {
                $documentRoot = dirname($documentRoot);
            }
        }
        
        // If it's a full URL starting with SITE_URL, validate the file exists
        if (strpos($featuredImage, SITE_URL) === 0) {
            // Extract the path after SITE_URL
            $relativePath = str_replace(SITE_URL, '', $featuredImage);
            $relativePath = ltrim($relativePath, '/');
            
            // Try different path combinations
            $possiblePaths = [
                dirname(__DIR__) . '/' . $relativePath,
                $documentRoot . '/' . $relativePath
            ];
            
            foreach ($possiblePaths as $fullPath) {
                if (file_exists($fullPath)) {
                    return $featuredImage; // Return the full URL as stored
                }
            }
        }
        
        // If it's any other full URL, return it directly (external images)
        if (filter_var($featuredImage, FILTER_VALIDATE_URL)) {
            return $featuredImage;
        }
        
        // If it's a path starting with /cybercrime_hive/, validate and return
        if (strpos($featuredImage, '/cybercrime_hive/') === 0) {
            $possiblePaths = [
                dirname(__DIR__) . str_replace('/cybercrime_hive', '', $featuredImage)
            ];
            
            foreach ($possiblePaths as $testPath) {
                if (file_exists($testPath)) {
                    return SITE_URL . str_replace('/cybercrime_hive', '', $featuredImage);
                }
            }
        }
        
        // If it's a relative path, check if file exists
        $relativePath = ltrim($featuredImage, '/');
        $possiblePaths = [
            dirname(__DIR__) . '/' . $relativePath
        ];
        
        foreach ($possiblePaths as $fullPath) {
            if (file_exists($fullPath)) {
                // Return the full URL
                return SITE_URL . '/' . $relativePath;
            }
        }
    }
    
    // Only generate if no valid featured image exists
    $generatedUrl = saveGeneratedImage($article['title'], $article['category'] ?? '');
    
    if ($generatedUrl) {
        // Only update the database if there was no existing featured_image
        if (empty($article['featured_image'])) {
            try {
                $conn = get_database_connection();
                $stmt = $conn->prepare("UPDATE knowledge_base SET featured_image = ? WHERE kb_id = ?");
                $stmt->bind_param('si', $generatedUrl, $article['kb_id']);
                $stmt->execute();
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                // Log error but continue
                error_log("Failed to update featured image for article {$article['kb_id']}: " . $e->getMessage());
            }
        }
        
        return $generatedUrl;
    }
    
    // Fallback to a default placeholder
    return SITE_URL . '/assets/images/default-article.svg';
}
?>