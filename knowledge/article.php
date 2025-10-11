<?php
/**
 * Public Article View
 * Displays individual knowledge base articles
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle both clean URLs and query parameter URLs
$slug = '';

// Check for clean URL format: /knowledge/slug
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path ?? '', '/'));

if (count($path_parts) >= 2 && $path_parts[0] === 'knowledge' && !empty($path_parts[1])) {
    // Clean URL: /knowledge/slug
    $slug = sanitize_input($path_parts[1]);
} elseif (count($path_parts) === 1 && !empty($path_parts[0]) && strpos($request_uri, 'article.php') === false) {
    // Root-level clean URL: /slug
    $slug = sanitize_input($path_parts[0]);
} else {
    // Old format: article.php?slug=slug
    $slug = isset($_GET['slug']) ? sanitize_input($_GET['slug']) : '';
    
    // Redirect old format to clean format
    if (!empty($slug) && strpos($request_uri, 'article.php?slug=') !== false) {
        $clean_url = SITE_URL . "/knowledge/$slug";
        header("Location: $clean_url", true, 301);
        exit();
    }
}

if (empty($slug)) {
    header("Location: " . SITE_URL . "/knowledge/index.php");
    exit();
}

$conn = get_database_connection();

// Get article by slug
$stmt = $conn->prepare("SELECT kb.*, u.username as author_name FROM knowledge_base kb 
                       LEFT JOIN users u ON kb.author_id = u.user_id 
                       WHERE kb.slug = ? AND kb.status = 'published'");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$article = $result->fetch_assoc();
$stmt->close();

if (!$article) {
    http_response_code(404);
    include dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4">Article Not Found</h1>
                <p class="lead">The article you're looking for doesn't exist or has been moved.</p>
                <a href="<?php echo SITE_URL; ?>/knowledge/index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Knowledge Base
                </a>
            </div>
        </div>
    </div>
    <?php
    include dirname(__DIR__) . '/includes/footer.php';
    exit();
}

// Update view count
$update_stmt = $conn->prepare("UPDATE knowledge_base SET view_count = view_count + 1 WHERE kb_id = ?");
$update_stmt->bind_param("i", $article['kb_id']);
$update_stmt->execute();
$update_stmt->close();

// Get related articles
$tags = explode(',', $article['tags'] ?? '');
$related_articles = [];
if (!empty($tags[0])) {
    $tag_conditions = array_fill(0, count($tags), "tags LIKE ?");
    $tag_params = array_map(function($tag) { return "%$tag%"; }, $tags);
    
    $related_query = "SELECT kb_id, title, slug, excerpt, featured_image FROM knowledge_base 
                     WHERE kb_id != ? AND status = 'published' AND (" . implode(" OR ", $tag_conditions) . ")
                     ORDER BY updated_at DESC LIMIT 3";
    
    $related_stmt = $conn->prepare($related_query);
    $types = str_repeat('s', count($tags));
    $related_stmt->bind_param("i$types", $article['kb_id'], ...$tag_params);
    $related_stmt->execute();
    $related_result = $related_stmt->get_result();
    $related_articles = $related_result->fetch_all(MYSQLI_ASSOC);
    $related_stmt->close();
}

// Get categories for sidebar
$categories_result = $conn->query("SELECT DISTINCT category, COUNT(*) as count FROM knowledge_base 
                                  WHERE status = 'published' GROUP BY category ORDER BY category");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Get recent articles
$recent_result = $conn->query("SELECT kb_id, title, slug, updated_at FROM knowledge_base 
                              WHERE status = 'published' ORDER BY updated_at DESC LIMIT 5");
$recent_articles = $recent_result->fetch_all(MYSQLI_ASSOC);

$conn->close();

$title = $article['title'];
$description = $article['excerpt'];
$og_image = $article['featured_image'];

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/knowledge/index.php">Knowledge Base</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($article['title']); ?></li>
                </ol>
            </nav>
            
            <!-- Article Header -->
            <article class="card shadow-sm">
                <?php if ($article['featured_image']): ?>
                    <img src="<?php echo htmlspecialchars($article['featured_image'] ?? ''); ?>" 
                         alt="<?php echo htmlspecialchars($article['title'] ?? ''); ?>" 
                         class="card-img-top">
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($article['category'] ?? ''); ?></span>
                            <h1 class="h2 mt-2 mb-1"><?php echo htmlspecialchars($article['title']); ?></h1>
                            <div class="text-muted small">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($article['author_name'] ?? 'Admin'); ?>
                                <i class="fas fa-calendar ms-3 me-1"></i><?php echo date('F j, Y', strtotime($article['updated_at'] ?? 'now')); ?>
                                <i class="fas fa-eye ms-3 me-1"></i><?php echo number_format($article['view_count']); ?> views
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($article['tags']): ?>
                        <div class="mb-3">
                            <?php 
                            if (!empty($article['tags'])): 
                                foreach (explode(',', $article['tags']) as $tag): 
                                    if (trim($tag)): ?>
                                        <span class="badge bg-light text-dark me-1"><?php echo trim(htmlspecialchars($tag)); ?></span>
                            <?php 
                                    endif;
                                endforeach; 
                            endif; 
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Article Content -->
                    <div class="article-content">
                        <?php echo $article['content']; ?>
                    </div>
                </div>
            </article>
            
            <!-- Related Articles -->
            <?php if (!empty($related_articles)): ?>
                <div class="card shadow-sm mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Related Articles</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($related_articles as $related): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <?php if ($related['featured_image']): ?>
                                            <img src="<?php echo htmlspecialchars($related['featured_image'] ?? ''); ?>" 
                                                 class="card-img-top" alt="<?php echo htmlspecialchars($related['title'] ?? '')?>"
                                                 style="height: 150px; object-fit: cover;">
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <a href="<?php echo SITE_URL; ?>/knowledge/article.php?slug=<?php echo $related['slug']; ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars($related['title']); ?>
                                                </a>
                                            </h6>
                                            <?php if ($related['excerpt']): ?>
                                                <p class="card-text small text-muted">
                                                    <?php echo htmlspecialchars(substr($related['excerpt'] ?? '', 0, 100)) . '...'; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Search Box -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Search Articles</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo SITE_URL; ?>/knowledge/index.php" method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search articles...">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Categories -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Categories</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($categories as $category): ?>
                            <a href="<?php echo SITE_URL; ?>/knowledge/index.php?category=<?php echo urlencode($category['category']); ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($category['category'] ?? ''); ?>
                                <span class="badge bg-primary rounded-pill"><?php echo $category['count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Articles -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">Recent Articles</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_articles as $recent): ?>
                            <a href="<?php echo SITE_URL; ?>/knowledge/article.php?slug=<?php echo $recent['slug']; ?>" 
                               class="list-group-item list-group-item-action">
                                <h6 class="h6 mb-1"><?php echo htmlspecialchars($recent['title'] ?? ''); ?></h6>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($recent['updated_at'])); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.article-content {
    line-height: 1.8;
}

.article-content h1, .article-content h2, .article-content h3,
.article-content h4, .article-content h5, .article-content h6 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-weight: 600;
}

.article-content p {
    margin-bottom: 1rem;
}

.article-content img {
    max-width: 100%;
    height: auto;
    border-radius: 0.375rem;
}

.article-content ul, .article-content ol {
    margin-bottom: 1rem;
    padding-left: 2rem;
}

.article-content blockquote {
    border-left: 4px solid #007bff;
    padding-left: 1rem;
    margin: 1rem 0;
    font-style: italic;
    color: #6c757d;
}

.article-content code {
    background-color: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
}

.article-content pre {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
    overflow-x: auto;
    margin-bottom: 1rem;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
