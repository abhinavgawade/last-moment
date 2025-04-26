<?php
require_once 'config/config.php';
require_once 'like_comment_utils.php';

// Handle AJAX like/dislike/comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    require 'like_comment_api.php';
    exit;
}

// Get article ID from URL
$article_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$article_id) {
    header('Location: index.php');
    exit();
}

$pdo = db_connect();

// Get user data for navbar profile image
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT username, profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// Allow access if published, author, or assigned reviewer
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        c.name as category_name, 
        c.slug as category_slug, 
        u.username as author, 
        u.profile_image as author_profile_image
    FROM articles a
    JOIN categories c ON a.category_id = c.id
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ? AND (a.status = 'published' OR a.user_id = ? OR a.reviewer_id = ?)
");
$stmt->execute([
    $article_id,
    is_authenticated() ? $_SESSION['user_id'] : 0,
    is_authenticated() ? $_SESSION['user_id'] : 0
]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: index.php');
    exit();
}

// Get like/dislike counts and comments for this article
$like_counts = get_article_like_counts($article_id);
$comments = get_article_comments($article_id);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/view_article.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>
    <style>
        .article-header {
            background: #f8f9fa;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid #dee2e6;
        }
        .article-meta {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .article-category {
            text-decoration: none;
            color: #0d6efd;
        }
        .article-category:hover {
            text-decoration: underline;
        }
        #pdfViewer {
            width: 100%;
            height: 800px;
            border: 1px solid #dee2e6;
            margin: 2rem 0;
        }
        .thumbnail-container {
            max-width: 300px;
            margin-bottom: 2rem;
        }
        .thumbnail-container img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Modern Navbar Matching App Style -->
    <nav class="navbar navbar-expand-lg fixed-top shadow-sm py-3 bg-white">
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
                        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='index.php') echo ' active'; ?>" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='articles.php') echo ' active'; ?>" href="articles.php">Articles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='reviews.php') echo ' active'; ?>" href="reviews.php">Reviews</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='profile.php') echo ' active'; ?>" href="profile.php">Profile</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <?php if($user): ?>
                        <span class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></span>
                        <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'assets/img/avatar-default.png'); ?>" class="rounded-circle" style="width:28px;height:28px;object-fit:cover;" alt="Profile Image">
                        <a href="logout.php" class="btn btn-outline-secondary btn-sm ms-2">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary btn-sm">Login</a>
                        <a href="register.php" class="btn btn-outline-primary btn-sm ms-2">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <div style="height:72px;"></div> <!-- navbar spacer -->

    <div class="container py-4">
        <div class="row g-4">
            <!-- Main Article Content -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <img src="<?php echo htmlspecialchars($article['author_profile_image'] ?? 'assets/img/avatar-default.png'); ?>" class="rounded-circle me-2" style="width:38px;height:38px;object-fit:cover;" alt="Profile Image">
                            <div>
                                <div class="fw-semibold"> <?php echo htmlspecialchars($article['author']); ?> </div>
                                <div class="small text-muted">
                                    <a href="published.php?category=<?php echo urlencode($article['category_slug']); ?>" class="article-category">
                                        <?php echo htmlspecialchars($article['category_name']); ?>
                                    </a>
                                    <?php if ($article['published_at']): ?>
                                        <span class="mx-2">•</span>
                                        <span>Published <?php echo date('M j, Y', strtotime($article['published_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <h1 class="h3 fw-bold mb-3"><?php echo htmlspecialchars($article['title']); ?></h1>
                        <?php if ($article['description']): ?>
                            <div class="mb-3">
                                <h5 class="mb-1">Abstract</h5>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($article['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                        <div id="pdfViewer" class="mb-4" style="width:100%; height:800px;"></div>
                        <script>
                            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';
                            const loadPDF = async () => {
                                const pdfPath = 'uploads/<?php echo htmlspecialchars($article['file_path']); ?>';
                                try {
                                    const loadingTask = pdfjsLib.getDocument(pdfPath);
                                    const pdf = await loadingTask.promise;
                                    const viewer = document.getElementById('pdfViewer');
                                    viewer.innerHTML = '';
                                    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                                        const page = await pdf.getPage(pageNum);
                                        const scale = 1.2;
                                        const viewport = page.getViewport({ scale });
                                        const canvas = document.createElement('canvas');
                                        canvas.style.display = 'block';
                                        canvas.style.margin = '0 auto 16px auto';
                                        const context = canvas.getContext('2d');
                                        canvas.height = viewport.height;
                                        canvas.width = viewport.width;
                                        await page.render({ canvasContext: context, viewport: viewport }).promise;
                                        viewer.appendChild(canvas);
                                    }
                                } catch (error) {
                                    document.getElementById('pdfViewer').innerHTML = '<div class="alert alert-danger">Error loading PDF. Please try again later.</div>';
                                }
                            };
                            loadPDF();
                        </script>
                    </div>
                </div>
            </div>
            <!-- Sidebar: Thumbnail -->
            <div class="col-lg-4">
                <?php if ($article['thumbnail_path']): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-body text-center">
                            <img src="uploads/<?php echo htmlspecialchars($article['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" class="img-fluid rounded mb-2" style="max-width:220px;">
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Social Actions: Like/Dislike/Comment in separate divs -->
                <div class="card shadow-sm mb-3">
                    <div class="card-body text-center">
                        <div id="like-section" class="mb-3">
                            <button class="btn btn-light btn-sm like-btn me-2" data-article-id="<?php echo $article_id; ?>"><i class="bi bi-hand-thumbs-up"></i> <span id="like-count"><?php echo (int)($like_counts['likes'] ?? 0); ?></span></button>
                            <button class="btn btn-light btn-sm dislike-btn" data-article-id="<?php echo $article_id; ?>"><i class="bi bi-hand-thumbs-down"></i> <span id="dislike-count"><?php echo (int)($like_counts['dislikes'] ?? 0); ?></span></button>
                        </div>
                        <div id="comment-section">
                            <button class="btn btn-outline-primary btn-sm w-100 mb-2" data-bs-toggle="collapse" data-bs-target="#comments-section"><i class="bi bi-chat-dots"></i> Comments (<span id="comment-count"><?php echo count($comments); ?></span>)</button>
                            <div class="collapse show" id="comments-section">
                                <div class="comments-list text-start" id="comments-list" style="max-height:200px;overflow-y:auto;">
                                    <?php if (count($comments) === 0): ?>
                                        <div class="text-muted small">No comments yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($comments as $comment): ?>
                                            <div class="d-flex align-items-start mb-2">
                                                <img src="<?php echo htmlspecialchars($comment['profile_image'] ?? 'assets/img/avatar-default.png'); ?>" class="rounded-circle me-2" style="width:32px;height:32px;object-fit:cover;" alt="Profile">
                                                <div>
                                                    <div class="fw-semibold small"><?php echo htmlspecialchars($comment['username'] ?? 'Guest'); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($comment['comment']); ?></div>
                                                    <div class="small text-secondary"><?php echo date('M j, Y H:i', strtotime($comment['created_at'])); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <form id="comment-form" class="mt-2">
                                    <div class="input-group">
                                        <input type="text" name="comment" class="form-control form-control-sm" placeholder="Add a comment..." required>
                                        <button class="btn btn-primary btn-sm" type="submit">Post</button>
                                    </div>
                                    <div id="comment-error" class="text-danger small mt-1"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const articleId = <?php echo $article_id; ?>;
            document.querySelectorAll('.like-btn').forEach(btn => {
                btn.onclick = function() {
                    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax=1&action=like&article_id='+articleId})
                    .then(res=>res.json()).then(data=>{
                        if(data.success) {
                            document.getElementById('like-count').innerText = data.likes;
                            document.getElementById('dislike-count').innerText = data.dislikes;
                        } else if(data.error) {
                            alert('Like error: ' + data.error);
                        }
                    });
                }
            });
            document.querySelectorAll('.dislike-btn').forEach(btn => {
                btn.onclick = function() {
                    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax=1&action=dislike&article_id='+articleId})
                    .then(res=>res.json()).then(data=>{
                        if(data.success) {
                            document.getElementById('like-count').innerText = data.likes;
                            document.getElementById('dislike-count').innerText = data.dislikes;
                        } else if(data.error) {
                            alert('Dislike error: ' + data.error);
                        }
                    });
                }
            });
            const commentForm = document.getElementById('comment-form');
            const commentError = document.getElementById('comment-error');
            if(commentForm) {
                commentForm.onsubmit = function(e) {
                    e.preventDefault();
                    commentError.textContent = '';
                    const formData = new FormData(commentForm);
                    fetch('', {method:'POST', body:new URLSearchParams([...formData, ['ajax','1'], ['action','comment'], ['article_id',articleId]])})
                    .then(res=>res.json()).then(data=>{
                        if(data.success) {
                            document.getElementById('comments-list').innerHTML = data.html;
                            document.getElementById('comment-count').innerText = data.count;
                            commentForm.reset();
                        } else if(data.error) {
                            commentError.textContent = data.error;
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
