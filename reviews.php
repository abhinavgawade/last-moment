<?php
require_once './config/config.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is reviewer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'reviewer') {
    header('Location: login.php');
    exit();
}

$pdo = db_connect();

// Get reviewer statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN status = 'under_review' THEN a.id END) as pending_reviews,
        COUNT(DISTINCT r.id) as completed_reviews,
        AVG(CASE WHEN r.recommendation = 'approve' THEN 1 ELSE 0 END) * 100 as approval_rate
    FROM articles a
    LEFT JOIN reviews r ON a.id = r.article_id AND r.reviewer_id = ?
    WHERE a.reviewer_id = ? OR r.reviewer_id = ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$stats = $stmt->fetch();

// Get assigned articles for review
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.title,
        a.abstract,
        a.thumbnail_path,
        a.file_path,
        a.created_at,
        u.username as author,
        COUNT(DISTINCT r.id) as review_count,
        MAX(r2.reviewed_at) as my_review_date,
        r2.rating as my_rating,
        r2.comments as my_comments,
        r2.recommendation as my_recommendation,
        DATEDIFF(CURRENT_TIMESTAMP, a.created_at) as days_pending
    FROM articles a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN reviews r ON a.id = r.article_id
    LEFT JOIN reviews r2 ON a.id = r2.article_id AND r2.reviewer_id = ?
    WHERE a.reviewer_id = ? AND a.status = 'under_review'
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$assigned_articles = $stmt->fetchAll();

