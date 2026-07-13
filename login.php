<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT id, name, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_name'] = (string)$user['name'];
            $_SESSION['username']  = (string)$user['username'];
            $_SESSION['role']      = (string)$user['role'];
            redirect(APP_URL . '/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$fishFavicon = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐟</text></svg>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= $fishFavicon ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<div class="login-page">
    <div class="login-card">

        <!-- Gradient top -->
        <div class="login-card-top">
            <div class="login-logo-icon">
                <i class="fas fa-fish"></i>
            </div>
            <h1><?= e(APP_NAME) ?></h1>
            <p>Sign in to your account</p>
        </div>

        <!-- Form body -->
        <div class="login-card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius:10px;font-size:13px">
                    <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text" style="border-color:#e2e8f0;background:#f8fafc">
                            <i class="fas fa-user" style="color:#94a3b8;font-size:13px"></i>
                        </span>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus
                               style="border-left:none">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text" style="border-color:#e2e8f0;background:#f8fafc">
                            <i class="fas fa-lock" style="color:#94a3b8;font-size:13px"></i>
                        </span>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required
                               style="border-left:none">
                    </div>
                </div>
                <button type="submit" class="btn w-100 fw-600"
                        style="background:linear-gradient(135deg,#0e7490,#0891b2);color:#fff;padding:12px;border-radius:10px;font-size:14px">
                    <i class="fas fa-sign-in-alt me-2"></i> Login
                </button>
            </form>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
