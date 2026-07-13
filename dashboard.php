<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$pageTitle = 'Dashboard';
$pdo = db();

/* Expense summary */
$expSummary = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN pond_id IS NOT NULL THEN amount ELSE 0 END),0) AS pond_expense,
        COALESCE(SUM(CASE WHEN pond_id IS NULL     THEN amount ELSE 0 END),0) AS common_expense,
        COALESCE(SUM(amount),0) AS total_expense
    FROM pond_expenses
")->fetch();

$totalPondExpense   = (float)$expSummary['pond_expense'];
$totalCommonExpense = (float)$expSummary['common_expense'];
$totalExpense       = (float)$expSummary['total_expense'];

/* Sales summary */
$salSummary = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN pond_id IS NOT NULL THEN net_amount ELSE 0 END),0) AS pond_sales,
        COALESCE(SUM(CASE WHEN pond_id IS NULL     THEN net_amount ELSE 0 END),0) AS common_sales,
        COALESCE(SUM(net_amount),0) AS total_sales
    FROM pond_sales
")->fetch();

$totalPondSales   = (float)$salSummary['pond_sales'];
$totalCommonSales = (float)$salSummary['common_sales'];
$totalSales       = (float)$salSummary['total_sales'];

$netProfit = $totalSales - $totalExpense;
$isProfit  = $netProfit >= 0;

