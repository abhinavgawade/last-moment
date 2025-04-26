<?php
require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$pdo = db_connect();

// Fetch all active topics (rooms)
$topics = $pdo->prepare("SELECT * FROM interaction_topics WHERE is_active = 1 ORDER BY created_at DESC");
$topics->execute();
$active_topics = $topics->fetchAll();

// Only show chat if user has joined a room
$has_joined = isset($_SESSION['joined_discussion']);
$joined_topic_id = $_SESSION['joined_discussion'] ?? null;
if (isset($_POST['join_room']) && isset($_POST['topic_id'])) {
    $_SESSION['joined_discussion'] = $_POST['topic_id'];
    $has_joined = true;
    $joined_topic_id = $_POST['topic_id'];
}
if (isset($_POST['leave_room'])) {
    unset($_SESSION['joined_discussion']);
    $has_joined = false;
    $joined_topic_id = null;
}

// Get the topic user has joined (if any)
$topic = null;
if ($joined_topic_id) {
    $stmt = $pdo->prepare("SELECT * FROM interaction_topics WHERE id = ? AND is_active = 1");
    $stmt->execute([$joined_topic_id]);
    $topic = $stmt->fetch();
}

// Get user info for navbar
$user = null;
$role = $_SESSION['role'] ?? null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT username, profile_image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// Get messages for the joined topic
$messages = [];
if ($topic) {
    $msgStmt = $pdo->prepare("SELECT m.*, u.username, u.profile_image FROM interaction_messages m LEFT JOIN users u ON m.user_id = u.id WHERE m.topic_id = ? ORDER BY m.created_at ASC LIMIT 50");
    $msgStmt->execute([$topic['id']]);
    $messages = $msgStmt->fetchAll();
}

