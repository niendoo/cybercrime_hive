<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/includes/functions.php';

// Check if user is logged in and is admin
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Redirect to login page
    header("Location: " . SITE_URL . "/auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$articles = [];

// Get knowledge base articles
$conn = get_database_connection();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Add new article
        if ($_POST['action'] == 'add') {
            $title = sanitize_input($_POST['title']);
            $category = sanitize_input($_POST['category']);
            $content = sanitize_input($_POST['content']);
            $timestamp = date('Y-m-d H:i:s');
            
            if (empty($title) || empty($category) || empty($content)) {
                $error_message = "All fields are required.";
            } else {
                $stmt = $conn->prepare("INSERT INTO knowledge_base (title, category, content, created_at, updated_at) 
                                         VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $title, $category, $content, $timestamp, $timestamp);
                
                if ($stmt->execute()) {
                    $success_message = "Article '$title' added successfully.";
                    log_admin_action($admin_id, "Added knowledge base article: $title", null);
                } else {
                    $error_message = "Failed to add article. Please try again.";
                }
                
                $stmt->close();
            }
        }
        // Update existing article
        else if ($_POST['action'] == 'edit' && isset($_POST['kb_id'])) {
            $kb_id = intval($_POST['kb_id']);
            $title = sanitize_input($_POST['title']);
            $category = sanitize_input($_POST['category']);
            $content = sanitize_input($_POST['content']);
            $timestamp = date('Y-m-d H:i:s');
            
            if (empty($title) || empty($category) || empty($content)) {
                $error_message = "All fields are required.";
            } else {
                $stmt = $conn->prepare("UPDATE knowledge_base SET title = ?, category = ?, content = ?, updated_at = ? 
                                         WHERE kb_id = ?");
                $stmt->bind_param("ssssi", $title, $category, $content, $timestamp, $kb_id);
                
                if ($stmt->execute()) {
                    $success_message = "Article '$title' updated successfully.";
                    log_admin_action($admin_id, "Updated knowledge base article: $title", null);
                } else {
                    $error_message = "Failed to update article. Please try again.";
                }
                
                $stmt->close();
            }
        }
        // Delete article
        else if ($_POST['action'] == 'delete' && isset($_POST['kb_id'])) {
            $kb_id = intval($_POST['kb_id']);
            
            // Get the title before deleting for the log
            $stmt = $conn->prepare("SELECT title FROM knowledge_base WHERE kb_id = ?");
            $stmt->bind_param("i", $kb_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $article = $result->fetch_assoc();
            $article_title = $article ? $article['title'] : "Unknown article";
            $stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM knowledge_base WHERE kb_id = ?");
            $stmt->bind_param("i", $kb_id);
            
            if ($stmt->execute()) {
                $success_message = "Article deleted successfully.";
                log_admin_action($admin_id, "Deleted knowledge base article: $article_title", null);
            } else {
                $error_message = "Failed to delete article. Please try again.";
            }
            
            $stmt->close();
        }
    }
}

// Get all categories for the dropdown
$categories = [];
$result = $conn->query("SELECT DISTINCT category FROM knowledge_base ORDER BY category");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Get all articles
$result = $conn->query("SELECT * FROM knowledge_base ORDER BY category, title");
while ($article = $result->fetch_assoc()) {
    $articles[] = $article;
}

$conn->close();

// Include header
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row mb-4 px-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active">Knowledge Base Management</li>
            </ol>
        </nav>
        
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Knowledge Base Management</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addArticleModal">
                <i class="fas fa-plus me-2"></i>Add New Article
            </button>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Knowledge Base Articles</h6>
        <a href="<?php echo SITE_URL; ?>/knowledge/index.php" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-external-link-alt me-1"></i>View Public Knowledge Base
        </a>
    </div>
    <div class="card-body">
        <?php if (count($articles) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $article): ?>
                            <tr>
                                <td><?php echo $article['kb_id']; ?></td>
                                <td><?php echo htmlspecialchars($article['title']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($article['category']); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($article['created_at'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($article['updated_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info edit-article" 
                                            data-id="<?php echo $article['kb_id']; ?>" 
                                            data-title="<?php echo htmlspecialchars($article['title']); ?>" 
                                            data-category="<?php echo htmlspecialchars($article['category']); ?>" 
                                            data-content="<?php echo htmlspecialchars($article['content']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-article" 
                                            data-id="<?php echo $article['kb_id']; ?>" 
                                            data-title="<?php echo htmlspecialchars($article['title']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-book fa-4x text-gray-300 mb-3"></i>
                <p>No knowledge base articles found.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addArticleModal">
                    <i class="fas fa-plus me-2"></i>Add Your First Article
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Article Modal -->
<div class="modal fade" id="addArticleModal" tabindex="-1" aria-labelledby="addArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addArticleModalLabel">Add New Knowledge Base Article</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="category" name="category" list="categories" required>
                            <datalist id="categories">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php endforeach; ?>
                                <option value="Prevention">
                                <option value="Reporting Guide">
                                <option value="System Usage">
                                <option value="FAQ">
                            </datalist>
                        </div>
                        <div class="form-text">You can select an existing category or create a new one.</div>
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">Content</label>
                        <textarea class="form-control" id="content" name="content" rows="10" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Article</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Article Modal -->
<div class="modal fade" id="editArticleModal" tabindex="-1" aria-labelledby="editArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editArticleModalLabel">Edit Knowledge Base Article</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="kb_id" id="edit_kb_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category" class="form-label">Category</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="edit_category" name="category" list="edit_categories" required>
                            <datalist id="edit_categories">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php endforeach; ?>
                                <option value="Prevention">
                                <option value="Reporting Guide">
                                <option value="System Usage">
                                <option value="FAQ">
                            </datalist>
                        </div>
                        <div class="form-text">You can select an existing category or create a new one.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_content" class="form-label">Content</label>
                        <textarea class="form-control" id="edit_content" name="content" rows="10" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Article</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Article Modal -->
<div class="modal fade" id="deleteArticleModal" tabindex="-1" aria-labelledby="deleteArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteArticleModalLabel">Delete Knowledge Base Article</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="kb_id" id="delete_kb_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the article: <strong id="delete_article_title"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Article</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle edit article buttons
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-article');
    const deleteButtons = document.querySelectorAll('.delete-article');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const category = this.getAttribute('data-category');
            const content = this.getAttribute('data-content');
            
            document.getElementById('edit_kb_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_content').value = content;
            
            const editModal = new bootstrap.Modal(document.getElementById('editArticleModal'));
            editModal.show();
        });
    });
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            
            document.getElementById('delete_kb_id').value = id;
            document.getElementById('delete_article_title').textContent = title;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteArticleModal'));
            deleteModal.show();
        });
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
