<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$pageTitle = 'Profit Summary';
$pdo = db();

/* Sales */
$totalPondSales   = (float)$pdo->query("SELECT COALESCE(SUM(net_amount),0) FROM pond_sales WHERE pond_id IS NOT NULL")->fetchColumn();
$totalCommonSales = (float)$pdo->query("SELECT COALESCE(SUM(net_amount),0) FROM pond_sales WHERE pond_id IS NULL")->fetchColumn();
$totalNetSales    = $totalPondSales + $totalCommonSales;

/* Expenses */
$totalPondExpense   = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM pond_expenses WHERE pond_id IS NOT NULL")->fetchColumn();
$totalCommonExpense = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM pond_expenses WHERE pond_id IS NULL")->fetchColumn();
$totalOverallExp    = $totalPondExpense + $totalCommonExpense;

/* Net Profit */
$netProfit  = $totalNetSales - $totalOverallExp;
$ownerPool  = $netProfit * (1 / 3);
$workerPool = $netProfit * (2 / 3);

/* Pond-wise */
$pondRows = $pdo->query("
    SELECT p.id, p.pond_name, p.land_owner_name, p.lease_amount,
        COALESCE(e.total_exp,0) AS total_expense,
        COALESCE(s.total_net,0) AS total_net_sale
    FROM ponds p
    LEFT JOIN (SELECT pond_id, SUM(amount) AS total_exp FROM pond_expenses WHERE pond_id IS NOT NULL GROUP BY pond_id) e ON e.pond_id = p.id
    LEFT JOIN (SELECT pond_id, SUM(net_amount) AS total_net FROM pond_sales WHERE pond_id IS NOT NULL GROUP BY pond_id) s ON s.pond_id = p.id
    ORDER BY p.id ASC
")->fetchAll();

$totalPondWiseProfit = 0;
foreach ($pondRows as &$r) {
    $r['profit_preview'] = (float)$r['total_net_sale'] - (float)$r['total_expense'];
    $totalPondWiseProfit += $r['profit_preview'];
}
unset($r);

/* Active partners */
$partners = $pdo->query("SELECT * FROM partners WHERE status='active' ORDER BY type ASC, id ASC")->fetchAll();

/* Advance deductible map */
$advRows = $pdo->query("
    SELECT partner_id, COALESCE(SUM(amount),0) AS total
    FROM partner_advances WHERE deduct_from_profit=1 GROUP BY partner_id
")->fetchAll();
$advMap = [];
foreach ($advRows as $r) $advMap[(int)$r['partner_id']] = (float)$r['total'];

/* Advance detail map */
$advDetailRows = $pdo->query("
    SELECT pa.partner_id, pa.advance_date, pa.purpose, pa.amount, pd.pond_name
    FROM partner_advances pa
    INNER JOIN partners p ON p.id = pa.partner_id
    LEFT JOIN ponds pd ON pd.id = pa.pond_id
    WHERE pa.deduct_from_profit=1
    ORDER BY pa.advance_date DESC, pa.id DESC
")->fetchAll();
$advDetailsByPartner = [];
foreach ($advDetailRows as $r) {
    $advDetailsByPartner[(int)$r['partner_id']][] = $r;
}

/* Separate */
$owners  = array_filter($partners, fn($p) => $p['type'] === 'owner');
$workers = array_filter($partners, fn($p) => $p['type'] === 'worker');
$totalOwnerUnits  = array_sum(array_column(iterator_to_array($owners), 'share_unit'));
$totalWorkerUnits = array_sum(array_column(iterator_to_array($workers), 'share_unit'));

/* Settlement */
function calcSettlement(array $list, float $pool, float $totalUnits, array $advMap, array $advDetails): array {
    $result = [];
    foreach ($list as $p) {
        $pid   = (int)$p['id'];
        $unit  = (float)$p['share_unit'];
        $adv   = $advMap[$pid] ?? 0;
        $gross = $totalUnits > 0 ? ($pool * ($unit / $totalUnits)) : 0;
        $result[] = [
            'id'             => $pid,
            'name'           => $p['name'],
            'share_unit'     => $unit,
            'gross_share'    => $gross,
            'advance'        => $adv,
            'final_payable'  => $gross - $adv,
            'advance_details'=> $advDetails[$pid] ?? [],
        ];
    }
    return $result;
}

$ownerSettlements  = calcSettlement($owners,  $ownerPool,  $totalOwnerUnits,  $advMap, $advDetailsByPartner);
$workerSettlements = calcSettlement($workers, $workerPool, $totalWorkerUnits, $advMap, $advDetailsByPartner);

$totalOwnerAdv  = array_sum(array_column($ownerSettlements, 'advance'));
$totalOwnerPay  = array_sum(array_column($ownerSettlements, 'final_payable'));
$totalWorkerAdv = array_sum(array_column($workerSettlements, 'advance'));
$totalWorkerPay = array_sum(array_column($workerSettlements, 'final_payable'));

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <div class="section-title">Profit Summary</div>
        <p class="section-text">Overall performance, pond-wise preview, and partner settlement.</p>
    </div>
</div>

<!-- Main Summary -->
<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Total Net Sales</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalNetSales) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Total Expenses</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalOverallExp) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Net Profit / Loss</div><div class="stat-value <?= $netProfit < 0 ? 'profit-negative' : 'profit-positive' ?>" style="font-size:26px"><?= formatCurrency($netProfit) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Owner Pool (1/3)</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($ownerPool) ?></div></div></div>
</div>

<!-- Breakdown -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card-soft">
            <h5 class="mb-3">Sales Breakdown</h5>
            <table class="table table-bordered align-middle mb-0">
                <tr><th style="width:240px">Pond-wise Net Sales</th><td><?= formatCurrency($totalPondSales) ?></td></tr>
                <tr><th>Common Net Sales</th><td><?= formatCurrency($totalCommonSales) ?></td></tr>
                <tr class="table-light fw-semibold"><th>Total Net Sales</th><td><?= formatCurrency($totalNetSales) ?></td></tr>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft">
            <h5 class="mb-3">Expense Breakdown</h5>
            <table class="table table-bordered align-middle mb-0">
                <tr><th style="width:240px">Pond-wise Expenses</th><td><?= formatCurrency($totalPondExpense) ?></td></tr>
                <tr><th>Common Expenses</th><td><?= formatCurrency($totalCommonExpense) ?></td></tr>
                <tr class="table-light fw-semibold"><th>Total Expenses</th><td><?= formatCurrency($totalOverallExp) ?></td></tr>
            </table>
        </div>
    </div>
</div>

<!-- Pool & Unit Summary -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card-soft">
            <h5 class="mb-3">Share Pool Summary</h5>
            <table class="table table-bordered align-middle mb-0">
                <tr><th style="width:240px">Owner Pool (1/3)</th><td><?= formatCurrency($ownerPool) ?></td></tr>
                <tr><th>Worker Pool (2/3)</th><td><?= formatCurrency($workerPool) ?></td></tr>
                <tr class="table-light fw-semibold"><th>Total Profit Base</th><td><?= formatCurrency($ownerPool + $workerPool) ?></td></tr>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-soft">
            <h5 class="mb-3">Share Unit Summary</h5>
            <table class="table table-bordered align-middle mb-0">
                <tr><th style="width:240px">Owner Total Units</th><td><?= number_format($totalOwnerUnits, 2) ?></td></tr>
                <tr><th>Worker Total Units</th><td><?= number_format($totalWorkerUnits, 2) ?></td></tr>
                <tr class="table-light fw-semibold"><th>Active Partners</th><td><?= count($partners) ?></td></tr>
            </table>
        </div>
    </div>
</div>

<!-- Pond-wise Profit -->
<div class="card-soft mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Pond-wise Profit Preview</h5>
        <span class="text-muted small">Common sales/expenses excluded</span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr><th style="width:50px">SL</th><th>Pond Name</th><th>Owner</th><th>Total Expense</th><th>Total Net Sale</th><th>Profit Preview</th></tr>
            </thead>
            <tbody>
                <?php if ($pondRows): ?>
                    <?php foreach ($pondRows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= e($r['pond_name']) ?></td>
                        <td><?= e($r['land_owner_name'] ?? '') ?></td>
                        <td><?= formatCurrency((float)$r['total_expense']) ?></td>
                        <td><?= formatCurrency((float)$r['total_net_sale']) ?></td>
                        <td class="<?= $r['profit_preview'] < 0 ? 'profit-negative' : 'profit-positive' ?>"><?= formatCurrency($r['profit_preview']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No pond data.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($pondRows): ?>
            <tfoot class="table-light">
                <tr><th colspan="5" class="text-end">Total Pond-wise Profit</th><th class="<?= $totalPondWiseProfit < 0 ? 'profit-negative' : 'profit-positive' ?>"><?= formatCurrency($totalPondWiseProfit) ?></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php
/* Settlement table renderer */
function renderSettlement(array $rows, string $title, float $pool, float $totalAdv, float $totalPay): void { ?>
<div class="card-soft mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><?= e($title) ?></h5>
        <span class="text-muted small">Pool: <?= formatCurrency($pool) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr><th style="width:50px">SL</th><th>Name</th><th>Share Unit</th><th>Gross Share</th><th>Deductible Advance</th><th>Final Payable</th></tr>
            </thead>
            <tbody>
                <?php if ($rows): ?>
                    <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= e($r['name']) ?></td>
                        <td><?= number_format((float)$r['share_unit'], 2) ?></td>
                        <td><?= formatCurrency((float)$r['gross_share']) ?></td>
                        <td><?= formatCurrency((float)$r['advance']) ?></td>
                        <td class="<?= (float)$r['final_payable'] < 0 ? 'profit-negative' : 'fw-semibold' ?>"><?= formatCurrency((float)$r['final_payable']) ?></td>
                    </tr>
                    <?php if (!empty($r['advance_details'])): ?>
                    <tr>
                        <td></td>
                        <td colspan="5">
                            <div class="small text-muted mb-1">Advance Details</div>
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light"><tr><th>Date</th><th>Pond</th><th>Purpose</th><th>Amount</th></tr></thead>
                                <tbody>
                                    <?php foreach ($r['advance_details'] as $d): ?>
                                    <tr>
                                        <td><?= e($d['advance_date']) ?></td>
                                        <td><?= e($d['pond_name'] ?? 'Common') ?></td>
                                        <td><?= e($d['purpose']) ?></td>
                                        <td><?= formatCurrency((float)$d['amount']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No active <?= strtolower($title) ?> found.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($rows): ?>
            <tfoot class="table-light">
                <tr><th colspan="3" class="text-end">Totals</th><th><?= formatCurrency($pool) ?></th><th><?= formatCurrency($totalAdv) ?></th><th><?= formatCurrency($totalPay) ?></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php } ?>

<?php renderSettlement($ownerSettlements,  'Owner Settlement',  $ownerPool,  $totalOwnerAdv,  $totalOwnerPay); ?>
<?php renderSettlement($workerSettlements, 'Worker Settlement', $workerPool, $totalWorkerAdv, $totalWorkerPay); ?>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
