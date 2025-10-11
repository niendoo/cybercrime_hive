<?php
/**
 * Knowledge Base CMS - WordPress-like Admin Interface
 * Modern CMS with rich text editor, media management, and advanced features
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Security check
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$articles = [];
$categories = [];
$tags = [];

$conn = get_database_connection();

// Handle form submission for add/edit actions only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'delete') {
    // Debug: Log all POST data
    error_log("POST data received: " . print_r($_POST, true));
    
    // Debug: Log form data
    error_log("Knowledge Base Form Submission: " . print_r($_POST, true));
    
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: knowledge_cms.php');
        exit();
    }
    
    // Validate and process add/edit
    $action = $_POST['action'];
    $admin_id = $_SESSION['user_id'];
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $excerpt = trim($_POST['excerpt'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $featured_image = trim($_POST['featured_image'] ?? '');
        
    // Debug: Check data
    error_log("Action: $action, Title: $title, Featured Image: $featured_image, Status: $status, Admin: $admin_id");
    
    // Validate required fields only for add/edit
    if (empty($title) || empty($content)) {
        $missing = [];
        if (empty($title)) $missing[] = 'title';
        if (empty($content)) $missing[] = 'content';
        $_SESSION['error'] = 'Missing required fields: ' . implode(', ', $missing);
        error_log("Validation failed: Missing " . implode(', ', $missing));
        header('Location: knowledge_cms.php');
        exit();
    }
        
        // Generate slug if not provided
    $slug = trim($_POST['slug'] ?? '');
    if (empty($slug)) {
        $slug = generate_slug($title);
    }
    
    // Handle new category
    if ($category === 'new' && !empty($_POST['new_category'])) {
        $category = trim($_POST['new_category']);
    }
    
    try {
        if ($action === 'add') {
            // Insert new article
            $stmt = $conn->prepare("INSERT INTO knowledge_base 
                (title, slug, category, content, excerpt, tags, featured_image, status, author_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            
            if (!$stmt) {
                $_SESSION['error'] = 'Database prepare failed: ' . $conn->error;
                header('Location: knowledge_cms.php');
                exit();
            }
            
            $stmt->bind_param("ssssssssi", $title, $slug, $category, $content, $excerpt, $tags, $featured_image, $status, $admin_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Article added successfully (ID: ' . $stmt->insert_id . ')';
                error_log("Article added: ID=" . $stmt->insert_id . ", Title=$title, Status=$status");
            } else {
                $_SESSION['error'] = 'Database execute failed: ' . $stmt->error;
                error_log("Article add failed: " . $stmt->error);
            }
            $stmt->close();
            
        } elseif ($action === 'edit' && isset($_POST['kb_id'])) {
            // Update existing article
            $kb_id = (int)$_POST['kb_id'];
            
            $stmt = $conn->prepare("UPDATE knowledge_base 
                SET title = ?, slug = ?, category = ?, content = ?, excerpt = ?, tags = ?, 
                    featured_image = ?, status = ?, updated_at = NOW()
                WHERE kb_id = ?");
            $stmt->bind_param("ssssssssi", $title, $slug, $category, $content, $excerpt, $tags, $featured_image, $status, $kb_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Article updated successfully (Rows affected: ' . $stmt->affected_rows . ')';
            } else {
                $_SESSION['error'] = 'Failed to update article: ' . $stmt->error;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
    
    header('Location: knowledge_cms.php');
    exit();
}

// Handle delete action separately
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid CSRF token';
        header('Location: knowledge_cms.php');
        exit();
    }
    
    $kb_id = (int)($_POST['kb_id'] ?? 0);
    
    if ($kb_id > 0) {
        // Get article title for logging
        $stmt = $conn->prepare("SELECT title FROM knowledge_base WHERE kb_id = ?");
        $stmt->bind_param("i", $kb_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $article = $result->fetch_assoc();
        $title = $article ? $article['title'] : "Unknown article";
        $stmt->close();
        
        // Delete the article
        $stmt = $conn->prepare("DELETE FROM knowledge_base WHERE kb_id = ?");
        $stmt->bind_param("i", $kb_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Article '$title' deleted successfully.";
            log_admin_action($admin_id, "Deleted knowledge base article: $title", null);
        } else {
            $_SESSION['error'] = 'Failed to delete article.';
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = 'Invalid article ID.';
    }
    
    header('Location: knowledge_cms.php');
    exit();
}



// Get articles with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// Build query with filters
$where_conditions = [];
$params = [];
$types = '';

if ($search) {
    $where_conditions[] = "(title LIKE ? OR content LIKE ? OR tags LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

if ($category_filter) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM knowledge_base $where_clause";
$stmt = $conn->prepare($count_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result()->fetch_assoc();
$total_articles = $total_result['total'];
$total_pages = ceil($total_articles / $per_page);
$stmt->close();

// Get articles
$query = "SELECT kb.*, u.username as author_name FROM knowledge_base kb 
          LEFT JOIN users u ON kb.author_id = u.user_id 
          $where_clause 
          ORDER BY updated_at DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$final_params = array_merge($params, [$per_page, $offset]);
$final_types = $types . 'ii';

if ($final_params) {
    $stmt->bind_param($final_types, ...$final_params);
}
$stmt->execute();
$result = $stmt->get_result();
$articles = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories
$categories_result = $conn->query("SELECT DISTINCT category FROM knowledge_base ORDER BY category");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Get recent articles
$recent_articles = $conn->query("SELECT kb_id, title, updated_at FROM knowledge_base 
                                WHERE status = 'published' 
                                ORDER BY updated_at DESC LIMIT 5");
$recent = $recent_articles->fetch_all(MYSQLI_ASSOC);

$conn->close();

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Include CKEditor with Image Upload -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Knowledge Base</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="#" class="list-group-item list-group-item-action" onclick="addArticle()">
                            <i class="fas fa-plus me-2"></i>New Article
                        </a>
                        <a href="<?php echo SITE_URL; ?>/knowledge/index.php" target="_blank" class="list-group-item list-group-item-action">
                            <i class="fas fa-external-link-alt me-2"></i>View Public
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Articles -->
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Recent Articles</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <div class="recent-articles">
                            <?php if (count($recent) > 0): ?>
                                <?php foreach ($recent as $article): ?>
                                    <div class="recent-article-item">
                                        <h6 class="mb-1">
                                            <a href="#" onclick="editArticle(<?php echo $article['kb_id']; ?>)" class="text-decoration-none">
                                                <?php echo htmlspecialchars(substr($article['title'], 0, 30)) . '...'; ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($article['updated_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center">No recent articles found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">Knowledge Base CMS</h1>
                    <p class="text-muted mb-0">Manage articles, categories, and content</p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#articleModal">
                        <i class="fas fa-plus me-2"></i>Add New Article
                    </button>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            

            
            <!-- Filters -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search articles..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="published" <?php echo $status_filter == 'published' ? 'selected' : ''; ?>>Published</option>
                                <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="archived" <?php echo $status_filter == 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-primary me-2">
                                <i class="fas fa-search"></i>
                            </button>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Articles Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Articles (<?php echo $total_articles; ?>)</h5>
                    <div class="text-muted">
                        Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (count($articles) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">ID</th>
                                        <th width="30%">Title</th>
                                        <th width="15%">Category</th>
                                        <th width="15%">Author</th>
                                        <th width="10%">Status</th>
                                        <th width="15%">Updated</th>
                                        <th width="10%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($articles as $article): ?>
                                        <tr>
                                            <td><?php echo $article['kb_id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($article['title']); ?></strong>
                                                <?php if ($article['featured_image']): ?>
                                                    <i class="fas fa-image text-muted ms-1" title="Has featured image"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($article['category'] ?? ''); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($article['author_name'] ?? 'Admin'); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $article['status']; ?>">
                                                    <?php echo ucfirst($article['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($article['updated_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-primary btn-action" 
                                                            onclick="editArticle(<?php echo $article['kb_id']; ?>)"
                                                            title="Edit Article">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="<?php echo SITE_URL; ?>/knowledge/article.php?slug=<?php echo $article['slug']; ?>" 
                                                       target="_blank" class="btn btn-info btn-action" title="View Article">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button class="btn btn-danger btn-action" 
                                                            onclick="deleteArticle(<?php echo $article['kb_id']; ?>, '<?php echo addslashes($article['title']); ?>')"
                                                            title="Delete Article">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Article pagination" class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5>No articles found</h5>
                            <p class="text-muted">Try adjusting your search criteria or create a new article.</p>
                            <button class="btn btn-primary" onclick="addArticle()">
                                <i class="fas fa-plus me-2"></i>Create First Article
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Article Modal -->
<div class="modal fade" id="articleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="articleModalLabel">Add New Article</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="articleForm" method="POST" action="knowledge_cms.php">
                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="kb_id" id="form_kb_id" value="">
                <?php echo csrf_token_field(); ?>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" id="form_title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Slug</label>
                                <input type="text" class="form-control" name="slug" id="form_slug" 
                                       placeholder="auto-generated-from-title">
                                <small class="form-text text-muted">URL-friendly version of the title</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Content</label>
                                <textarea class="form-control" name="content" id="form_content" rows="10"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Excerpt</label>
                                <textarea class="form-control" name="excerpt" id="form_excerpt" rows="3" 
                                          placeholder="Brief summary of the article"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="form_status">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" id="form_category">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($cat['category'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control mt-2" id="new_category" 
                                       placeholder="Or create new category" style="display: none;">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Tags</label>
                                <input type="text" class="form-control" name="tags" id="form_tags" 
                                       placeholder="security, prevention, guide">
                                <small class="form-text text-muted">Comma-separated tags</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">SEO Preview</label>
                                <div class="card border">
                                    <div class="card-body p-3">
                                        <div id="seo-preview" class="seo-preview">
                                            <div class="seo-preview-item">
                                                <strong>SEO Title:</strong> <span class="text-muted">Enter a title to see preview</span>
                                            </div>
                                            <div class="seo-preview-item">
                                                <strong>URL:</strong> <span class="text-muted">Enter a slug to see preview</span>
                                            </div>
                                            <div class="seo-preview-item">
                                                <strong>Description:</strong> <span class="text-muted">Enter an excerpt to see preview</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Featured Image</label>
                                
                                <!-- Enhanced Upload Zone -->
                                <div class="border rounded p-4 text-center mb-3" id="uploadZone" style="cursor: pointer; border-style: dashed !important;">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <h6 class="mb-2">Drop image here or click to browse</h6>
                                    <p class="text-muted small mb-0">Supports JPG, PNG, GIF, WebP (Max 5MB)</p>
                                    <input type="file" id="featured_image_file" accept="image/*" style="display: none;">
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="progress mb-3" id="upload_progress" style="display: none; height: 8px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 0%"></div>
                                </div>
                                
                                <!-- Image URL Input -->
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" name="featured_image" id="form_featured_image" 
                                           placeholder="Image URL will appear here...">
                                    <button type="button" class="btn btn-outline-secondary" onclick="clearFeaturedImage()" 
                                            title="Clear image" id="clearImageBtn" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Image Preview -->
                                <div class="border rounded p-3" id="featured_image_container" style="display: none;">
                                    <img id="featured_image_preview" src="" alt="Featured image preview" 
                                         class="img-fluid rounded" style="max-height: 200px;">
                                    <div class="mt-2">
                                        <small class="text-muted" id="image_info"></small>
                                    </div>
                                </div>
                                
                                <!-- Alerts Container -->
                                <div id="image_alerts"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Article</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Article Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Article</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="kb_id" id="delete_kb_id">
                <?php echo csrf_token_field(); ?>
                
                <div class="modal-body">
                    <p>Are you sure you want to delete the article: <strong id="delete_title"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.tox-tinymce {
    border: 1px solid #dee2e6 !important;
    border-radius: 0.375rem !important;
}

.article-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

/* Enhanced Upload Zone Styles */
#uploadZone {
    transition: all 0.3s ease;
    border: 2px dashed #dee2e6 !important;
}

