<?php // Universal Navbar for The Publish Club (logo only)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? null;
$activePage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? '';
$profile_img = $_SESSION['profile_image'] ?? 'assets/img/avatar-default.png';
// Use correct logo path for all cases
$logo_path = 'assets/img/publishclub-logo.png';
if (!file_exists($logo_path)) {
    // fallback: show a text logo if image missing
    $logo_html = '<span style="font-weight:bold;font-size:2rem;color:#6a82fb;">Th</span>';
} else {
    $logo_html = '<img src="' . $logo_path . '" alt="The Publish Club Logo" style="height:56px;width:56px;object-fit:cover;border-radius:50%;background:#fff;box-shadow:0 2px 12px 0 rgba(106,130,251,0.13);border:3px solid #fff;padding:6px;margin-right:10px;">';
}
?>
<nav class="navbar navbar-expand-lg fixed-top shadow-sm py-3" style="background: linear-gradient(90deg, #f8fafc 80%, #c7c5f4 100%); border-bottom: 2px solid #e0e0e0;">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <?php echo $logo_html; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto align-items-center">
                <li class="nav-item"><a class="nav-link<?php if($activePage=="index.php")echo' active'; ?> fw-semibold px-3" style="<?php if($activePage=="index.php")echo'background:linear-gradient(90deg,#6a82fb,#fc5c7d);color:#fff;border-radius:8px;'; ?>" href="index.php">Home</a></li>
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link<?php if($activePage=="admin.php")echo' active'; ?> fw-semibold px-3" href="admin.php">Admin Panel</a></li>
                    <li class="nav-item"><a class="nav-link<?php if($activePage=="create_discussion.php")echo' active'; ?> fw-semibold px-3" href="create_discussion.php">Create Discussion</a></li>
                    <li class="nav-item"><a class="nav-link<?php if($activePage=="profile.php")echo' active'; ?> fw-semibold px-3" href="profile.php">Profile</a></li>
                <?php elseif ($role === 'reviewer'): ?>
                    <li class="nav-item"><a class="nav-link<?php if($activePage=="interaction.php")echo' active'; ?> fw-semibold px-3" href="interaction.php">Discussion</a></li>
                    <li class="nav-item"><a class="nav-link<?php if($activePage=="articles.php")echo' active'; ?> fw-semibold px-3" href="articles.php">Articles</a></li>
                    <li class="nav-item"><a class="nav-link<?php if($activePage=="reviews.php")echo' active'; ?> fw-semibold px-3" href="reviews.php">Review</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link<?php if($activePage=="published.php")echo' active'; ?> fw-semibold px-3" href="published.php">Articles</a></li>
                    <li class="nav-item"><a class="nav-link<?php if($activePage=="interaction.php")echo' active'; ?> fw-semibold px-3" href="interaction.php">Discussion</a></li>
                    <li class="nav-item"><a class="nav-link<?php if($activePage=="profile.php")echo' active'; ?> fw-semibold px-3<?php if(!$role)echo' d-none'; ?>" href="profile.php">Profile</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <?php if ($role === 'user' || $role === 'admin' || $role === 'reviewer'): ?>
                <li class="nav-item d-flex align-items-center">
                    <span class="me-2 fw-semibold" style="font-size:1.05rem;letter-spacing:.01em;">
                        <?php echo htmlspecialchars($username); ?>
                    </span>
                    <img src="<?php echo htmlspecialchars($profile_img); ?>" class="rounded-circle me-2" style="width:32px;height:32px;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 8px 0 rgba(106,130,251,0.13);" alt="Profile Image">
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
<style>
.navbar .nav-link.active {
    background: linear-gradient(90deg,#6a82fb,#fc5c7d)!important;
    color: #fff!important;
    border-radius: 8px!important;
}
.navbar .nav-link {
    color: #2d2d2d;
    font-weight: 500;
    transition: background .2s, color .2s;
}
.navbar .nav-link:hover {
    background: #f1f1f1;
    color: #6a82fb;
    border-radius: 8px;
}
</style>
