<?php
require_once 'config/config.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any existing session data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    session_unset();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        try {
            $pdo = db_connect();
            
            // Get user details including role and profile image
            $stmt = $pdo->prepare("SELECT id, username, email, password, role, profile_image FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_image'] = $user['profile_image'] ?: 'assets/img/avatar-default.png';

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin.php');
                        break;
                    case 'reviewer':
                        header('Location: reviews.php');
                        break;
                    default:
                        header('Location: index.php');
                }
                exit();
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
            error_log($e->getMessage());
        }
    } else {
        $error = 'Please enter both email and password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - The Publish Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
    <style>
        body { background: #f7f7f9 !important; font-family: 'Inter', Arial, sans-serif; }
        .login-gradient-card {
            background: linear-gradient(90deg, #6a82fb 0%, #fc5c7d 100%);
            border-radius: 22px;
            box-shadow: 0 4px 32px 0 rgba(106,130,251,0.13);
            max-width: 440px;
            margin: 0 auto;
        }
        .login-gradient-card .card-body {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 12px 0 rgba(106,130,251,0.10);
            padding: 2.5rem 2.5rem 2rem 2.5rem;
        }
        .login-gradient-card .card-title {
            font-weight: 700;
            font-size: 2.1rem;
            color: #6a82fb;
        }
        .login-gradient-card .btn-primary {
            background: #23272f;
            border: none;
            font-weight: 600;
            font-size: 1.15rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px 0 rgba(106,130,251,0.09);
        }
        .login-gradient-card .btn-primary:hover {
            background: #fc5c7d;
            color: #fff;
        }
        .login-gradient-card .form-label {
            font-weight: 500;
            color: #23272f;
        }
        .login-gradient-card .form-control {
            border-radius: 8px;
            font-size: 1.06rem;
        }
        .login-gradient-card .alert {
            border-radius: 8px;
        }
        .login-gradient-card .register-link {
            color: #6a82fb;
            font-weight: 500;
        }
        .login-gradient-card .register-link:hover {
            color: #fc5c7d;
            text-decoration: underline;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-5 pt-5">
        <div class="row justify-content-center">
            <div class="col-12 d-flex justify-content-center">
                <div class="login-gradient-card p-1">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Login</h2>
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="login.php">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Login</button>
                            </div>
                        </form>
                        <div class="text-center mt-4">
                            <p class="mb-0">Don't have an account? <a href="register.php" class="register-link">Register here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