#uploadZone:hover {
    border-color: #0d6efd !important;
    background-color: #f8f9fa;
}

#uploadZone.border-primary {
    border-color: #0d6efd !important;
    background-color: #e7f3ff;
}

#featured_image_preview {
    max-height: 200px;
    border-radius: 0.375rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.progress {
    height: 8px;
    border-radius: 4px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 0.75rem;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-card h3 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.stat-card p {
    margin: 0;
    opacity: 0.9;
}

.category-badge {
    background-color: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-published {
    background-color: #d4edda;
    color: #155724;
}

.status-draft {
    background-color: #fff3cd;
    color: #856404;
}

.status-archived {
    background-color: #f8d7da;
    color: #721c24;
}

.btn-action {
    padding: 0.25rem 0.5rem;
    margin: 0 0.125rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.table-responsive {
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

.sidebar {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.sidebar h5 {
    color: #495057;
    font-weight: 600;
    margin-bottom: 1rem;
}

.recent-articles {
    max-height: 300px;
    overflow-y: auto;
}

.recent-article-item {
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    margin-bottom: 0.5rem;
    background: white;
    transition: background-color 0.2s ease;
}

.recent-article-item:hover {
    background-color: #f8f9fa;
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: none;
}

.modal-header .btn-close {
    filter: invert(1);
}

.form-label {
    font-weight: 500;
    color: #495057;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

#featured_image_preview {
    max-width: 200px;
    max-height: 150px;
    border-radius: 0.375rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* SEO Preview Styles */
.seo-preview {
    font-size: 0.875rem;
}

.seo-preview-item {
    margin-bottom: 0.5rem;
    padding: 0.25rem 0;
}

.seo-preview-item:last-child {
    margin-bottom: 0;
}

.seo-preview-item strong {
    display: block;
    margin-bottom: 0.125rem;
    color: #495057;
}

.seo-preview-item .text-primary {
    color: #0066cc !important;
    word-break: break-all;
}

.seo-preview-item .text-muted {
    color: #6c757d !important;
}
</style>

<script>
// Initialize CKEditor
let editor;
// Custom image upload adapter
class UploadAdapter {
    constructor(loader) {
        this.loader = loader;
    }

    upload() {
        return this.loader.file
            .then(file => new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('image', file);

                fetch('upload_image.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resolve({
                            default: data.url
                        });
                    } else {
                        reject(data.message || 'Upload failed');
                    }
                })
                .catch(error => {
                    reject('Upload failed: ' + error.message);
                });
            }));
    }

    abort() {
        // Handle abort if needed
    }
}

