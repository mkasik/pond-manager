<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn()) { jsonErr('Unauthorized'); }

$action = trim((string)($_GET['action'] ?? ''));
$pdo    = db();

function processSaleData(array $post, PDO $pdo): array {
    $saleScope    = trim((string)($post['sale_scope'] ?? 'pond'));
    $entryType    = trim((string)($post['entry_type'] ?? 'measured'));
    $pondIdRaw    = trim((string)($post['pond_id'] ?? ''));
    $pondId       = $pondIdRaw !== '' ? (int)$pondIdRaw : null;
    $saleDate     = trim((string)($post['sale_date'] ?? ''));
    $fishType     = trim((string)($post['fish_type'] ?? ''));
    $quantityKg   = (float)($post['quantity_kg'] ?? 0);
    $unitPrice    = (float)($post['unit_price'] ?? 0);
    $directAmount = (float)($post['direct_amount'] ?? 0);
    $saleExpense  = (float)($post['sale_expense'] ?? 0);
    $buyerName    = trim((string)($post['buyer_name'] ?? ''));
    $buyerPhone   = trim((string)($post['buyer_phone'] ?? ''));
    $notes        = trim((string)($post['notes'] ?? ''));

    if (!in_array($saleScope, ['pond','common'], true)) $saleScope = 'pond';
    if (!in_array($entryType, ['measured','direct'], true)) $entryType = 'measured';

    if ($saleDate === '')  throw new InvalidArgumentException('Sale date is required.');
    if ($fishType === '')  throw new InvalidArgumentException('Fish type is required.');
    if ($saleExpense < 0) throw new InvalidArgumentException('Sale expense cannot be negative.');

    if ($saleScope === 'pond' && ($pondId === null || $pondId <= 0)) {
        throw new InvalidArgumentException('Please select a pond for pond-wise sale.');
    }

    if ($saleScope === 'common') $pondId = null;

    if ($pondId !== null) {
        $c = $pdo->prepare("SELECT id FROM ponds WHERE id=? LIMIT 1");
        $c->execute([$pondId]);
        if (!$c->fetch()) throw new InvalidArgumentException('Selected pond not found.');
    }

    if ($entryType === 'measured') {
        if ($quantityKg <= 0) throw new InvalidArgumentException('Quantity must be greater than 0 for measured sale.');
        $grossAmount = $quantityKg * $unitPrice;
    } else {
        if ($directAmount <= 0) throw new InvalidArgumentException('Direct sale amount must be greater than 0.');
        $quantityKg  = 0;
        $unitPrice   = 0;
        $grossAmount = $directAmount;
    }

    $netAmount = $grossAmount - $saleExpense;

    return compact('pondId','saleScope','entryType','saleDate','fishType','quantityKg','unitPrice','grossAmount','saleExpense','netAmount','buyerName','buyerPhone','notes');
}

if ($action === 'add') {
    try {
        $d = processSaleData($_POST, $pdo);
        $pdo->prepare("
            INSERT INTO pond_sales (pond_id, sale_scope, entry_type, sale_date, fish_type, quantity_kg, unit_price, gross_amount, sale_expense, net_amount, buyer_name, buyer_phone, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$d['pondId'], $d['saleScope'], $d['entryType'], $d['saleDate'], $d['fishType'], $d['quantityKg'], $d['unitPrice'], $d['grossAmount'], $d['saleExpense'], $d['netAmount'], $d['buyerName'] ?: null, $d['buyerPhone'] ?: null, $d['notes'] ?: null]);

        jsonOk([], 'Sale added successfully.');
    } catch (InvalidArgumentException $e) {
        jsonErr($e->getMessage());
    }
}

if ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonErr('Invalid sale ID.');

    try {
        $d = processSaleData($_POST, $pdo);
        $pdo->prepare("
            UPDATE pond_sales SET pond_id=?, sale_scope=?, entry_type=?, sale_date=?, fish_type=?, quantity_kg=?, unit_price=?, gross_amount=?, sale_expense=?, net_amount=?, buyer_name=?, buyer_phone=?, notes=?
            WHERE id=?
        ")->execute([$d['pondId'], $d['saleScope'], $d['entryType'], $d['saleDate'], $d['fishType'], $d['quantityKg'], $d['unitPrice'], $d['grossAmount'], $d['saleExpense'], $d['netAmount'], $d['buyerName'] ?: null, $d['buyerPhone'] ?: null, $d['notes'] ?: null, $id]);

        jsonOk([], 'Sale updated successfully.');
    } catch (InvalidArgumentException $e) {
        jsonErr($e->getMessage());
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonErr('Invalid sale ID.');
    $pdo->prepare("DELETE FROM pond_sales WHERE id=?")->execute([$id]);
    jsonOk([], 'Sale deleted successfully.');
}

jsonErr('Invalid action.');
