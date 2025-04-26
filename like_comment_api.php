<?php
require_once 'config/config.php';
require_once 'like_comment_utils.php';
session_start();

header('Content-Type: application/json');

$article_id = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
$action = $_POST['action'] ?? '';
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$response = ['success' => false];

if (!$article_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid article id']);
    exit;
}

try {
    switch ($action) {
        case 'like':
            save_article_like($article_id, $user_id, 1);
            $counts = get_article_like_counts($article_id);
            $response = ['success' => true, 'likes' => (int)$counts['likes'], 'dislikes' => (int)$counts['dislikes']];
            break;
        case 'dislike':
            save_article_like($article_id, $user_id, 0);
            $counts = get_article_like_counts($article_id);
            $response = ['success' => true, 'likes' => (int)$counts['likes'], 'dislikes' => (int)$counts['dislikes']];
            break;
        case 'comment':
            $comment = trim($_POST['comment'] ?? '');
            if ($comment !== '') {
                save_article_comment($article_id, $user_id, $comment);
            }
            $comments = get_article_comments($article_id);
            ob_start();
            foreach ($comments as $c) {
                echo '<div class="d-flex align-items-start mb-2">';
                echo '<img src="' . htmlspecialchars($c['profile_image'] ?? 'assets/img/avatar-default.png') . '" class="rounded-circle me-2" style="width:32px;height:32px;object-fit:cover;" alt="Profile">';
                echo '<div><div class="fw-semibold small">' . htmlspecialchars($c['username'] ?? 'Guest') . '</div>';
                echo '<div class="small text-muted">' . htmlspecialchars($c['comment']) . '</div>';
                echo '<div class="small text-secondary">' . date('M j, Y H:i', strtotime($c['created_at'])) . '</div></div></div>';
            }
            $response = ['success'=>true, 'html'=>ob_get_clean(), 'count'=>count($comments)];
            break;
        default:
            $response = ['success' => false, 'error' => 'Unknown action'];
    }
} catch (PDOException $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}
echo json_encode($response);