// Function to register the upload adapter
function MyCustomUploadAdapterPlugin(editor) {
    editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
        return new UploadAdapter(loader);
    };
}

// Initialize CKEditor with custom upload adapter
ClassicEditor
    .create(document.querySelector('#form_content'), {
        toolbar: {
            items: [
                'undo', 'redo',
                '|', 'heading',
                '|', 'bold', 'italic',
                '|', 'link', 'blockQuote',
                '|', 'bulletedList', 'numberedList',
                '|', 'insertTable', 'mediaEmbed',
                '|', 'imageUpload' // Enable image upload
            ]
        },
        language: 'en',
        table: {
            contentToolbar: [
                'tableColumn', 'tableRow', 'mergeTableCells'
            ]
        },
        image: {
            toolbar: [
                'imageTextAlternative',
                '|',
                'imageStyle:alignLeft',
                'imageStyle:alignCenter',
                'imageStyle:alignRight'
            ],
            styles: [
                'alignLeft',
                'alignCenter',
                'alignRight'
            ]
        },
        extraPlugins: [MyCustomUploadAdapterPlugin]
    })
    .then(newEditor => {
        editor = newEditor;
        
        // Handle form submission
        document.getElementById('articleForm').addEventListener('submit', function(e) {
            // Update the textarea with CKEditor content
            if (editor) {
                document.getElementById('form_content').value = editor.getData();
            }
        });
    })
    .catch(error => {
        console.error('CKEditor initialization error:', error);
    });