$user_id = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Room</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom-pro.css" rel="stylesheet">
    <style>
        body { background: #f7f7f9; font-family: 'Inter', Arial, sans-serif; }
        .chat-box { max-height: 400px; overflow-y: auto; background: #fff; border-radius: 8px; padding: 16px; }
        .chat-msg { margin-bottom: 16px; }
        .chat-msg .profile { width: 36px; height: 36px; object-fit: cover; border-radius: 50%; }
        .chat-msg .bubble { background: #f1f3f5; border-radius: 12px; padding: 8px 12px; display: inline-block; }
        .chat-msg.me .bubble { background: #d1e7dd; }
        .banner-img { height:80px; max-width:180px; object-fit:cover; border-radius:8px; }
        .leave-btn { margin-top: 32px; }
        .rate-stars i { cursor:pointer; font-size: 1.5rem; color: #ffc107; }
        .rate-stars i.inactive { color: #e4e5e9; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="assets/img/publishclub-logo.png" alt="The Publish Club Logo" style="height:72px;width:72px;object-fit:cover;border-radius:50%;background:#fff;box-shadow:0 2px 12px 0 rgba(106,130,251,0.13);border:3px solid #fff;padding:6px;margin-right:10px;">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="articles.php">Articles</a></li>
                    <li class="nav-item"><a class="nav-link active" href="interaction.php">Discussion</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_interaction.php">Admin Panel</a></li>
                    <li class="nav-item"><a class="nav-link" href="create_discussion.php">Create Discussion</a></li>
                <?php elseif ($role === 'reviewer'): ?>
                    <li class="nav-item"><a class="nav-link" href="reviews.php">Reviews</a></li>
                    <li class="nav-item"><a class="nav-link active" href="interaction.php">Discussion</a></li>
                    <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                <?php elseif ($role === 'user'): ?>
                    <li class="nav-item"><a class="nav-link" href="articles.php">Articles</a></li>
                    <li class="nav-item"><a class="nav-link active" href="interaction.php">Discussion</a></li>
                    <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($user): ?>
                    <li class="nav-item d-flex align-items-center">
                        <span class="me-2 fw-semibold"><?php echo htmlspecialchars($user['username']); ?></span>
                        <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'assets/img/avatar-default.png'); ?>" class="rounded-circle me-2" style="width:32px;height:32px;object-fit:cover;" alt="Profile Image">
                        <a href="logout.php" class="btn btn-outline-secondary btn-sm ms-2">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-primary" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-primary" href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container py-5" style="max-width: 1200px; margin-top: 80px;">
    <div class="mb-4">
        <h2 class="fw-bold">Active Discussion Rooms</h2>
        <div class="d-flex flex-column gap-4">
            <?php foreach ($active_topics as $room): ?>
                <div class="card shadow-sm p-4 d-flex flex-row align-items-center gap-5 <?php if($joined_topic_id == $room['id']) echo 'border-primary'; ?>"
                     style="max-width:1000px; min-height:180px; margin:0 auto; background: linear-gradient(90deg,#6a82fb 0%,#fc5c7d 100%); border-radius: 20px; box-shadow: 0 4px 32px 0 rgba(106,130,251,0.13);">
                    <?php if (!empty($room['banner_img'])): ?>
                        <img src="<?php echo htmlspecialchars($room['banner_img']); ?>"
                             style="max-width:200px;max-height:200px;width:auto;height:auto;border-radius:18px;border:3px solid #fff;background:#fff;box-shadow:0 2px 12px 0 rgba(106,130,251,0.13);">
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-1" style="font-size:1.3rem; color:#fff;"> <?php echo htmlspecialchars($room['title']); ?> </div>
                        <div class="text-light" style="font-size:1.05rem;"> <?php echo htmlspecialchars($room['description']); ?> </div>
                    </div>
                    <form method="post" class="d-flex align-items-center ms-auto">
                        <input type="hidden" name="topic_id" value="<?php echo $room['id']; ?>">
                        <?php if ($joined_topic_id == $room['id']): ?>
                            <button type="submit" name="leave_room" class="btn btn-outline-light btn-lg">Leave</button>
                        <?php else: ?>
                            <button type="submit" name="join_room" class="btn btn-light btn-lg fw-semibold">Join</button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if ($topic): ?>
    <div class="discussion-banner py-5 mb-4 rounded-4 d-flex flex-column flex-md-row align-items-center justify-content-between px-5" style="background: linear-gradient(90deg,#6a82fb,#fc5c7d); min-height: 220px;">
        <div class="d-flex align-items-center gap-4">
            <?php if (!empty($topic['banner_img'])): ?>
                <img src="<?php echo htmlspecialchars($topic['banner_img']); ?>" style="max-width:200px;max-height:200px;border-radius:18px;border:3px solid #fff;background:#fff;box-shadow:0 2px 12px 0 rgba(106,130,251,0.13);">
            <?php endif; ?>
            <div>
                <h2 class="fw-bold mb-2" style="color:#fff;"> <?php echo htmlspecialchars($topic['title']); ?> </h2>
                <div class="mb-2" style="color:#f8fafc;font-size:1.1rem;font-weight:500;">
                    <?php echo htmlspecialchars($topic['description']); ?>
                </div>
            </div>
        </div>
        <form method="post" class="mt-4 mt-md-0">
            <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
            <?php if (!$has_joined): ?>
                <button type="submit" name="join_room" class="btn btn-light btn-lg fw-semibold shadow">Join the Interaction</button>
            <?php else: ?>
                <button type="submit" name="leave_room" class="btn btn-outline-light btn-lg fw-semibold shadow">Leave</button>
            <?php endif; ?>
        </form>
    </div>
    <div id="chat-box" class="my-5 chat-box">
        <?php foreach ($messages as $msg): ?>
            <div class="chat-msg<?php echo ($msg['user_id'] == $user_id) ? ' me' : ''; ?>">
                <img class="profile me-2" src="<?php echo htmlspecialchars($msg['profile_image'] ?? 'assets/img/avatar-default.png'); ?>" alt="Profile">
                <span class="fw-semibold small"><?php echo htmlspecialchars($msg['username'] ?? 'Guest'); ?></span>
                <span class="text-secondary small ms-2"><?php echo date('M j, H:i', strtotime($msg['created_at'])); ?></span><br>
                <?php if ($msg['message_type'] === 'text'): ?>
                    <span class="bubble"><?php echo htmlspecialchars($msg['message_text']); ?></span>
                <?php elseif ($msg['message_type'] === 'audio' && $msg['audio_path']): ?>
                    <span class="audio-msg"><audio controls src="<?php echo htmlspecialchars($msg['audio_path']); ?>"></audio></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="post" class="text-center leave-btn">
        <button name="leave_room" class="btn btn-outline-danger px-4">Leave Room</button>
    </form>
    <div class="text-center mt-3">
        <span class="fw-semibold">Rate the Room:</span>
        <span class="rate-stars">
            <i class="bi bi-star inactive"></i>
            <i class="bi bi-star inactive"></i>
            <i class="bi bi-star inactive"></i>
            <i class="bi bi-star inactive"></i>
            <i class="bi bi-star inactive"></i>
        </span>
    </div>
    <form id="chat-form" enctype="multipart/form-data" autocomplete="off">
        <div class="input-group mb-2">
            <input type="text" name="message_text" id="message_text" class="form-control" placeholder="Type a message..." autocomplete="off">
            <button class="btn btn-primary" type="submit">Send</button>
            <button class="btn btn-outline-secondary" type="button" id="mic-btn"><i class="bi bi-mic"></i></button>
        </div>
        <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
        <input type="hidden" name="action" value="send">
        <input type="file" name="audio_blob" id="audio_blob" style="display:none;">
        <div id="recording-status" class="text-danger small mb-2"></div>
        <div id="chat-error" class="text-danger small mb-2"></div>
    </form>
    <?php endif; ?>
    <?php if (empty($active_topics)): ?>
        <div class="alert alert-warning text-center">No active discussion topic found.</div>
    <?php endif; ?>
</div>
<script>
let recording = false, mediaRecorder, audioChunks = [];
const micBtn = document.getElementById('mic-btn');
const recordingStatus = document.getElementById('recording-status');
const audioBlobInput = document.getElementById('audio_blob');

micBtn.onclick = async function() {
    if (!recording) {
        if (!navigator.mediaDevices) {
            recordingStatus.textContent = 'Audio recording not supported.';
            return;
        }
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
        mediaRecorder.onstop = () => {
            const blob = new Blob(audioChunks, { type: 'audio/webm' });
            const file = new File([blob], 'voice.webm', { type: 'audio/webm' });
            const dt = new DataTransfer();
            dt.items.add(file);
            audioBlobInput.files = dt.files;
            document.getElementById('chat-form').dispatchEvent(new Event('submit'));
        };
        mediaRecorder.start();
        recording = true;
        recordingStatus.textContent = 'Recording... Click mic again to stop.';
    } else {
        mediaRecorder.stop();
        recording = false;
        recordingStatus.textContent = '';
    }
};

document.getElementById('chat-form').onsubmit = function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    if (audioBlobInput.files.length > 0) {
        formData.append('message_type', 'audio');
    } else {
        formData.append('message_type', 'text');
    }
    fetch('interaction_api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('chat-box').innerHTML = data.html;
                form.reset();
                audioBlobInput.value = '';
                document.getElementById('chat-error').textContent = '';
                // Scroll to bottom after sending
                const chatBox = document.getElementById('chat-box');
                chatBox.scrollTop = chatBox.scrollHeight;
            } else if (data.error) {
                document.getElementById('chat-error').textContent = data.error;
            }
        });
};

// Optional: Poll for new messages every 1 second for near-instant updates
globalThis.interactionTopicId = <?php echo $topic['id']; ?>;
setInterval(() => {
    fetch('interaction_api.php?action=fetch&topic_id=' + globalThis.interactionTopicId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('chat-box').innerHTML = data.html;
                // Scroll to bottom on update
                const chatBox = document.getElementById('chat-box');
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });
}, 1000); // Poll every 1 second for near-instant updates

// Simple star rating UI (visual only)
document.querySelectorAll('.rate-stars').forEach(function(starGroup) {
    let stars = starGroup.querySelectorAll('i');
    stars.forEach(function(star, idx) {
        star.addEventListener('click', function() {
            stars.forEach((s, i) => s.classList.toggle('inactive', i > idx));
        });
    });
});
</script>
</body>
</html>
