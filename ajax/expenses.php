<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn()) { jsonErr('Unauthorized'); }

$action = trim((string)($_GET['action'] ?? ''));
$pdo    = db();

$validCategories = ['Feed','Medicine','Labor','Transport','Equipment','Electricity','Lease','Pond Preparation','Miscellaneous'];

if ($action === 'add') {
    $pondIdRaw   = trim((string)($_POST['pond_id'] ?? ''));
    $pondId      = $pondIdRaw !== '' ? (int)$pondIdRaw : null;
    $expenseDate = trim((string)($_POST['expense_date'] ?? ''));
    $category    = trim((string)($_POST['category'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $amount      = (float)($_POST['amount'] ?? 0);
    $paidBy      = trim((string)($_POST['paid_by'] ?? ''));
    $notes       = trim((string)($_POST['notes'] ?? ''));

    if ($expenseDate === '')                    jsonErr('Expense date is required.');
    if ($category === '')                       jsonErr('Category is required.');
    if (!in_array($category, $validCategories, true)) jsonErr('Invalid category.');
    if ($amount <= 0)                           jsonErr('Amount must be greater than 0.');

    if ($pondId !== null) {
        $check = $pdo->prepare("SELECT id FROM ponds WHERE id=? LIMIT 1");
        $check->execute([$pondId]);
        if (!$check->fetch()) jsonErr('Selected pond not found.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO pond_expenses (pond_id, expense_date, category, description, amount, paid_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$pondId, $expenseDate, $category, $description ?: null, $amount, $paidBy ?: null, $notes ?: null]);

    jsonOk([], 'Expense added successfully.');
}

if ($action === 'edit') {
    $id          = (int)($_POST['id'] ?? 0);
    $pondIdRaw   = trim((string)($_POST['pond_id'] ?? ''));
    $pondId      = $pondIdRaw !== '' ? (int)$pondIdRaw : null;
    $expenseDate = trim((string)($_POST['expense_date'] ?? ''));
    $category    = trim((string)($_POST['category'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $amount      = (float)($_POST['amount'] ?? 0);
    $paidBy      = trim((string)($_POST['paid_by'] ?? ''));
    $notes       = trim((string)($_POST['notes'] ?? ''));

    if ($id <= 0)          jsonErr('Invalid expense ID.');
    if ($expenseDate === '') jsonErr('Expense date is required.');
    if ($category === '')   jsonErr('Category is required.');
    if ($amount <= 0)       jsonErr('Amount must be greater than 0.');

    $stmt = $pdo->prepare("
        UPDATE pond_expenses SET pond_id=?, expense_date=?, category=?, description=?, amount=?, paid_by=?, notes=?
        WHERE id=?
    ");
    $stmt->execute([$pondId, $expenseDate, $category, $description ?: null, $amount, $paidBy ?: null, $notes ?: null, $id]);

    jsonOk([], 'Expense updated successfully.');
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonErr('Invalid expense ID.');
    $pdo->prepare("DELETE FROM pond_expenses WHERE id=?")->execute([$id]);
    jsonOk([], 'Expense deleted successfully.');
}

jsonErr('Invalid action.');
