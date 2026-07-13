<?php
declare(strict_types=1);
$_page  = basename($_SERVER['PHP_SELF'], '.php');
$isLogin = ($pageTitle ?? '') === 'Login';
$_user  = isLoggedIn() ? currentUser() : ['name' => '', 'username' => '', 'role' => ''];

$_avatarLetter = strtoupper(substr($_user['name'] ?: $_user['username'] ?: 'U', 0, 1));

$navSections = [
    ['section' => 'Main'],
    ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard.php', 'icon' => 'fa-tachometer-alt',    'page' => 'dashboard'],
    ['label' => 'Ponds',     'url' => APP_URL . '/ponds.php',     'icon' => 'fa-water',              'page' => 'ponds'],
    ['label' => 'Expenses',  'url' => APP_URL . '/expenses.php',  'icon' => 'fa-wallet',             'page' => 'expenses'],
    ['label' => 'Sales',     'url' => APP_URL . '/sales.php',     'icon' => 'fa-chart-bar',          'page' => 'sales'],
    ['section' => 'Business'],
    ['label' => 'Partners',  'url' => APP_URL . '/partners.php',  'icon' => 'fa-users',              'page' => 'partners'],
    ['label' => 'Advances',  'url' => APP_URL . '/advances.php',  'icon' => 'fa-hand-holding-usd',   'page' => 'advances'],
    ['section' => 'Reports'],
    ['label' => 'Profit',    'url' => APP_URL . '/profit.php',    'icon' => 'fa-chart-line',         'page' => 'profit'],
    ['label' => 'Reports',   'url' => APP_URL . '/reports.php',   'icon' => 'fa-file-alt',           'page' => 'reports'],
];

$fishFavicon = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐟</text></svg>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= $fishFavicon ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<?php if ($isLogin): ?>
<?php /* login has its own layout — no sidebar */ return; endif; ?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>
<div class="wrapper">

<!-- ══ SIDEBAR ══════════════════════════════════════════════════ -->
<nav class="sidebar" id="sidebar">
    <a href="<?= APP_URL ?>/dashboard.php" class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-fish"></i></div>
        <div>
            <div class="brand-name"><?= e(APP_NAME) ?></div>
            <div class="brand-sub">mkasik.com</div>
        </div>
    </a>

    <ul class="sidebar-nav">
        <?php foreach ($navSections as $item): ?>
            <?php if (isset($item['section'])): ?>
                <li class="nav-section-label"><?= e($item['section']) ?></li>
            <?php else: ?>
                <?php $active = $_page === $item['page']; ?>
                <li class="<?= $active ? 'active' : '' ?>">
                    <a href="<?= e($item['url']) ?>">
                        <span class="nav-icon"><i class="fas <?= e($item['icon']) ?>"></i></span>
                        <?= e($item['label']) ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar-footer">
        <div class="user-avatar"><?= $_avatarLetter ?></div>
        <div class="user-meta">
            <div class="u-name"><?= e($_user['name'] ?: $_user['username']) ?></div>
            <div class="u-role"><?= ucfirst(e($_user['role'])) ?></div>
        </div>
        <a href="<?= APP_URL ?>/logout.php" class="btn-logout" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</nav>

<!-- ══ MAIN CONTENT ════════════════════════════════════════════ -->
<div class="main-content">
    <div class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <span class="page-heading"><?= e($pageTitle ?? '') ?></span>
        <div class="topbar-meta d-none d-md-block"><?= date('d M Y, D') ?></div>
    </div>
    <div class="content-area">

<div id="toastArea" class="toast-area"></div>