// Auto-slug generation
let slugManuallyEdited = false;

function generateSlugFromTitle(title) {
    // Remove HTML tags and decode entities
    let slug = title.replace(/<[^>]*>/g, '');
    
    // Convert to lowercase and trim
    slug = slug.toLowerCase().trim();
    
    // Replace non-alphanumeric characters with hyphens
    slug = slug.replace(/[^a-z0-9\s\-_]/g, '');
    
    // Replace spaces, underscores, and multiple hyphens with single hyphen
    slug = slug.replace(/[\s\-_]+/g, '-');
    
    // Remove leading/trailing hyphens
    slug = slug.replace(/^-+|-+$/g, '');
    
    // Limit length to 100 characters
    slug = slug.substring(0, 100).replace(/-+$/, '');
    
    // Ensure it's not empty
    if (slug === '') {
        slug = 'article';
    }
    
    return slug;
}

// Auto-generate slug from title
function updateSlug() {
    if (slugManuallyEdited) return;
    
    const title = document.getElementById('form_title').value;
    const slug = generateSlugFromTitle(title);
    document.getElementById('form_slug').value = slug;
    updateSEOPreview();
}

// Update SEO preview
function updateSEOPreview() {
    const title = document.getElementById('form_title').value;
    const slug = document.getElementById('form_slug').value;
    const excerpt = document.getElementById('form_excerpt').value;
    
    const seoTitle = title || 'Untitled Article';
    const seoUrl = '<?php echo SITE_URL; ?>/knowledge/' + encodeURIComponent(slug || 'untitled-article');
    const seoDescription = excerpt || 'No description provided';
    
    // Update SEO preview if it exists
    const seoPreview = document.getElementById('seo-preview');
    if (seoPreview) {
        seoPreview.innerHTML = `
            <div class="seo-preview-item">
                <strong>SEO Title:</strong> ${seoTitle.substring(0, 60)}${seoTitle.length > 60 ? '...' : ''}
            </div>
            <div class="seo-preview-item">
                <strong>URL:</strong> <span class="text-primary">${seoUrl}</span>
            </div>
            <div class="seo-preview-item">
                <strong>Description:</strong> ${seoDescription.substring(0, 160)}${seoDescription.length > 160 ? '...' : ''}
            </div>
        `;
    }
}

