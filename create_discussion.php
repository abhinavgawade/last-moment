<?php
// Prevent duplicate session_start warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); // Hide notices and warnings
require_once 'config/config.php';

// Only admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$pdo = db_connect();

// Handle topic creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $banner_img_path = null;
    if (isset($_FILES['banner_img']) && $_FILES['banner_img']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['banner_img']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $upload_dir = 'uploads/banners/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $fname = uniqid('banner_', true) . '.' . $ext;
            $dest = $upload_dir . $fname;
            if (move_uploaded_file($_FILES['banner_img']['tmp_name'], $dest)) {
                $banner_img_path = $dest;
            }
        }
    }
    $stmt = $pdo->prepare("INSERT INTO interaction_topics (title, description, created_by, is_active, banner_img) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['title'],
        $_POST['description'],
        $_SESSION['user_id'],
        isset($_POST['is_active']) ? 1 : 0,
        $banner_img_path
    ]);
    header('Location: admin_interaction.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Discussion Room</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container py-5" style="margin-top: 80px; max-width: 500px;">
    <h2 class="mb-4 text-center">Create New Discussion Room</h2>
    <form method="post" enctype="multipart/form-data" class="mb-4">
        <div class="mb-2">
            <label class="form-label">Topic Name</label>
            <input type="text" name="title" class="form-control" placeholder="Topic Name" required>
        </div>
        <div class="mb-2">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" placeholder="Description" rows="2"></textarea>
        </div>
        <div class="mb-2">
            <label class="form-label">Banner Image (optional)</label>
            <input type="file" name="banner_img" accept="image/*" class="form-control">
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <button class="btn btn-primary w-100" type="submit">Create Room</button>
    </form>
</div>
</body>
</html>
