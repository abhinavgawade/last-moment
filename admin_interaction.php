<?php
require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Enable full error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Only admin access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
            if (!is_writable($upload_dir)) {
                die('Upload directory is not writable.');
            }
            $fname = uniqid('banner_', true) . '.' . $ext;
            $dest = $upload_dir . $fname;
            if (move_uploaded_file($_FILES['banner_img']['tmp_name'], $dest)) {
                $banner_img_path = $dest;
            } else {
                die('Failed to move uploaded file.');
            }
        } else {
            die('Invalid file type for banner image.');
        }
    }
    $stmt = $pdo->prepare("INSERT INTO interaction_topics (title, description, created_by, is_active, banner_img) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['title'] ?? '',
        $_POST['description'] ?? '',
        $_SESSION['user_id'],
        isset($_POST['is_active']) ? 1 : 0,
        $banner_img_path
    ]);
    header('Location: admin_interaction.php');
    exit();
}

// Handle topic activation/deactivation
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("UPDATE interaction_topics SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    header('Location: admin_interaction.php');
    exit();
}

// Fetch all topics
$stmt = $pdo->query("SELECT t.*, u.username as admin_name FROM interaction_topics t JOIN users u ON t.created_by = u.id ORDER BY t.created_at DESC");
$topics = $stmt->fetchAll();

// Get user data for navbar
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT username, profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Discussion Topics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/custom-pro.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container py-5" style="margin-top: 80px; max-width: 800px;">
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
    <h4 class="mb-3">All Discussion Rooms</h4>
    <table class="table table-bordered">
        <thead><tr><th>Banner</th><th>Title</th><th>Description</th><th>Created By</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($topics as $topic): ?>
            <tr>
                <td><?php if (!empty($topic['banner_img'])): ?><img src="<?php echo htmlspecialchars($topic['banner_img']); ?>" alt="Banner" style="height:40px;max-width:80px;object-fit:cover;border-radius:4px;"><?php endif; ?></td>
                <td><?php echo htmlspecialchars($topic['title']); ?></td>
                <td><?php echo htmlspecialchars($topic['description']); ?></td>
                <td><?php echo htmlspecialchars($topic['admin_name']); ?></td>
                <td><?php echo $topic['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td>
                <td><a href="?toggle=1&id=<?php echo $topic['id']; ?>" class="btn btn-sm btn-outline-warning"><?php echo $topic['is_active'] ? 'Deactivate' : 'Activate'; ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