// Event listeners for auto-slug generation
// Helper function for alerts
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at top of modal body
    const modalBody = document.querySelector('#articleModal .modal-body');
    if (modalBody) {
        modalBody.insertBefore(alertDiv, modalBody.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

// Enhanced form handling
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('form_title');
    const slugInput = document.getElementById('form_slug');
    const featuredImageField = document.getElementById('form_featured_image');
    
    // Debug form submission
    document.getElementById('articleForm').addEventListener('submit', function(e) {
        console.log('=== FORM SUBMISSION DEBUG ===');
        console.log('Action:', document.getElementById('form_action').value);
        console.log('Title:', document.getElementById('form_title').value);
        console.log('Featured image:', document.getElementById('form_featured_image').value);
        console.log('All form data:', new FormData(this));
    });
    
    // Ensure featured image is included in form data
    if (featuredImageField) {
        featuredImageField.addEventListener('change', function() {
            console.log('Featured image changed to:', this.value);
            updateSEOPreview();
        });
    }
    
    // Enhanced slug generation
    if (titleInput && slugInput) {
        titleInput.addEventListener('input', updateSlug);
        
        slugInput.addEventListener('input', function() {
            slugManuallyEdited = true;
            updateSEOPreview();
        });
        
        const excerptInput = document.getElementById('form_excerpt');
        if (excerptInput) {
            excerptInput.addEventListener('input', updateSEOPreview);
        }
    }
    
    // Ensure CKEditor content is synced before form submission
    const articleForm = document.getElementById('articleForm');
    if (articleForm) {
        articleForm.addEventListener('submit', function(e) {
            if (editor) {
                // Sync CKEditor content to textarea
                document.getElementById('form_content').value = editor.getData();
            }
            
            // Validate required fields
            const title = document.getElementById('form_title').value.trim();
            const content = document.getElementById('form_content').value.trim();
            
            if (!title || !content) {
                e.preventDefault();
                showAlert('Please fill in all required fields (Title and Content)', 'danger');
                return false;
            }
            
            console.log('Form validation passed');
        });
    }
});

// Functions for article management
function addArticle() {
    console.log('Opening add article modal');
    document.getElementById('form_action').value = 'add';
    document.getElementById('form_kb_id').value = '';
    document.getElementById('articleForm').reset();
    document.getElementById('articleModalLabel').textContent = 'Add New Article';
    // Clear CKEditor content
    if (editor) {
        editor.setData('');
    }
    
    // Reset manual edit flag for new articles
    slugManuallyEdited = false;
    
    // Update SEO preview
    updateSEOPreview();
    
    const modal = new bootstrap.Modal(document.getElementById('articleModal'));
    modal.show();
}

