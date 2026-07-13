<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn()) { jsonErr('Unauthorized'); }

$action = trim((string)($_GET['action'] ?? ''));
$pdo    = db();

if ($action === 'add') {
    $name      = trim((string)($_POST['name'] ?? ''));
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $address   = trim((string)($_POST['address'] ?? ''));
    $type      = trim((string)($_POST['type'] ?? 'worker'));
    $shareUnit = (float)($_POST['share_unit'] ?? 0);
    $status    = trim((string)($_POST['status'] ?? 'active'));
    $notes     = trim((string)($_POST['notes'] ?? ''));

    if ($name === '') jsonErr('Name is required.');
    if (!in_array($type, ['owner','worker'], true)) $type = 'worker';
    if (!in_array($status, ['active','inactive'], true)) $status = 'active';
    if ($shareUnit < 0) jsonErr('Share unit cannot be negative.');

    $pdo->prepare("
        INSERT INTO partners (name, phone, address, type, share_unit, notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([$name, $phone ?: null, $address ?: null, $type, $shareUnit, $notes ?: null, $status]);

    jsonOk([], 'Partner added successfully.');
}

if ($action === 'edit') {
    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim((string)($_POST['name'] ?? ''));
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $address   = trim((string)($_POST['address'] ?? ''));
    $type      = trim((string)($_POST['type'] ?? 'worker'));
    $shareUnit = (float)($_POST['share_unit'] ?? 0);
    $status    = trim((string)($_POST['status'] ?? 'active'));
    $notes     = trim((string)($_POST['notes'] ?? ''));

    if ($id <= 0)    jsonErr('Invalid partner ID.');
    if ($name === '') jsonErr('Name is required.');
    if (!in_array($type, ['owner','worker'], true)) $type = 'worker';
    if (!in_array($status, ['active','inactive'], true)) $status = 'active';

    $pdo->prepare("
        UPDATE partners SET name=?, phone=?, address=?, type=?, share_unit=?, notes=?, status=? WHERE id=?
    ")->execute([$name, $phone ?: null, $address ?: null, $type, $shareUnit, $notes ?: null, $status, $id]);

    jsonOk([], 'Partner updated successfully.');
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonErr('Invalid partner ID.');
    $pdo->prepare("DELETE FROM partner_advances WHERE partner_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM partners WHERE id=?")->execute([$id]);
    jsonOk([], 'Partner deleted successfully.');
}

jsonErr('Invalid action.');