// Get completed reviews
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        u.username as author,
        r.rating,
        r.comments,
        r.recommendation,
        r.reviewed_at
    FROM reviews r
    JOIN articles a ON r.article_id = a.id
    JOIN users u ON a.user_id = u.id
    WHERE r.reviewer_id = ?
    ORDER BY r.reviewed_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$completed_reviews = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./assets/css/style.css" rel="stylesheet">
    <link href="assets/css/reviews.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="hero-banner">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">Article Reviews</h1>
                <p class="hero-subtitle">Review and provide feedback on submitted articles</p>
            </div>
        </div>
    </div>

    <section class="section bg-light">
        <div class="container">
            <!-- Review Statistics -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card review-stats-card text-center shadow-sm py-3">
                        <div class="card-body">
                            <div class="mb-2">
                                <i class="bi bi-hourglass-split display-5 text-warning"></i>
                            </div>
                            <h3 class="fw-bold mb-0"><?php echo $stats['pending_reviews']; ?></h3>
                            <div class="text-muted">Pending Reviews</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card review-stats-card text-center shadow-sm py-3">
                        <div class="card-body">
                            <div class="mb-2">
                                <i class="bi bi-check-circle display-5 text-success"></i>
                            </div>
                            <h3 class="fw-bold mb-0"><?php echo $stats['completed_reviews']; ?></h3>
                            <div class="text-muted">Completed Reviews</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card review-stats-card text-center shadow-sm py-3">
                        <div class="card-body">
                            <div class="mb-2">
                                <i class="bi bi-bar-chart-line display-5 text-primary"></i>
                            </div>
                            <h3 class="fw-bold mb-0"><?php echo number_format($stats['approval_rate'], 1); ?>%</h3>
                            <div class="text-muted">Approval Rate</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="card-title mb-0">Assigned Articles</h5>
                                <input type="text" class="form-control" id="articleSearch" placeholder="Search articles..." style="max-width: 200px;">
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assigned_articles)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-journal-text"></i>
                                    <h4>No Pending Reviews</h4>
                                    <p>You don't have any articles assigned for review at the moment.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Article</th>
                                                <th>Author</th>
                                                <th>Days Pending</th>
                                                <th>Download</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assigned_articles as $article): ?>
                                                <tr>
                                                    <td>
                                                        <div class="article-preview d-flex align-items-center gap-2">
                                                            <?php if (!empty($article['thumbnail_path'])): ?>
                                                                <img src="uploads/<?php echo htmlspecialchars($article['thumbnail_path']); ?>" style="width:38px;height:38px;object-fit:cover;border-radius:6px;" alt="Thumbnail">
                                                            <?php else: ?>
                                                                <img src="./assets/images/default-article.jpg" style="width:38px;height:38px;object-fit:cover;border-radius:6px;" alt="No Thumbnail">
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($article['title']); ?></div>
                                                                <div class="small text-muted"><?php echo htmlspecialchars($article['abstract']); ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($article['author']); ?></td>
                                                    <td><?php echo $article['days_pending']; ?></td>
                                                    <td>
                                                        <a href="uploads/<?php echo htmlspecialchars($article['file_path']); ?>" download class="btn btn-sm btn-outline-success">
                                                            <i class="bi bi-download"></i> PDF
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <div class="review-actions">
                                                            <?php if (!empty($article['id'])): ?>
                                                                <a href="view_article.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-eye"></i> View
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-outline-secondary" disabled title="Article ID missing">View</button>
                                                            <?php endif; ?>
                                                            <?php if (!$article['my_review_date']): ?>
                                                                <a href="submit_review.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-success">
                                                                    <i class="bi bi-pencil"></i> Review
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="submit_review.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                                    <i class="bi bi-pencil"></i> Edit Review
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0">Recent Reviews</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($completed_reviews)): ?>
                                <div class="empty-state py-4">
                                    <i class="bi bi-clipboard-check"></i>
                                    <h4>No Reviews Yet</h4>
                                    <p>You haven't completed any reviews yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($completed_reviews as $review): ?>
                                    <div class="review-history-item">
                                        <a href="view_article.php?id=<?php echo $review['id']; ?>" class="review-article-title">
                                            <?php echo htmlspecialchars($review['title']); ?>
                                        </a>
                                        <div class="review-history-meta">
                                            <span class="review-recommendation <?php echo $review['recommendation']; ?>">
                                                <?php if ($review['recommendation'] === 'approve'): ?>
                                                    <i class="bi bi-check-circle"></i> Approved
                                                <?php else: ?>
                                                    <i class="bi bi-x-circle"></i> Rejected
                                                <?php endif; ?>
                                            </span>
                                            <span class="review-date">
                                                <i class="bi bi-calendar3"></i>
                                                <?php echo date('M j, Y', strtotime($review['reviewed_at'])); ?>
                                            </span>
                                        </div>
                                        <?php if ($review['comments']): ?>
                                            <div class="review-comments">
                                                <?php echo nl2br(htmlspecialchars($review['comments'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        .hero-banner {
            background: #fff!important;
            border-radius: 18px!important;
            box-shadow: 0 6px 32px 0 rgba(106,130,251,0.09)!important;
            color: #222;
            border: 2px solid #111!important;
        }
        .section.bg-light, .card, .review-stats-card, .card .card-header.bg-white {
            background: #fff!important;
            border-radius: 18px!important;
            box-shadow: 0 6px 32px 0 rgba(106,130,251,0.09)!important;
            border: 2px solid #111!important;
        }
        body {
            background: #f8fafc!important;
            min-height: 100vh;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const articleSearch = document.getElementById('articleSearch');
            const articleTable = document.querySelector('.table');
            
            if (articleSearch && articleTable) {
                articleSearch.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const rows = articleTable.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const title = row.querySelector('.fw-semibold').textContent.toLowerCase();
                        const author = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        const status = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                        
                        const matches = title.includes(searchTerm) || 
                                      author.includes(searchTerm) || 
                                      status.includes(searchTerm);
                        
                        row.style.display = matches ? '' : 'none';
                    });
                    
                    // Show/hide empty state message
                    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
                    const tableContainer = document.querySelector('.table-responsive');
                    const emptyState = document.querySelector('.empty-state');
                    
                    if (visibleRows.length === 0) {
                        if (!document.querySelector('.search-empty-state')) {
                            const searchEmptyState = document.createElement('div');
                            searchEmptyState.className = 'empty-state search-empty-state';
                            searchEmptyState.innerHTML = `
                                <i class="bi bi-search"></i>
                                <h4>No Results Found</h4>
                                <p>No articles match your search criteria. Try adjusting your search terms.</p>
                            `;
                            tableContainer.parentNode.insertBefore(searchEmptyState, tableContainer);
                        }
                        document.querySelector('.search-empty-state').style.display = 'block';
                        tableContainer.style.display = 'none';
                    } else {
                        const searchEmptyState = document.querySelector('.search-empty-state');
                        if (searchEmptyState) {
                            searchEmptyState.style.display = 'none';
                        }
                        tableContainer.style.display = 'block';
                    }
                });
            }
        });
    </script>
</body>
</html>