function editArticle(kb_id) {
    console.log('Loading article ID:', kb_id);
    // Fetch article data via AJAX
    fetch(`get_article.php?id=${kb_id}`)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Article data received:', data);
            if (data.success) {
                document.getElementById('form_action').value = 'edit';
                document.getElementById('form_kb_id').value = kb_id;
                document.getElementById('articleModalLabel').textContent = 'Edit Article';
                
                document.getElementById('form_title').value = data.article.title;
                document.getElementById('form_slug').value = data.article.slug;
                document.getElementById('form_category').value = data.article.category;
                document.getElementById('form_status').value = data.article.status;
                document.getElementById('form_excerpt').value = data.article.excerpt || '';
                document.getElementById('form_tags').value = data.article.tags || '';
                document.getElementById('form_featured_image').value = data.article.featured_image || '';
                console.log('Featured image loaded:', data.article.featured_image);
                
                if (data.article.featured_image) {
                    setFeaturedImage(data.article.featured_image);
                    console.log('Preview image set to:', data.article.featured_image);
                } else {
                    clearFeaturedImage();
                    console.log('No featured image, hiding preview');
                }
                
                if (editor) {
                    editor.setData(data.article.content || '');
                } else {
                    // Fallback for when CKEditor is not ready
                    setTimeout(() => {
                        if (editor) {
                            editor.setData(data.article.content || '');
                        }
                    }, 100);
                }
                
                // Update SEO preview
                updateSEOPreview();
                
                // Reset manual edit flag when loading existing article
                slugManuallyEdited = true;
                
                const modal = new bootstrap.Modal(document.getElementById('articleModal'));
                modal.show();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load article data: ' + error.message);
        });
}

