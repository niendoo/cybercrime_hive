<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Handle feedback routing (Alternative solution for production)
if (isset($_GET['feedback']) || isset($_GET['token'])) {
    // Include the feedback system
    include __DIR__ . '/feedback/index.php';
    exit;
}

// Debug CSS loading (temporary)
if (isset($_GET['debug_css'])) {
    echo "<h1>CSS Debug Information</h1>";
    echo "<p><strong>Environment:</strong> " . (EnvironmentManager::isProduction() ? 'Production' : 'Local') . "</p>";
    echo "<p><strong>SITE_URL:</strong> " . SITE_URL . "</p>";
    echo "<p><strong>CSS URL:</strong> " . SITE_URL . "/assets/css/main.css</p>";
    echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
    echo "<p><strong>HTTP Host:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
    
    $cssPath = __DIR__ . '/assets/css/main.css';
    echo "<p><strong>CSS File Path:</strong> " . $cssPath . "</p>";
    echo "<p><strong>CSS File Exists:</strong> " . (file_exists($cssPath) ? 'YES' : 'NO') . "</p>";
    
    if (file_exists($cssPath)) {
        echo "<p><strong>CSS File Size:</strong> " . filesize($cssPath) . " bytes</p>";
    }
    
    echo "<p>Access this page without ?debug_css to see the normal site.</p>";
    exit;
}

// Include header
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section with Animated Background -->
<header class="hero-section position-relative overflow-hidden mb-5">
    <div class="hero-bg"></div>
    <div class="position-relative py-5 w-100">
        <div class="container">
            <div class="row align-items-center min-vh-75">
                <article class="col-lg-6 py-5 animate-fade-in">
                    <h1 class="display-3 fw-bold text-white mb-3">
                        <span class="d-block text-gradient">Cyber</span>
                        <span class="d-block">Crime Hive</span>
                    </h1>
                    <p class="lead text-white mb-4 fs-4 fw-light">A secure platform for reporting and tracking cybercrime incidents in real-time</p>
                    <p class="text-white-75 mb-5">Whether you've been a victim of phishing, hacking, fraud, or any other cybercrime, we're here to help you take back control. Submit your report and track its progress as our team works on your case.</p>
                    <nav class="d-flex gap-3 mt-4">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn btn-lg btn-gradient-primary">
                                <i class="fas fa-user-plus me-2"></i>Register Now
                            </a>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-lg btn-outline-light">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/reports/submit.php" class="btn btn-lg btn-gradient-primary">
                                <i class="fas fa-file-alt me-2"></i>Submit Report
                            </a>
                            <a href="<?php echo SITE_URL; ?>/reports/track.php" class="btn btn-lg btn-outline-light">
                                <i class="fas fa-search me-2"></i>Track Report
                            </a>
                        <?php endif; ?>
                    </nav>
                </article>
                <figure class="col-lg-6 d-none d-lg-block">
                    <div class="position-relative" style="z-index: 1;">
                        <div class="hero-graphic" role="img" aria-label="Cybersecurity illustration"></div>
                    </div>
                </figure>
            </div>
        </div>
    </div>
    <div class="hero-wave"></div>
</header>

<!-- Features Section - Full Width -->
<section class="features-section py-5 mb-5 w-100" aria-labelledby="services-heading">
    <div class="container text-center mb-5">
        <h2 id="services-heading" class="display-5 fw-bold">Our <span class="text-gradient">Services</span></h2>
        <p class="lead">Comprehensive cybercrime reporting and tracking solutions</p>
    </div>
    
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4 mb-4">
                <article class="feature-card">
                    <figure class="icon-wrapper primary" aria-hidden="true">
                        <i class="fas fa-file-alt"></i>
                    </figure>
                    <h3>Report Cybercrime</h3>
                    <p>Submit detailed reports about any cybercrime incidents. Attach evidence files and get a unique tracking code for your case.</p>
                    <a href="<?php echo SITE_URL; ?>/reports/submit.php" class="btn btn-gradient-primary mt-auto" role="button">
                        <span>Submit Report</span>
                        <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                    </a>
                </article>
            </div>
            
            <div class="col-md-4 mb-4">
                <article class="feature-card">
                    <figure class="icon-wrapper secondary" aria-hidden="true">
                        <i class="fas fa-search"></i>
                    </figure>
                    <h3>Track Status</h3>
                    <p>Monitor the progress of your report in real-time. Receive automatic notifications via email as your case advances.</p>
                    <a href="<?php echo SITE_URL; ?>/reports/track.php" class="btn btn-gradient-secondary mt-auto" role="button">
                        <span>Track Report</span>
                        <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                    </a>
                </article>
            </div>
            
            <div class="col-md-4 mb-4">
                <article class="feature-card">
                    <figure class="icon-wrapper tertiary" aria-hidden="true">
                        <i class="fas fa-book"></i>
                    </figure>
                    <h3>Knowledge Base</h3>
                    <p>Access valuable resources about various cybercrime types, prevention strategies, and how to protect yourself online.</p>
                    <a href="<?php echo SITE_URL; ?>/knowledge/index.php" class="btn btn-gradient-tertiary mt-auto" role="button">
                        <span>Learn More</span>
                        <i class="fas fa-arrow-right ms-2" aria-hidden="true"></i>
                    </a>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section with Counter Animation - Full Width -->
