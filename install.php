<?php
declare(strict_types=1);

define('APP_NAME', 'Pond Manager');
define('APP_URL', '/tools/pond-manager');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'pondmanager');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');

$done      = [];
$error     = '';
$formName  = trim((string)($_POST['admin_name'] ?? ''));
$formUser  = trim((string)($_POST['admin_username'] ?? ''));
$formPass  = (string)($_POST['admin_password'] ?? '');
$formPass2 = (string)($_POST['admin_password2'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Validate admin account fields before touching the DB */
    if ($formName === '')  $error = 'Admin name is required.';
    elseif ($formUser === '') $error = 'Admin username is required.';
    elseif (strlen($formPass) < 6) $error = 'Password must be at least 6 characters.';
    elseif ($formPass !== $formPass2) $error = 'Passwords do not match.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("SET NAMES utf8mb4");

        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin','viewer') DEFAULT 'admin',
                created_at DATETIME DEFAULT NOW()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS ponds (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pond_name VARCHAR(100) NOT NULL,
                land_owner_name VARCHAR(100),
                lease_amount DECIMAL(12,2) DEFAULT 0,
                notes TEXT,
                status ENUM('active','inactive') DEFAULT 'active',
                created_at DATETIME DEFAULT NOW()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS pond_expenses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pond_id INT NULL,
                expense_date DATE NOT NULL,
                category VARCHAR(50) NOT NULL,
                description VARCHAR(255),
                amount DECIMAL(12,2) NOT NULL,
                paid_by VARCHAR(100),
                notes TEXT,
                created_at DATETIME DEFAULT NOW(),
                FOREIGN KEY (pond_id) REFERENCES ponds(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS pond_sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pond_id INT NULL,
                sale_scope ENUM('pond','common') DEFAULT 'pond',
                entry_type ENUM('measured','direct') DEFAULT 'measured',
                sale_date DATE NOT NULL,
                fish_type VARCHAR(100) NOT NULL,
                quantity_kg DECIMAL(10,2) DEFAULT 0,
                unit_price DECIMAL(10,2) DEFAULT 0,
                gross_amount DECIMAL(12,2) NOT NULL,
                sale_expense DECIMAL(12,2) DEFAULT 0,
                net_amount DECIMAL(12,2) NOT NULL,
                buyer_name VARCHAR(100),
                buyer_phone VARCHAR(20),
                notes TEXT,
                created_at DATETIME DEFAULT NOW(),
                FOREIGN KEY (pond_id) REFERENCES ponds(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS partners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                address VARCHAR(255),
                type ENUM('owner','worker') DEFAULT 'worker',
                share_unit DECIMAL(8,2) DEFAULT 0,
                notes TEXT,
                status ENUM('active','inactive') DEFAULT 'active',
                created_at DATETIME DEFAULT NOW()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS partner_advances (
                id INT AUTO_INCREMENT PRIMARY KEY,
                partner_id INT NOT NULL,
                pond_id INT NULL,
                advance_date DATE NOT NULL,
                purpose VARCHAR(255) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                deduct_from_profit TINYINT(1) DEFAULT 1,
                notes TEXT,
                created_at DATETIME DEFAULT NOW(),
                FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
                FOREIGN KEY (pond_id) REFERENCES ponds(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $q) {
            $pdo->exec($q);
            preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $q, $m);
            if (!empty($m[1])) $done[] = "Table `{$m[1]}` created.";
        }

        $adminCheck = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ((int)$adminCheck === 0) {
            $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, 'admin')")
                ->execute([$formName, $formUser, password_hash($formPass, PASSWORD_DEFAULT)]);
            $done[] = "Admin user created (username: {$formUser}).";
        } else {
            $done[] = "Admin user already exists — skipped.";
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f7fb;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:Inter,Arial,sans-serif;}
.card{max-width:520px;width:100%;border-radius:18px;box-shadow:0 8px 30px rgba(0,0,0,0.08);}
</style>
</head>
<body>
<div class="card p-4">
    <h4 class="mb-1"><?= APP_NAME ?> — Installer</h4>
    <p class="text-muted mb-4">Creates database tables and sets up your admin account.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($done)): ?>
        <div class="alert alert-success">
            <?php foreach ($done as $line): ?>
                <div>✓ <?= e($line) ?></div>
            <?php endforeach; ?>
        </div>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-primary w-100">Go to Login →</a>
    <?php else: ?>
        <form method="post">
            <p class="mb-3">Database: <strong><?= DB_NAME ?></strong> on <strong><?= DB_HOST ?></strong></p>

            <h6 class="mb-3 fw-semibold">Admin Account</h6>

            <div class="mb-3">
                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($formName, ENT_QUOTES) ?>" placeholder="e.g. Admin" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                <input type="text" name="admin_username" class="form-control" value="<?= htmlspecialchars($formUser, ENT_QUOTES) ?>" placeholder="e.g. admin" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                <input type="password" name="admin_password" class="form-control" placeholder="Min. 6 characters" required minlength="6">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="admin_password2" class="form-control" placeholder="Repeat password" required minlength="6">
            </div>

            <button type="submit" class="btn btn-primary w-100">Run Install</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