/* Advance summary */
$advSummary = $pdo->query("
    SELECT
        COALESCE(SUM(amount),0) AS total_advance,
        COALESCE(SUM(CASE WHEN deduct_from_profit=1 THEN amount ELSE 0 END),0) AS deductible
    FROM partner_advances
")->fetch();

$totalAdvance     = (float)$advSummary['total_advance'];
$totalDeductible  = (float)$advSummary['deductible'];

/* Pond-wise summary */
$pondRows = $pdo->query("
    SELECT p.id, p.pond_name, p.land_owner_name, p.lease_amount,
        COALESCE(e.total_exp,0) AS total_expense,
        COALESCE(s.total_sal,0) AS total_sales
    FROM ponds p
    LEFT JOIN (SELECT pond_id, SUM(amount) AS total_exp FROM pond_expenses WHERE pond_id IS NOT NULL GROUP BY pond_id) e ON e.pond_id = p.id
    LEFT JOIN (SELECT pond_id, SUM(net_amount) AS total_sal FROM pond_sales WHERE pond_id IS NOT NULL GROUP BY pond_id) s ON s.pond_id = p.id
    ORDER BY p.id ASC
")->fetchAll();

foreach ($pondRows as &$r) {
    $r['profit_preview'] = (float)$r['total_sales'] - (float)$r['total_expense'];
}
unset($r);

/* Recent expenses */
$recentExpenses = $pdo->query("
    SELECT pe.expense_date, pe.category, pe.amount, pe.paid_by, p.pond_name
    FROM pond_expenses pe
    LEFT JOIN ponds p ON p.id = pe.pond_id
    ORDER BY pe.id DESC LIMIT 6
")->fetchAll();

/* Recent sales */
$recentSales = $pdo->query("
    SELECT ps.sale_date, ps.fish_type, ps.net_amount, ps.entry_type, p.pond_name
    FROM pond_sales ps
    LEFT JOIN ponds p ON p.id = ps.pond_id
    ORDER BY ps.id DESC LIMIT 6
")->fetchAll();

/* Partner advance summary */
$partnerAdv = $pdo->query("
    SELECT p.id, p.name, p.type, COALESCE(SUM(pa.amount),0) AS total_advance
    FROM partners p
    LEFT JOIN partner_advances pa ON pa.partner_id = p.id
    WHERE p.status = 'active'
    GROUP BY p.id ORDER BY total_advance DESC, p.id ASC LIMIT 8
")->fetchAll();

include __DIR__ . '/includes/layout_header.php';
?>

<!-- Top Metrics -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card sc-amber">
            <div class="sc-label">Total Expenses</div>
            <div class="sc-value"><?= formatCurrency($totalExpense) ?></div>
            <div class="sc-sub">Pond + Common</div>
            <i class="fas fa-wallet sc-icon"></i>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card sc-teal">
            <div class="sc-label">Total Net Sales</div>
            <div class="sc-value"><?= formatCurrency($totalSales) ?></div>
            <div class="sc-sub">All sales combined</div>
            <i class="fas fa-chart-bar sc-icon"></i>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card <?= $isProfit ? 'sc-green' : 'sc-red' ?>">
            <div class="sc-label">Net Profit / Loss</div>
            <div class="sc-value"><?= formatCurrency($netProfit) ?></div>
            <div class="sc-sub"><?= $isProfit ? 'Profit' : 'Loss' ?></div>
            <i class="fas fa-chart-line sc-icon"></i>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card sc-purple">
            <div class="sc-label">Total Advances</div>
            <div class="sc-value"><?= formatCurrency($totalAdvance) ?></div>
            <div class="sc-sub"><?= formatCurrency($totalDeductible) ?> deductible</div>
            <i class="fas fa-hand-holding-usd sc-icon"></i>
        </div>
    </div>
</div>

<!-- Breakdown -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-wallet me-2 text-warning"></i>Expense Breakdown</h5>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <tr><td style="width:240px;padding:12px 20px;color:#64748b">Pond-wise Expenses</td><td class="fw-600 amount-neutral"><?= formatCurrency($totalPondExpense) ?></td></tr>
                    <tr><td style="padding:12px 20px;color:#64748b">Common Expenses</td><td class="fw-600 amount-neutral"><?= formatCurrency($totalCommonExpense) ?></td></tr>
                    <tr><td style="padding:12px 20px;color:#64748b">Deductible Advances</td><td class="fw-600 amount-neutral"><?= formatCurrency($totalDeductible) ?></td></tr>
                    <tr style="background:#f8fafc"><td style="padding:12px 20px;font-weight:700">Total Expenses</td><td class="fw-700 amount-neg"><?= formatCurrency($totalExpense) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2 text-info"></i>Sales Breakdown</h5>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <tr><td style="width:240px;padding:12px 20px;color:#64748b">Pond-wise Net Sales</td><td class="fw-600 amount-neutral"><?= formatCurrency($totalPondSales) ?></td></tr>
                    <tr><td style="padding:12px 20px;color:#64748b">Common Net Sales</td><td class="fw-600 amount-neutral"><?= formatCurrency($totalCommonSales) ?></td></tr>
                    <tr style="background:#f8fafc"><td style="padding:12px 20px;font-weight:700">Total Net Sales</td><td class="fw-700 amount-pos"><?= formatCurrency($totalSales) ?></td></tr>
                    <tr style="background:#f8fafc"><td style="padding:12px 20px;font-weight:700">Net Profit / Loss</td><td class="fw-700 <?= $isProfit ? 'amount-pos' : 'amount-neg' ?>"><?= formatCurrency($netProfit) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Pond Performance -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="fas fa-water me-2" style="color:#0891b2"></i>Pond-wise Performance</h5>
        <a href="<?= APP_URL ?>/ponds.php" class="btn btn-sm btn-outline-secondary">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:50px">SL</th>
                    <th>Pond Name</th>
                    <th>Owner</th>
                    <th>Lease</th>
                    <th>Expense</th>
                    <th>Net Sale</th>
                    <th>Profit Preview</th>
                    <th style="width:80px">View</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pondRows): ?>
                    <?php foreach ($pondRows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-600"><?= e($r['pond_name']) ?></td>
                        <td><?= e($r['land_owner_name'] ?? '') ?></td>
                        <td class="amount-neutral"><?= formatCurrency((float)$r['lease_amount']) ?></td>
                        <td class="amount-neg"><?= formatCurrency((float)$r['total_expense']) ?></td>
                        <td class="amount-pos"><?= formatCurrency((float)$r['total_sales']) ?></td>
                        <td class="<?= $r['profit_preview'] < 0 ? 'amount-neg' : 'amount-pos' ?>">
                            <?= formatCurrency($r['profit_preview']) ?>
                        </td>
                        <td><a href="<?= APP_URL ?>/ponds.php?id=<?= (int)$r['id'] ?>" class="btn-act btn-view"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No pond data yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Activity -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-wallet me-2 text-warning"></i>Recent Expenses</h5>
                <a href="<?= APP_URL ?>/expenses.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Date</th><th>Pond</th><th>Category</th><th>Amount</th></tr></thead>
                    <tbody>
                        <?php if ($recentExpenses): ?>
                            <?php foreach ($recentExpenses as $r): ?>
                            <tr>
                                <td class="fs-13"><?= e($r['expense_date']) ?></td>
                                <td class="fs-13"><?= e($r['pond_name'] ?? 'Common') ?></td>
                                <td><?= e($r['category']) ?></td>
                                <td class="amount-neg"><?= formatCurrency((float)$r['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No recent expenses.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-fish me-2" style="color:#0891b2"></i>Recent Sales</h5>
                <a href="<?= APP_URL ?>/sales.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Date</th><th>Pond</th><th>Fish Type</th><th>Net</th></tr></thead>
                    <tbody>
                        <?php if ($recentSales): ?>
                            <?php foreach ($recentSales as $r): ?>
                            <tr>
                                <td class="fs-13"><?= e($r['sale_date']) ?></td>
                                <td class="fs-13"><?= e($r['pond_name'] ?? 'Common') ?></td>
                                <td><?= e($r['fish_type']) ?></td>
                                <td class="amount-pos"><?= formatCurrency((float)$r['net_amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No recent sales.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Partner Advance Summary -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-hand-holding-usd me-2" style="color:#7c3aed"></i>Partner Advance Summary</h5>
        <a href="<?= APP_URL ?>/advances.php" class="btn btn-sm btn-outline-secondary">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th style="width:50px">SL</th><th>Partner</th><th>Type</th><th>Total Advance</th></tr></thead>
            <tbody>
                <?php if ($partnerAdv): ?>
                    <?php foreach ($partnerAdv as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-600"><?= e($r['name']) ?></td>
                        <td>
                            <span class="badge <?= $r['type'] === 'owner' ? 'bg-primary' : 'bg-success' ?>">
                                <?= e(ucfirst($r['type'])) ?>
                            </span>
                        </td>
                        <td class="amount-neutral"><?= formatCurrency((float)$r['total_advance']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">No partner advance data.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
