<?php
require_once 'config/config.php';
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); // Hide notices and warnings

$pdo = db_connect();

// Get user data for navbar profile image
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT username, profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// Get categories
$stmt = $pdo->query("SELECT id, name, slug, description FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Fetch recent articles (add author profile image)
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.title,
        a.abstract,
        a.thumbnail_path,
        a.created_at,
        u.username,
        u.profile_image as author_profile_image
    FROM articles a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.status = 'published'
    GROUP BY a.id, u.username, u.profile_image
    ORDER BY a.created_at DESC
    LIMIT 6
");
$stmt->execute();
$articles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Publish Club - Share Knowledge, Learn Together</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/index.css" rel="stylesheet">
    <link href="assets/css/custom-pro.css" rel="stylesheet">
    <style>
        .hero-banner-img-wrap {
            cursor: pointer;
        }
        .hero-banner-img-wrap:hover .hero-banner-img {
            transform: scale(1.05);
            box-shadow: 0 8px 32px 0 rgba(33,150,243,0.25);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <section class="hero-section text-center position-relative">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-7 text-start text-md-start">
                    <h1 class="mb-3">Welcome to <span style="color:#fff; background:#1976d2; border-radius:8px; padding:0 10px;">The Publish Club</span></h1>
                    <p class="mb-4">Share your knowledge, publish your research, and join a vibrant community of writers, reviewers, and readers.<br>Discover articles, join discussions, and grow together!</p>
                    <a href="articles.php" class="btn btn-warning me-2">Explore Articles</a>
                    <a href="articles.php?action=publish" class="btn btn-light fw-bold">Publish Article</a>
                </div>
                <div class="col-md-5 d-none d-md-block">
                    <div class="hero-banner-img-wrap position-relative">
                        <img src="assets/img/baner (2).png" class="hero-banner-img" alt="Banner" style="width:100%;max-width:350px;box-shadow:0 8px 32px 0 rgba(33,150,243,0.15);border-radius:18px;transition:transform 0.3s;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section bg-light">
        <div class="container explore-categories-shade">
            <div class="row justify-content-center mb-5">
                <div class="col-md-8 text-center">
                    <h2 class="section-title">Explore Categories</h2>
                    <p class="section-subtitle">Discover articles in your favorite topics</p>
                </div>
            </div>
            <div id="categories-pagination" class="row g-4 justify-content-center"></div>
            <nav aria-label="Category pagination" class="mt-4">
                <ul class="pagination justify-content-center" id="categories-pagination-nav"></ul>
            </nav>
        </div>
    </section>

    <section class="section">
        <div class="container recent-articles-shade">
            <div class="row justify-content-center mb-5">
                <div class="col-md-8 text-center">
                    <h2 class="section-title">Recent Articles</h2>
                    <hr class="section-divider" />
                </div>
            </div>
            <div class="row g-4">
                <?php foreach ($articles as $article): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <?php 
                            $thumb = (!empty($article['thumbnail_path']) && file_exists(__DIR__ . '/' . $article['thumbnail_path']))
                                ? $article['thumbnail_path']
                                : 'assets/img/article-default.png';
                        ?>
                        <img src="<?php echo htmlspecialchars($thumb); ?>" class="card-img-top" alt="Article Thumbnail" style="width:100%;height:220px;object-fit:cover;">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($article['title']); ?></h5>
                            <div class="d-flex align-items-center mb-2">
                                <img src="<?php echo htmlspecialchars($article['author_profile_image'] ?? 'assets/img/avatar-default.png'); ?>" class="rounded-circle me-2" style="width:28px;height:28px;object-fit:cover;" alt="Profile Image">
                                <span class="small text-muted"><?php echo htmlspecialchars($article['username']); ?></span>
                            </div>
                            <p class="card-text flex-grow-1">
                                <?php if (!empty($article['abstract'])) {
                                    echo htmlspecialchars($article['abstract']);
                                } ?>
                            </p>
                            <a href="view_article.php?id=<?php echo $article['id']; ?>" class="btn btn-dark btn-sm mt-3 btn-icon w-100"><i class="bi bi-book"></i> Read More</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="articles.php" class="btn btn-outline-primary">View All Articles</a>
            </div>
        </div>
    </section>

    <footer class="footer mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h3 class="footer-title">The Publish Club</h3>
                    <p class="small">Share Knowledge, Learn Together.<br> &copy; <?php echo date('Y'); ?> All rights reserved.</p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h3 class="footer-title">Quick Links</h3>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="articles.php" class="text-white">Articles</a></li>
                        <li><a href="reviews.php" class="text-white">Reviews</a></li>
                        <li><a href="interaction.php" class="text-white">Discussion</a></li>
                        <li><a href="profile.php" class="text-white">Profile</a></li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li><a href="admin.php" class="text-white">Admin Panel</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h3 class="footer-title">Contact</h3>
                    <p class="small mb-2"><i class="bi bi-envelope"></i> support@publishclub.com</p>
                    <p class="small"><i class="bi bi-geo-alt"></i> Online Worldwide</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Paginate categories (4 per row)
    const categories = <?php echo json_encode($categories); ?>;
    const perPage = 4;
    let currentPage = 1;
    function renderCategoriesPage(page) {
        currentPage = page;
        const start = (page-1)*perPage;
        const end = start+perPage;
        const cats = categories.slice(start, end);
        let html = '';
        cats.forEach(cat => {
            html += `<div class="col-md-3">
                <a href="published.php?category=${encodeURIComponent(cat.slug)}" class="category-card text-decoration-none">
                    <div class="card h-100 border-0 shadow-hover">
                        <div class="card-body p-4 text-center">
                            <div class="category-icon mb-3">
                                <i class="bi bi-folder2-open display-4"></i>
                            </div>
                            <h3 class="category-title h4 mb-2">${cat.name}</h3>
                            ${cat.description ? `<p class="category-description mb-0 text-muted">${cat.description}</p>` : ''}
                        </div>
                    </div>
                </a>
            </div>`;
        });
        document.getElementById('categories-pagination').innerHTML = html;
        renderCategoriesPagination();
    }
    function renderCategoriesPagination() {
        const totalPages = Math.ceil(categories.length/perPage);
        let nav = '';
        nav += `<li class="page-item${currentPage===1?' disabled':''}"><a class="page-link" href="#" onclick="if(currentPage>1){renderCategoriesPage(currentPage-1)}return false;">&lsaquo;</a></li>`;
        for(let i=1;i<=totalPages;i++) {
            if(i===1 || i===totalPages || (i>=currentPage-1 && i<=currentPage+1)) {
                nav += `<li class="page-item${i===currentPage?' active':''}"><a class="page-link" href="#" onclick="renderCategoriesPage(${i});return false;">${i}</a></li>`;
            } else if (i===currentPage-2 || i===currentPage+2) {
                nav += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        nav += `<li class="page-item${currentPage===totalPages?' disabled':''}"><a class="page-link" href="#" onclick="if(currentPage<totalPages){renderCategoriesPage(currentPage+1)}return false;">&rsaquo;</a></li>`;
        document.getElementById('categories-pagination-nav').innerHTML = nav;
    }
    renderCategoriesPage(1);
    </script>
</body>
</html>
