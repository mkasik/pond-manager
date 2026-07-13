<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
logoutUser();
header('Location: ' . APP_URL . '/login.php');
exit;