<section class="statistics-section py-5 mb-0 w-100" aria-labelledby="statistics-heading">
    <div class="text-center mb-5">
        <h2 id="statistics-heading" class="display-5 fw-bold text-white">Cybercrime <span class="text-gradient-light">Statistics</span></h2>
        <p class="lead text-white-75">Based on reports submitted to CyberCrime Hive in the last 12 months</p>
    </div>
    
    <div class="container">
        <div class="row">
            <div class="col-md-3 col-6 mb-4">
                <figure class="stat-item text-center" role="figure" aria-labelledby="phishing-stat">
                    <div class="stat-number" data-target="35" aria-live="polite">0</div>
                    <div class="stat-percent">%</div>
                    <figcaption id="phishing-stat" class="stat-label">Phishing Attacks</figcaption>
                </figure>
            </div>
            <div class="col-md-3 col-6 mb-4">
                <figure class="stat-item text-center" role="figure" aria-labelledby="fraud-stat">
                    <div class="stat-number" data-target="28" aria-live="polite">0</div>
                    <div class="stat-percent">%</div>
                    <figcaption id="fraud-stat" class="stat-label">Online Fraud</figcaption>
                </figure>
            </div>
            <div class="col-md-3 col-6 mb-4">
                <figure class="stat-item text-center" role="figure" aria-labelledby="hacking-stat">
                    <div class="stat-number" data-target="22" aria-live="polite">0</div>
                    <div class="stat-percent">%</div>
                    <figcaption id="hacking-stat" class="stat-label">Hacking Incidents</figcaption>
                </figure>
            </div>
            <div class="col-md-3 col-6 mb-4">
                <figure class="stat-item text-center" role="figure" aria-labelledby="other-stat">
                    <div class="stat-number" data-target="15" aria-live="polite">0</div>
                    <div class="stat-percent">%</div>
                    <figcaption id="other-stat" class="stat-label">Other Cybercrimes</figcaption>
                </figure>
            </div>
        </div>
    </div>
</section>

<!-- Content Section -->
<main class="content-section py-5">
    <div class="container"> <!-- Content container -->
        <!-- First row: Recent Threats -->
        <div class="row mb-4">
            <section class="col-12 col-md-6">
                <article class="card shadow-sm">
                    <header class="card-header bg-primary text-white">
                        <h2 class="h4 mb-0"><i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>Recent Threats</h2>
                    </header>
                    <div class="card-body">
                        <ul class="list-group list-group-flush" aria-labelledby="recent-threats-heading">
                            <li class="list-group-item">
                                <h3 class="h5"><i class="fas fa-virus text-danger me-2" aria-hidden="true"></i>New Ransomware Strain Targeting Healthcare</h3>
                                <p>A new ransomware variant is specifically targeting healthcare organizations. Exercise caution with email attachments and ensure your systems are updated.</p>
                            </li>
                            <li class="list-group-item">
                                <h3 class="h5"><i class="fas fa-envelope text-warning me-2" aria-hidden="true"></i>Sophisticated Phishing Campaign</h3>
                                <p>Users are reporting a highly convincing phishing campaign impersonating major banks. Always verify the sender and never click suspicious links.</p>
                            </li>
                            <li class="list-group-item">
                                <h3 class="h5"><i class="fas fa-mobile-alt text-info me-2" aria-hidden="true"></i>SMS Scams on the Rise</h3>
                                <p>There's been an increase in SMS-based scams claiming to be from delivery services. Never provide personal information via text messages.</p>
                            </li>
                        </ul>
                    </div>
                </article>
            </section>
        </div>
        
        <!-- Second row: Latest News & Articles (3-card grid) -->
        <div class="row mb-4">
            <section class="col-12">
                <div class="text-center mb-4">
                    <h2 class="display-6 fw-bold"><i class="fas fa-newspaper text-primary me-2" aria-hidden="true"></i>Latest News &amp; Articles</h2>
                    <p class="lead text-muted">Stay informed with our latest cybersecurity insights and updates</p>
                </div>
                
                <?php
                // Get latest articles from knowledge base
                require_once $_SERVER['DOCUMENT_ROOT'] . '/cybercrime_hive/includes/image_generator.php';
                $conn = get_database_connection();
                $latest_articles = $conn->query("SELECT kb.*, u.username as author_name FROM knowledge_base kb 
                                                LEFT JOIN users u ON kb.author_id = u.user_id 
                                                WHERE kb.status = 'published' 
                                                ORDER BY kb.updated_at DESC LIMIT 3");
                $articles = $latest_articles->fetch_all(MYSQLI_ASSOC);
                $conn->close();
                
                if (count($articles) > 0):
                ?>
                    <div class="row g-4 mb-4">
                        <?php foreach ($articles as $article):
                            $featuredImage = getFeaturedImageUrl($article);
                        ?>
                            <div class="col-md-4">
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
                                            <a href="<?php echo SITE_URL; ?>/knowledge/article.php?slug=<?php echo urlencode($article['slug']); ?>" 
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
                                                
                                                <a href="<?php echo SITE_URL; ?>/knowledge/article.php?slug=<?php echo urlencode($article['slug']); ?>" 
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
                    
                    <div class="text-center">
                        <a href="<?php echo SITE_URL; ?>/knowledge/index.php" class="btn btn-lg btn-gradient-primary">
                            <i class="fas fa-book me-2"></i>Read All Articles
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-newspaper fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No articles available yet</h5>
                        <p class="text-muted">Check back later for the latest cybersecurity insights.</p>
                        <a href="<?php echo SITE_URL; ?>/knowledge/index.php" class="btn btn-primary">
                            <i class="fas fa-book me-2"></i>Visit Knowledge Base
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<!-- End Content Section -->

<?php include __DIR__ . '/includes/footer.php'; ?>