function deleteArticle(kb_id, title) {
    console.log('Deleting article ID:', kb_id);
    document.getElementById('delete_kb_id').value = kb_id;
    document.getElementById('delete_title').textContent = title;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Enhanced Featured Image Functions
        function uploadFeaturedImage() {
            const fileInput = document.getElementById('featured_image_file');
            const file = fileInput.files[0];
            
            if (!file) {
                showImageAlert('Please select an image file first.', 'warning');
                return;
            }

            // Validate file
            if (!validateImageFile(file)) {
                return;
            }

            uploadImageFile(file);
        }

        function validateImageFile(file) {
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            const maxSize = 5 * 1024 * 1024; // 5MB

            if (!validTypes.includes(file.type)) {
                showImageAlert('Invalid file type. Please select JPG, PNG, GIF, or WebP.', 'danger');
                return false;
            }

            if (file.size > maxSize) {
                showImageAlert('File size must be less than 5MB.', 'danger');
                return false;
            }

            return true;
        }

        function uploadImageFile(file) {
            const formData = new FormData();
            formData.append('image', file);

            const progressBar = document.getElementById('upload_progress');
            const progressInner = progressBar.querySelector('.progress-bar');
            
            progressBar.style.display = 'block';
            progressInner.style.width = '0%';

            fetch('upload_image.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.url) {
                    setFeaturedImage(data.url, file);
                    showImageAlert('Image uploaded successfully!', 'success');
                } else {
                    throw new Error(data.message || 'Upload failed');
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                showImageAlert('Upload failed: ' + error.message, 'danger');
            })
            .finally(() => {
                progressBar.style.display = 'none';
            });
        }

        function setFeaturedImage(url, file = null) {
            const imageField = document.getElementById('form_featured_image');
            const preview = document.getElementById('featured_image_preview');
            const container = document.getElementById('featured_image_container');
            const clearBtn = document.getElementById('clearImageBtn');
            const info = document.getElementById('image_info');

            // Set URL
            imageField.value = url;
            
            // Update preview
            preview.src = url;
            container.style.display = 'block';
            clearBtn.style.display = 'block';
            
            // Update info
            if (file) {
                info.textContent = `${file.name} (${formatFileSize(file.size)})`;
            } else {
                info.textContent = 'External image';
            }
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            imageField.dispatchEvent(event);
            
            console.log('Featured image set to:', url);
        }

        function clearFeaturedImage() {
            const imageField = document.getElementById('form_featured_image');
            const container = document.getElementById('featured_image_container');
            const clearBtn = document.getElementById('clearImageBtn');
            const fileInput = document.getElementById('featured_image_file');

            imageField.value = '';
            container.style.display = 'none';
            clearBtn.style.display = 'none';
            fileInput.value = '';
            
            const event = new Event('change', { bubbles: true });
            imageField.dispatchEvent(event);
            
            console.log('Featured image cleared');
        }

        function showImageAlert(message, type) {
            const container = document.getElementById('image_alerts');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            container.innerHTML = '';
            container.appendChild(alertDiv);
            
            setTimeout(() => alertDiv.remove(), 5000);
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

// Enhanced file handling and drag-and-drop
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('featured_image_file');
            const uploadZone = document.getElementById('uploadZone');
            
            if (uploadZone) {
                // Click to browse
                uploadZone.addEventListener('click', () => fileInput.click());
                
                // Drag and drop events
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    uploadZone.addEventListener(eventName, preventDefaults, false);
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    uploadZone.addEventListener(eventName, highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    uploadZone.addEventListener(eventName, unhighlight, false);
                });

                uploadZone.addEventListener('drop', handleDrop, false);
            }
            
            if (fileInput) {
                fileInput.addEventListener('change', handleFileSelect);
            }
            
            // Handle featured image URL changes
            const imageField = document.getElementById('form_featured_image');
            if (imageField) {
                imageField.addEventListener('input', handleImageUrlChange);
            }
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight() {
            document.getElementById('uploadZone').classList.add('border-primary', 'bg-light');
        }

        function unhighlight() {
            document.getElementById('uploadZone').classList.remove('border-primary', 'bg-light');
        }

        function handleDrop(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        }

        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                handleFile(file);
            }
        }

        function handleFile(file) {
            if (!validateImageFile(file)) {
                return;
            }

            // Show preview immediately
            const reader = new FileReader();
            reader.onload = function(e) {
                showFilePreview(e.target.result, file);
            };
            reader.readAsDataURL(file);

            // Upload the file
            uploadImageFile(file);
        }

        function showFilePreview(src, file) {
            const preview = document.getElementById('featured_image_preview');
            const container = document.getElementById('featured_image_container');
            const info = document.getElementById('image_info');
            
            preview.src = src;
            container.style.display = 'block';
            document.getElementById('clearImageBtn').style.display = 'block';
            info.textContent = `${file.name} (${formatFileSize(file.size)})`;
        }

        function handleImageUrlChange() {
            const url = this.value;
            const preview = document.getElementById('featured_image_preview');
            const container = document.getElementById('featured_image_container');
            const clearBtn = document.getElementById('clearImageBtn');
            const info = document.getElementById('image_info');
            
            if (url) {
                preview.src = url;
                container.style.display = 'block';
                clearBtn.style.display = 'block';
                info.textContent = 'External image';
            } else {
                container.style.display = 'none';
                clearBtn.style.display = 'none';
            }
            
            console.log('Featured image URL changed:', url);
        }

// Handle featured image preview
document.getElementById('form_featured_image').addEventListener('input', function() {
    console.log('Featured image input changed:', this.value);
    const url = this.value;
    const preview = document.getElementById('featured_image_preview');
    if (url) {
        preview.src = url;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

// Auto-generate slug
document.getElementById('form_title').addEventListener('input', function() {
    const title = this.value;
    const slug = title.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim('-');
    
    if (!document.getElementById('form_slug').value || 
        document.getElementById('form_slug').value === document.getElementById('form_slug').getAttribute('placeholder')) {
        document.getElementById('form_slug').value = slug;
    }
});

// Debug form submission
document.getElementById('articleForm').addEventListener('submit', function(e) {
    console.log('Form submission triggered');
    
    // Ensure CKEditor content is synced
    if (typeof editor !== 'undefined' && editor) {
        document.getElementById('form_content').value = editor.getData();
    }
    
    console.log('Form data:', {
        title: document.getElementById('form_title').value,
        content: document.getElementById('form_content').value,
        status: document.getElementById('form_status').value,
        action: document.getElementById('form_action').value
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
