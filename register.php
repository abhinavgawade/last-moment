<?php
require_once 'config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $profile_image_path = null;

    // Handle profile image upload for all users
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
        }
    }

    if ($username && $email && $password && $confirm_password) {
        if ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            try {
                $pdo = db_connect();
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists';
                } else {
                    // Create new user with profile image
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, role, profile_image) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $username,
                        $email,
                        password_hash($password, PASSWORD_DEFAULT),
                        $role,
                        $profile_image_path
                    ]);
                    $success = 'Registration successful! You can now login.';
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again later.';
                error_log($e->getMessage());
            }
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f7f7f9 !important; font-family: 'Inter', Arial, sans-serif; }
        .register-gradient-card {
            background: linear-gradient(90deg, #6a82fb 0%, #a18cd1 100%);
            border-radius: 22px;
            box-shadow: 0 4px 32px 0 rgba(106,130,251,0.13);
            max-width: 480px;
            margin: 0 auto;
        }
        .register-gradient-card .card-body {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 12px 0 rgba(106,130,251,0.10);
            padding: 2.5rem 2.5rem 2rem 2.5rem;
        }
        .register-gradient-card .card-title {
            font-weight: 700;
            font-size: 2.1rem;
            color: #6a82fb;
        }
        .register-gradient-card .btn-primary {
            background: #6a82fb;
            border: none;
            font-weight: 600;
            font-size: 1.15rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px 0 rgba(106,130,251,0.09);
        }
        .register-gradient-card .btn-primary:hover {
            background: #a18cd1;
            color: #fff;
        }
        .register-gradient-card .form-label {
            font-weight: 500;
            color: #23272f;
        }
        .register-gradient-card .form-control {
            border-radius: 8px;
            font-size: 1.06rem;
        }
        .register-gradient-card .alert {
            border-radius: 8px;
        }
        .register-gradient-card .login-link {
            color: #6a82fb;
            font-weight: 500;
        }
        .register-gradient-card .login-link:hover {
            color: #a18cd1;
            text-decoration: underline;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-5 pt-5">
        <div class="row justify-content-center">
            <div class="col-12 d-flex justify-content-center">
                <div class="register-gradient-card p-1">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Create Account</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" action="register.php">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Register as</label>
                                <select class="form-select" name="role" id="role" required>
                                    <option value="user">User</option>
                                    <option value="reviewer">Reviewer</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Profile Image</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Register</button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">Already have an account? <a href="login.php" class="login-link">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Hide avatar logic if present
    if (document.getElementById('avatar-upload-section')) {
        document.getElementById('avatar-upload-section').style.display = 'none';
    }
    </script>
</body>
</html>
