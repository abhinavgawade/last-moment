<?php
require_once 'config/config.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = db_connect();
$error = '';
$success = '';

// Fetch user data
$stmt = $pdo->prepare("SELECT username, email, role, profile_image FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // User not found in DB, log out and redirect to login
    session_destroy();
    header('Location: login.php');
    exit();
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Securely fetch POST fields for password update
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $profile_image_path = $user['profile_image'];

    // Profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $profile_image = $_FILES['profile_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($profile_image['type'], $allowed_types)) {
            $profile_image_dir = __DIR__ . '/uploads/profile_images/';
            if (!file_exists($profile_image_dir)) {
                mkdir($profile_image_dir, 0777, true);
            }
            $profile_image_filename = uniqid() . '_' . basename($profile_image['name']);
            $profile_image_path = 'uploads/profile_images/' . $profile_image_filename;
            move_uploaded_file($profile_image['tmp_name'], $profile_image_dir . $profile_image_filename);
            // Update profile_image in DB
            $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->execute([$profile_image_path, $_SESSION['user_id']]);
            $user['profile_image'] = $profile_image_path;
            $_SESSION['profile_image'] = $profile_image_path;
            $success = 'Profile image updated successfully!';
        }
    }

    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stored_password = $stmt->fetchColumn();
    
    if (!password_verify($current_password, $stored_password)) {
        $error = 'Current password is incorrect';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            $success = 'Password updated successfully';
        } catch (PDOException $e) {
            $error = 'Failed to update password';
        }
    }
}

// Fetch user statistics
$stats = [];
if ($user['role'] === 'user') {
    // Get article statistics for regular users
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_articles,
            COUNT(CASE WHEN status = 'published' THEN 1 END) as published_articles,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_articles,
            COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review_articles
        FROM articles 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} elseif ($user['role'] === 'reviewer') {
    // Get review statistics for reviewers
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_reviews
        FROM reviews 
        WHERE reviewer_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} elseif ($user['role'] === 'admin') {
    $stats = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/profile.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="hero-banner">
        <div class="container">
            <div class="hero-content text-center">
                <div class="profile-avatar mb-3">
                    <i class="bi bi-person-circle display-1"></i>
                </div>
                <h1 class="hero-title"><?php echo htmlspecialchars($user['username']); ?></h1>
                <p class="hero-subtitle"><?php echo ucfirst($user['role']); ?></p>
            </div>
        </div>
    </div>

    <section class="section bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-4">
                    <div class="card mb-4 text-center">
                        <div class="card-body">
                            <!-- Profile Image Display -->
                            <div class="mb-3">
                                <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'assets/img/avatar-default.png'); ?>" class="avatar avatar-lg mb-2" alt="Profile Image">
                                <form method="POST" enctype="multipart/form-data" class="d-inline-block">
                                    <input type="file" name="profile_image" accept="image/*" class="form-control mb-2" style="max-width:200px;display:inline-block;">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Change Profile Image</button>
                                </form>
                            </div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($user['username']); ?></h5>
                            <div class="badge badge-role bg-primary text-white text-capitalize mt-2">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </div>
                            <div class="mt-2 small text-muted"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Statistics</h5>
                            <?php if ($user['role'] === 'user'): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="me-2">Total Articles:</span>
                                    <span class="fw-bold"><?php echo (int)($stats['total_articles'] ?? 0); ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="me-2">Published:</span>
                                    <span class="fw-bold"><?php echo (int)($stats['published_articles'] ?? 0); ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="me-2">Pending:</span>
                                    <span class="fw-bold"><?php echo (int)($stats['pending_articles'] ?? 0); ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="me-2">Under Review:</span>
                                    <span class="fw-bold"><?php echo (int)($stats['under_review_articles'] ?? 0); ?></span>
                                </div>
                            <?php elseif ($user['role'] === 'reviewer'): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="me-2">Total Reviews:</span>
                                    <span class="fw-bold"><?php echo (int)($stats['total_reviews'] ?? 0); ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="me-2">Average Rating:</span>
                                    <span class="fw-bold"><?php echo number_format((float)($stats['avg_rating'] ?? 0), 2); ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="me-2">Recent Reviews (30 days):</span>
                                    <span class="fw-bold"><?php echo (int)($stats['recent_reviews'] ?? 0); ?></span>
                                </div>
                            <?php elseif ($user['role'] === 'admin'): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="me-2 text-success"><i class="bi bi-shield-lock"></i> You are an admin. You can manage users, articles, and site settings.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Change Password</h5>
                            <form method="POST">
                                <div class="mb-4">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-key"></i>
                                        </span>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-key-fill"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-shield-lock me-2"></i>
                                    Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
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
