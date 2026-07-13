<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn()) { jsonErr('Unauthorized'); }

$action = trim((string)($_GET['action'] ?? ''));
$pdo    = db();

if ($action === 'add') {
    $pondName    = trim((string)($_POST['pond_name'] ?? ''));
    $ownerName   = trim((string)($_POST['owner_name'] ?? ''));
    $leaseAmount = (float)($_POST['lease_amount'] ?? 0);
    $notes       = trim((string)($_POST['notes'] ?? ''));

    if ($pondName === '')  jsonErr('Pond name is required.');
    if ($ownerName === '') jsonErr('Owner name is required.');
    if ($leaseAmount < 0) jsonErr('Lease amount cannot be negative.');

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO ponds (pond_name, land_owner_name, lease_amount, notes, status, created_at)
            VALUES (?, ?, ?, ?, 'active', NOW())
        ")->execute([$pondName, $ownerName, $leaseAmount, $notes ?: null]);

        $pondId = (int)$pdo->lastInsertId();

        if ($leaseAmount > 0) {
            $pdo->prepare("
                INSERT INTO pond_expenses (pond_id, expense_date, category, description, amount, paid_by, notes, created_at)
                VALUES (?, CURDATE(), 'Lease', 'Auto lease expense from pond entry', ?, 'Admin', 'Auto generated', NOW())
            ")->execute([$pondId, $leaseAmount]);
        }

        $pdo->commit();
        jsonOk([], 'Pond added successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonErr('Failed: ' . $e->getMessage());
    }
}

if ($action === 'edit') {
    $id          = (int)($_POST['id'] ?? 0);
    $pondName    = trim((string)($_POST['pond_name'] ?? ''));
    $ownerName   = trim((string)($_POST['owner_name'] ?? ''));
    $leaseAmount = (float)($_POST['lease_amount'] ?? 0);
    $notes       = trim((string)($_POST['notes'] ?? ''));

    if ($id <= 0)          jsonErr('Invalid pond ID.');
    if ($pondName === '')  jsonErr('Pond name is required.');
    if ($ownerName === '') jsonErr('Owner name is required.');

    $pdo->prepare("
        UPDATE ponds SET pond_name=?, land_owner_name=?, lease_amount=?, notes=? WHERE id=?
    ")->execute([$pondName, $ownerName, $leaseAmount, $notes ?: null, $id]);

    jsonOk([], 'Pond updated successfully.');
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonErr('Invalid pond ID.');

    $pdo->prepare("UPDATE pond_expenses SET pond_id=NULL WHERE pond_id=?")->execute([$id]);
    $pdo->prepare("UPDATE pond_sales SET pond_id=NULL WHERE pond_id=?")->execute([$id]);
    $pdo->prepare("UPDATE partner_advances SET pond_id=NULL WHERE pond_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM ponds WHERE id=?")->execute([$id]);

    jsonOk([], 'Pond deleted successfully.');
}

jsonErr('Invalid action.');
