<?php
require_once 'config/config.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = db_connect();

// Get selected category from URL
$selected_category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);

// Fetch all categories
$stmt = $pdo->prepare("SELECT id, name, slug FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Fetch published articles with category filter
$sql = "
    SELECT 
        a.*,
        c.name as category_name,
        c.slug as category_slug,
        u.username as author,
        COUNT(DISTINCT r.id) as review_count,
        AVG(r.rating) as avg_rating
    FROM articles a
    JOIN categories c ON a.category_id = c.id
    JOIN users u ON a.user_id = u.id
    LEFT JOIN reviews r ON a.id = r.article_id
    WHERE a.status = 'published'
";

if ($selected_category) {
    $sql .= " AND c.slug = ?";
}

$sql .= "
    GROUP BY a.id, c.name, c.slug, u.username
    ORDER BY a.published_at DESC
";

$stmt = $pdo->prepare($sql);
if ($selected_category) {
    $stmt->execute([$selected_category]);
} else {
    $stmt->execute();
}
$articles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Published Articles - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/published.css" rel="stylesheet">
    <style>
        body { background: #f7f7f9 !important; font-family: 'Inter', Arial, sans-serif; }
        .articles-gradient-banner {
            background: linear-gradient(90deg, #6a82fb 0%, #a18cd1 100%);
            border-radius: 22px;
            box-shadow: 0 4px 32px 0 rgba(106,130,251,0.13);
            padding: 2.5rem 2rem 2rem 2rem;
            margin-bottom: 2.5rem;
            text-align: center;
            color: #fff;
        }
        .articles-gradient-banner h1 {
            font-size: 2.6rem;
            font-weight: 700;
            margin-bottom: 0.6rem;
        }
        .articles-gradient-banner p {
            font-size: 1.2rem;
            color: #f8fafc;
            margin-bottom: 0;
        }
        .article-card {
            background: linear-gradient(90deg, #f8fafc 0%, #eae6f7 100%);
            border-radius: 18px;
            box-shadow: 0 2px 16px 0 rgba(106,130,251,0.10);
            border: none;
            transition: transform 0.14s, box-shadow 0.14s;
        }
        .article-card:hover {
            transform: translateY(-6px) scale(1.025);
            box-shadow: 0 6px 32px 0 rgba(106,130,251,0.18);
        }
        .article-card .card-title {
            font-size: 1.22rem;
            font-weight: 600;
            color: #23272f;
        }
        .article-card .card-text {
            font-size: 1.04rem;
            color: #333;
        }
        .article-card .btn-outline-primary {
            border-radius: 8px;
            font-weight: 500;
            border: 2px solid #6a82fb;
            color: #6a82fb;
            transition: background 0.13s, color 0.13s;
        }
        .article-card .btn-outline-primary:hover {
            background: #6a82fb;
            color: #fff;
            border-color: #6a82fb;
        }
        .article-card .card-footer {
            border-top: none;
            background: transparent;
        }
        .article-card .text-warning i {
            font-size: 1.2rem;
        }
        .category-badge {
            background: #fff;
            color: #6a82fb;
            border-radius: 16px;
            font-size: 1.02rem;
            padding: 0.35em 1.1em;
            font-weight: 500;
            margin-bottom: 0.5em;
            margin-right: 0.6em;
            border: 1.5px solid #e0e7ff;
            transition: background 0.13s, color 0.13s;
        }
        .category-badge.active, .category-badge:hover {
            background: #6a82fb;
            color: #fff;
            border-color: #6a82fb;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="assets/img/publishclub-logo.png" alt="The Publish Club Logo" style="height:72px;width:72px;object-fit:cover;border-radius:50%;background:#fff;box-shadow:0 2px 12px 0 rgba(106,130,251,0.13);border:3px solid #fff;padding:6px;margin-right:10px;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="articles.php">Articles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quizzes.php">Quizzes</a>
                    </li>
                    <?php if (isset($_SESSION['role'])): ?>
                        <?php if ($_SESSION['role'] === 'reviewer'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="reviews.php">Reviews</a>
                            </li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin.php">Admin Panel</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="quiz_dashboard.php">Quiz Management</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <section class="section bg-light" style="min-height: 100vh;">
        <div class="container">
            <div class="articles-gradient-banner">
                <h1>Published Articles</h1>
                <p>Discover knowledge shared by our community</p>
            </div>
            <div class="mb-4 d-flex flex-wrap gap-2 justify-content-center">
                <?php foreach ($categories as $cat): ?>
                    <a href="published.php?category=<?php echo urlencode($cat['slug']); ?>"
                       class="category-badge<?php if ($selected_category === $cat['slug']) echo ' active'; ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="row g-4">
                <?php foreach ($articles as $article): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card article-card h-100">
                            <?php if ($article['thumbnail_path']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($article['thumbnail_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($article['title']); ?>" style="height: 220px; object-fit: cover; border-radius: 14px 14px 0 0;">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($article['title']); ?></h5>
                                <div class="article-meta mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-person-circle me-2"></i>
                                        <span><?php echo htmlspecialchars($article['author']); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-folder me-2"></i>
                                        <a href="published.php?category=<?php echo urlencode($article['category_slug']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($article['category_name']); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php if ($article['description']): ?>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($article['description'], 0, 150))); ?>...</p>
                                <?php endif; ?>
                                <?php if ($article['review_count'] > 0): ?>
                                    <div class="d-flex align-items-center mt-3">
                                        <div class="text-warning me-2">
                                            <?php
                                            $rating = round($article['avg_rating']);
                                            for ($i = 0; $i < 5; $i++) {
                                                echo '<i class="bi bi-star' . ($i < $rating ? '-fill' : '') . '"></i>';
                                            }
                                            ?>
                                        </div>
                                        <small class="text-muted">
                                            (<?php echo $article['review_count']; ?> reviews)
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($article['published_at'])); ?></small>
                                    <a href="view_article.php?id=<?php echo $article['id']; ?>" class="btn btn-outline-primary">Read More</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h3 class="footer-title">The Publish Club</h3>
                    <p>A platform for knowledge sharing, learning, and community engagement.</p>
                </div>
                <div class="col-md-4">
                    <h3 class="footer-title">Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="articles.php">Articles</a></li>
                        <li><a href="quizzes.php">Quizzes</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h3 class="footer-title">Connect With Us</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="bi bi-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="bi bi-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="bi bi-linkedin"></i> LinkedIn</a></li>
                        <li><a href="#"><i class="bi bi-github"></i> GitHub</a></li>
                    </ul>
                </div>
            </div>
            <hr class="mt-4 mb-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> The Publish Club. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
