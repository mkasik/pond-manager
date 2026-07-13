<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn()) { jsonErr('Unauthorized'); }

$action = trim((string)($_GET['action'] ?? ''));
$pdo    = db();

if ($action === 'add') {
    $partnerIdRaw     = trim((string)($_POST['partner_id'] ?? ''));
    $pondIdRaw        = trim((string)($_POST['pond_id'] ?? ''));
    $advanceDate      = trim((string)($_POST['advance_date'] ?? ''));
    $purpose          = trim((string)($_POST['purpose'] ?? ''));
    $amount           = (float)($_POST['amount'] ?? 0);
    $deductFromProfit = (int)($_POST['deduct_from_profit'] ?? 1);
    $notes            = trim((string)($_POST['notes'] ?? ''));

    $partnerId = (int)$partnerIdRaw;
    $pondId    = $pondIdRaw !== '' ? (int)$pondIdRaw : null;

    if ($partnerId <= 0)   jsonErr('Please select a partner.');
    if ($advanceDate === '') jsonErr('Advance date is required.');
    if ($purpose === '')    jsonErr('Purpose is required.');
    if ($amount <= 0)       jsonErr('Amount must be greater than 0.');
    if (!in_array($deductFromProfit, [0, 1], true)) $deductFromProfit = 1;

    $pc = $pdo->prepare("SELECT id FROM partners WHERE id=? LIMIT 1");
    $pc->execute([$partnerId]);
    if (!$pc->fetch()) jsonErr('Selected partner not found.');

    if ($pondId !== null) {
        $pc2 = $pdo->prepare("SELECT id FROM ponds WHERE id=? LIMIT 1");
        $pc2->execute([$pondId]);
        if (!$pc2->fetch()) jsonErr('Selected pond not found.');
    }

    $pdo->prepare("
        INSERT INTO partner_advances (partner_id, pond_id, advance_date, purpose, amount, deduct_from_profit, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([$partnerId, $pondId, $advanceDate, $purpose, $amount, $deductFromProfit, $notes ?: null]);

    jsonOk([], 'Advance added successfully.');
}

if ($action === 'edit') {
    $id               = (int)($_POST['id'] ?? 0);
    $partnerIdRaw     = trim((string)($_POST['partner_id'] ?? ''));
    $pondIdRaw        = trim((string)($_POST['pond_id'] ?? ''));
    $advanceDate      = trim((string)($_POST['advance_date'] ?? ''));
    $purpose          = trim((string)($_POST['purpose'] ?? ''));
    $amount           = (float)($_POST['amount'] ?? 0);
    $deductFromProfit = (int)($_POST['deduct_from_profit'] ?? 1);
    $notes            = trim((string)($_POST['notes'] ?? ''));

    $partnerId = (int)$partnerIdRaw;
    $pondId    = $pondIdRaw !== '' ? (int)$pondIdRaw : null;

    if ($id <= 0)          jsonErr('Invalid advance ID.');
    if ($partnerId <= 0)   jsonErr('Please select a partner.');
    if ($advanceDate === '') jsonErr('Advance date is required.');
    if ($purpose === '')    jsonErr('Purpose is required.');
    if ($amount <= 0)       jsonErr('Amount must be greater than 0.');

    $pdo->prepare("
        UPDATE partner_advances SET partner_id=?, pond_id=?, advance_date=?, purpose=?, amount=?, deduct_from_profit=?, notes=?
        WHERE id=?
    ")->execute([$partnerId, $pondId, $advanceDate, $purpose, $amount, $deductFromProfit, $notes ?: null, $id]);

    jsonOk([], 'Advance updated successfully.');
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonErr('Invalid advance ID.');
    $pdo->prepare("DELETE FROM partner_advances WHERE id=?")->execute([$id]);
    jsonOk([], 'Advance deleted successfully.');
}

jsonErr('Invalid action.');
