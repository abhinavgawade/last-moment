<?php
require_once 'config/config.php';
session_start();
$pdo = db_connect();

function render_messages($messages, $user_id) {
    ob_start();
    foreach ($messages as $msg) {
        echo '<div class="chat-msg' . (($msg['user_id'] == $user_id) ? ' me' : '') . '">';
        echo '<img class="profile me-2" src="' . htmlspecialchars($msg['profile_image'] ?? 'assets/img/avatar-default.png') . '" alt="Profile">';
        echo '<span class="fw-semibold small">' . htmlspecialchars($msg['username'] ?? 'Guest') . '</span>';
        echo '<span class="text-secondary small ms-2">' . date('M j, H:i', strtotime($msg['created_at'])) . '</span><br>';
        if ($msg['message_type'] === 'text') {
            echo '<span class="bubble">' . htmlspecialchars($msg['message_text']) . '</span>';
        } elseif ($msg['message_type'] === 'audio' && $msg['audio_path']) {
            echo '<span class="audio-msg"><audio controls src="' . htmlspecialchars($msg['audio_path']) . '"></audio></span>';
        }
        echo '</div>';
    }
    return ob_get_clean();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    // For guests, assign a special user_id = NULL (already supported by schema), and set username as 'Guest' in the UI.
    // No block for guests.
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$topic_id = $_POST['topic_id'] ?? $_GET['topic_id'] ?? null;

$response = ['success' => false];

try {
    if ($action === 'send') {
        $message_type = $_POST['message_type'] ?? 'text';
        if ($message_type === 'audio' && isset($_FILES['audio_blob']) && $_FILES['audio_blob']['error'] === 0) {
            $upload_dir = 'uploads/audio/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = uniqid('audio_') . '.webm';
            move_uploaded_file($_FILES['audio_blob']['tmp_name'], $upload_dir . $filename);
            $audio_path = $upload_dir . $filename;
            $stmt = $pdo->prepare("INSERT INTO interaction_messages (topic_id, user_id, message_type, audio_path) VALUES (?, ?, 'audio', ?)");
            $stmt->execute([$topic_id, $user_id, $audio_path]);
        } elseif ($message_type === 'text') {
            $msg = trim($_POST['message_text'] ?? '');
            if ($msg !== '') {
                $stmt = $pdo->prepare("INSERT INTO interaction_messages (topic_id, user_id, message_type, message_text) VALUES (?, ?, 'text', ?)");
                $stmt->execute([$topic_id, $user_id, $msg]);
            } else {
                throw new Exception('Message cannot be empty.');
            }
        }
        // Fetch messages after insert
        $msgStmt = $pdo->prepare("SELECT m.*, u.username, u.profile_image FROM interaction_messages m LEFT JOIN users u ON m.user_id = u.id WHERE m.topic_id = ? ORDER BY m.created_at ASC LIMIT 50");
        $msgStmt->execute([$topic_id]);
        $messages = $msgStmt->fetchAll();
        $response = ['success' => true, 'html' => render_messages($messages, $user_id)];
    } elseif ($action === 'fetch' && $topic_id) {
        $msgStmt = $pdo->prepare("SELECT m.*, u.username, u.profile_image FROM interaction_messages m LEFT JOIN users u ON m.user_id = u.id WHERE m.topic_id = ? ORDER BY m.created_at ASC LIMIT 50");
        $msgStmt->execute([$topic_id]);
        $messages = $msgStmt->fetchAll();
        $response = ['success' => true, 'html' => render_messages($messages, $user_id)];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
} catch (PDOException $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);
