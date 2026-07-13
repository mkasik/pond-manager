<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$pageTitle = 'Reports';
$pdo = db();

/* Date range filter */
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to'] ?? ''));
$pondId   = isset($_GET['pond_id']) && $_GET['pond_id'] !== '' ? (int)$_GET['pond_id'] : null;

$ponds = $pdo->query("SELECT id, pond_name FROM ponds ORDER BY pond_name ASC")->fetchAll();

/* Expense report */
$expSql  = "SELECT pe.expense_date, pe.category, pe.description, pe.amount, pe.paid_by, p.pond_name FROM pond_expenses pe LEFT JOIN ponds p ON p.id = pe.pond_id WHERE 1=1";
$expParams = [];
if ($dateFrom !== '') { $expSql .= " AND pe.expense_date >= :df"; $expParams['df'] = $dateFrom; }
if ($dateTo !== '')   { $expSql .= " AND pe.expense_date <= :dt"; $expParams['dt'] = $dateTo; }
if ($pondId !== null) { $expSql .= " AND pe.pond_id = :pid"; $expParams['pid'] = $pondId; }
$expSql .= " ORDER BY pe.expense_date DESC";
$expStmt = $pdo->prepare($expSql);
$expStmt->execute($expParams);
$reportExpenses = $expStmt->fetchAll();
$totalExpAmt = array_sum(array_column($reportExpenses, 'amount'));

/* Sales report */
$salSql  = "SELECT ps.sale_date, ps.fish_type, ps.entry_type, ps.gross_amount, ps.sale_expense, ps.net_amount, ps.buyer_name, p.pond_name FROM pond_sales ps LEFT JOIN ponds p ON p.id = ps.pond_id WHERE 1=1";
$salParams = [];
if ($dateFrom !== '') { $salSql .= " AND ps.sale_date >= :df"; $salParams['df'] = $dateFrom; }
if ($dateTo !== '')   { $salSql .= " AND ps.sale_date <= :dt"; $salParams['dt'] = $dateTo; }
if ($pondId !== null) { $salSql .= " AND ps.pond_id = :pid"; $salParams['pid'] = $pondId; }
$salSql .= " ORDER BY ps.sale_date DESC";
$salStmt = $pdo->prepare($salSql);
$salStmt->execute($salParams);
$reportSales = $salStmt->fetchAll();
$totalSalNet = array_sum(array_column($reportSales, 'net_amount'));

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <div class="section-title">Reports</div>
        <p class="section-text">Filter and view expense and sales reports by date range and pond.</p>
    </div>
</div>

<!-- Filter -->
<div class="card-soft mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold">From Date</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">To Date</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Pond</label>
            <select name="pond_id" class="form-select">
                <option value="">All Ponds</option>
                <?php foreach ($ponds as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $pondId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['pond_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Generate</button>
            <a href="<?= APP_URL ?>/reports.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Expense Report -->
<div class="card-soft mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Expense Report</h5>
        <span class="text-muted small">Total: <?= count($reportExpenses) ?> entries</span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th>Pond</th><th>Category</th><th>Description</th><th>Amount</th><th>Paid By</th></tr>
            </thead>
            <tbody>
                <?php if ($reportExpenses): ?>
                    <?php foreach ($reportExpenses as $r): ?>
                    <tr>
                        <td><?= e($r['expense_date']) ?></td>
                        <td><?= e($r['pond_name'] ?? 'Common') ?></td>
                        <td><?= e($r['category']) ?></td>
                        <td><?= e($r['description'] ?? '') ?></td>
                        <td><?= formatCurrency((float)$r['amount']) ?></td>
                        <td><?= e($r['paid_by'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No expense data for the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($reportExpenses): ?>
            <tfoot class="table-light">
                <tr><th colspan="4" class="text-end fw-semibold">Total Expense</th><th class="fw-semibold"><?= formatCurrency($totalExpAmt) ?></th><th></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Sales Report -->
<div class="card-soft">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Sales Report</h5>
        <span class="text-muted small">Total: <?= count($reportSales) ?> entries</span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th>Pond</th><th>Fish Type</th><th>Type</th><th>Gross</th><th>Sale Exp</th><th>Net</th><th>Buyer</th></tr>
            </thead>
            <tbody>
                <?php if ($reportSales): ?>
                    <?php foreach ($reportSales as $r): ?>
                    <tr>
                        <td><?= e($r['sale_date']) ?></td>
                        <td><?= e($r['pond_name'] ?? 'Common') ?></td>
                        <td><?= e($r['fish_type']) ?></td>
                        <td><?= $r['entry_type'] === 'direct' ? 'Direct' : 'Measured' ?></td>
                        <td><?= formatCurrency((float)$r['gross_amount']) ?></td>
                        <td><?= formatCurrency((float)$r['sale_expense']) ?></td>
                        <td><?= formatCurrency((float)$r['net_amount']) ?></td>
                        <td><?= e($r['buyer_name'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No sales data for the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($reportSales): ?>
            <tfoot class="table-light">
                <tr><th colspan="6" class="text-end fw-semibold">Total Net Sale</th><th class="fw-semibold"><?= formatCurrency($totalSalNet) ?></th><th></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
