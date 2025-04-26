<?php
require_once 'config/config.php';

function get_article_like_counts($article_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 
        SUM(is_like = 1) as likes, 
        SUM(is_like = 0) as dislikes
        FROM article_likes WHERE article_id = ?");
    $stmt->execute([$article_id]);
    return $stmt->fetch();
}

function get_article_comments($article_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT ac.*, u.username, u.profile_image FROM article_comments ac LEFT JOIN users u ON ac.user_id = u.id WHERE ac.article_id = ? ORDER BY ac.created_at ASC");
    $stmt->execute([$article_id]);
    return $stmt->fetchAll();
}

function save_article_like($article_id, $user_id, $is_like) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO article_likes (article_id, user_id, is_like) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_like = VALUES(is_like)");
        $stmt->execute([$article_id, $user_id, $is_like]);
    } catch (PDOException $e) {
        error_log('Like insert error: ' . $e->getMessage());
        throw $e;
    }
}

function save_article_comment($article_id, $user_id, $comment) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO article_comments (article_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$article_id, $user_id, $comment]);
    } catch (PDOException $e) {
        error_log('Comment insert error: ' . $e->getMessage());
        throw $e;
    }
}
