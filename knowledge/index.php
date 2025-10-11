<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Get knowledge base articles
$conn = get_database_connection();
$articles = [];
$categories = [];
$category_filter = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Get all categories
$result = $conn->query("SELECT DISTINCT category FROM knowledge_base WHERE status = 'published' ORDER BY category");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Build query with prepared statements
$query = "SELECT kb.*, u.username as author_name FROM knowledge_base kb 
          LEFT JOIN users u ON kb.author_id = u.user_id 
          WHERE kb.status = 'published'";
$params = [];
$types = '';

if (!empty($category_filter)) {
    $query .= " AND kb.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if (!empty($search_term)) {
    $query .= " AND (kb.title LIKE ? OR kb.excerpt LIKE ? OR kb.tags LIKE ?)";
    $search_param = '%' . $search_term . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$query .= " ORDER BY kb.updated_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($article = $result->fetch_assoc()) {
    $articles[] = $article;
}

$conn->close();

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row my-4 px-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active">Knowledge Base</li>
            </ol>
        </nav>
        
        <div class="jumbotron bg-light p-4 rounded-3 mb-4">
            <h1 class="display-5"><i class="fas fa-book text-primary me-2"></i>Knowledge Base</h1>
            <p class="lead">Resources to help you understand cybercrime, how to prevent it, and how to use our system.</p>
        </div>
    </div>
</div>

<div class="row px-4">
    <div class="col-md-3 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Search & Filter</h5>
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <div class="mb-3">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search terms..." value="<?php echo htmlspecialchars($search_term ?? ''); ?>">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Filter by Category</label>
                        <select class="form-select" id="category" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category ?? ''); ?>" <?php if ($category_filter == $category) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($category ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($category_filter) || !empty($search_term)): ?>
                        <div class="d-grid">
                            <a href="<?php echo SITE_URL; ?>/knowledge/index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Quick Links</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?php echo SITE_URL; ?>/knowledge/index.php?category=<?php echo urlencode($cat); ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-folder text-primary me-3"></i> <?php echo htmlspecialchars($cat); ?>
                        </a>
                    <?php endforeach; ?>
                    <div class="border-top my-2"></div>
                    <a href="<?php echo SITE_URL; ?>/reports/submit.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="fas fa-paper-plane text-primary me-3"></i> Submit a Report
                    </a>
                    <a href="<?php echo SITE_URL; ?>/reports/track.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="fas fa-search text-primary me-3"></i> Track Your Report
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <?php if (count($articles) > 0): ?>
            <?php 
            // Include image generator
            require_once dirname(__DIR__) . '/includes/image_generator.php';
            
            // Display all articles in unified card layout
            ?>
            <div class="row g-4">
                <?php foreach ($articles as $article): 
                    $featuredImage = getFeaturedImageUrl($article);
                ?>
                            <div class="col-md-6 col-lg-4">
                                <article class="card h-100 shadow-sm article-card">
                                    <div class="card-img-top-wrapper">
                                        <img src="<?php echo htmlspecialchars($featuredImage); ?>" 
                                             class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($article['title']); ?>"
                                             style="height: 200px; object-fit: cover;">
                                        <div class="card-img-overlay-badge">
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($article['category']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title">
                                            <a href="<?php echo SITE_URL; ?>/knowledge/<?php echo urlencode($article['slug']); ?>" 
                                               class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($article['title']); ?>
                                            </a>
                                        </h5>
                                        
                                        <p class="card-text text-muted flex-grow-1">
                                            <?php 
                                            $excerpt = $article['excerpt'] ?? strip_tags($article['content']);
                                            echo htmlspecialchars(substr($excerpt, 0, 120) . (strlen($excerpt) > 120 ? '...' : ''));
                                            ?>
                                        </p>
                                        
                                        <div class="card-footer-info mt-auto">
                                            <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                                                <span>
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($article['author_name'] ?? 'Admin'); ?>
                                                </span>
                                                <span>
                                                    <i class="fas fa-eye me-1"></i>
                                                    <?php echo number_format($article['view_count'] ?? 0); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($article['updated_at'])); ?>
                                                </small>
                                                
                                                <a href="<?php echo SITE_URL; ?>/knowledge/<?php echo urlencode($article['slug']); ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    Read More <i class="fas fa-arrow-right ms-1"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-book-open fa-4x text-gray-300 mb-3"></i>
                    <?php if (!empty($category_filter) || !empty($search_term)): ?>
                        <h5>No articles found matching your criteria</h5>
                        <p>Try adjusting your search or filter settings.</p>
                        <a href="<?php echo SITE_URL; ?>/knowledge/index.php" class="btn btn-primary mt-2">
                            <i class="fas fa-times me-2"></i>Clear Filters
                        </a>
                    <?php else: ?>
                        <h5>No knowledge base articles available yet</h5>
                        <p>Please check back later as we continue to add helpful resources.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
