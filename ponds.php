<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = db();

/* ── Detail view ── */
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($viewId > 0) {
    $pond = $pdo->prepare("SELECT * FROM ponds WHERE id = ? LIMIT 1");
    $pond->execute([$viewId]);
    $pond = $pond->fetch();

    if (!$pond) {
        setFlash('danger', 'Pond not found.');
        redirect(APP_URL . '/ponds.php');
    }

    $expSum = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM pond_expenses WHERE pond_id = ?");
    $expSum->execute([$viewId]);
    $totalExpense = (float)$expSum->fetchColumn();

    $salSum = $pdo->prepare("
        SELECT COALESCE(SUM(gross_amount),0) AS gross, COALESCE(SUM(sale_expense),0) AS sal_exp, COALESCE(SUM(net_amount),0) AS net
        FROM pond_sales WHERE pond_id = ?
    ");
    $salSum->execute([$viewId]);
    $salRow = $salSum->fetch();
    $totalGross   = (float)$salRow['gross'];
    $totalSalExp  = (float)$salRow['sal_exp'];
    $totalNetSale = (float)$salRow['net'];
    $pondProfit   = $totalNetSale - $totalExpense;

    $expenses = $pdo->prepare("SELECT * FROM pond_expenses WHERE pond_id = ? ORDER BY expense_date DESC, id DESC");
    $expenses->execute([$viewId]);
    $expenses = $expenses->fetchAll();

    $sales = $pdo->prepare("SELECT * FROM pond_sales WHERE pond_id = ? ORDER BY sale_date DESC, id DESC");
    $sales->execute([$viewId]);
    $sales = $sales->fetchAll();

    $pageTitle = e($pond['pond_name']);
    include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <div class="section-title"><?= e($pond['pond_name']) ?></div>
        <p class="section-text">Pond details, expenses, sales, and profit preview.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/expenses.php?pond_id=<?= $viewId ?>" class="btn btn-primary btn-sm">+ Add Expense</a>
        <a href="<?= APP_URL ?>/sales.php?pond_id=<?= $viewId ?>" class="btn btn-success btn-sm">+ Add Sale</a>
        <button class="btn btn-outline-secondary btn-sm" onclick="openPondModal(<?= (int)$pond['id'] ?>)">Edit</button>
        <a href="<?= APP_URL ?>/ponds.php" class="btn btn-light border btn-sm">← Back</a>
    </div>
</div>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Lease Amount</div><div class="stat-value" style="font-size:26px"><?= formatCurrency((float)$pond['lease_amount']) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Total Expense</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalExpense) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Total Net Sale</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalNetSale) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Profit Preview</div><div class="stat-value <?= $pondProfit < 0 ? 'profit-negative' : 'profit-positive' ?>" style="font-size:26px"><?= formatCurrency($pondProfit) ?></div></div></div>
</div>

<div class="card-soft mb-4">
    <h5 class="mb-3">Pond Information</h5>
    <table class="table table-bordered align-middle mb-0">
        <tr><th style="width:220px">Pond Name</th><td><?= e($pond['pond_name']) ?></td></tr>
        <tr><th>Owner Name</th><td><?= e($pond['land_owner_name'] ?? '') ?></td></tr>
        <tr><th>Lease Amount</th><td><?= formatCurrency((float)$pond['lease_amount']) ?></td></tr>
        <tr><th>Total Gross Sale</th><td><?= formatCurrency($totalGross) ?></td></tr>
        <tr><th>Total Sale Expense</th><td><?= formatCurrency($totalSalExp) ?></td></tr>
        <tr><th>Total Net Sale</th><td><?= formatCurrency($totalNetSale) ?></td></tr>
        <tr><th>Total Expense</th><td><?= formatCurrency($totalExpense) ?></td></tr>
        <tr><th>Profit Preview</th><td class="<?= $pondProfit < 0 ? 'profit-negative' : 'profit-positive' ?>"><?= formatCurrency($pondProfit) ?></td></tr>
        <?php if ($pond['notes']): ?><tr><th>Notes</th><td><?= nl2br(e($pond['notes'])) ?></td></tr><?php endif; ?>
        <tr><th>Created At</th><td><?= e($pond['created_at'] ?? '') ?></td></tr>
    </table>
</div>

<div class="card-soft mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Expense History</h5>
        <a href="<?= APP_URL ?>/expenses.php?pond_id=<?= $viewId ?>" class="btn btn-sm btn-primary">+ Add Expense</a>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr><th style="width:50px">SL</th><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Paid By</th></tr>
            </thead>
            <tbody>
                <?php if ($expenses): ?>
                    <?php foreach ($expenses as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['expense_date']) ?></td>
                        <td><?= e($r['category']) ?></td>
                        <td><?= e($r['description'] ?? '') ?></td>
                        <td><?= formatCurrency((float)$r['amount']) ?></td>
                        <td><?= e($r['paid_by'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-3 text-muted">No expenses for this pond.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($expenses): ?>
            <tfoot class="table-light">
                <tr><th colspan="4" class="text-end">Total</th><th><?= formatCurrency($totalExpense) ?></th><th></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="card-soft">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Sales History</h5>
        <a href="<?= APP_URL ?>/sales.php?pond_id=<?= $viewId ?>" class="btn btn-sm btn-success">+ Add Sale</a>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr><th style="width:50px">SL</th><th>Date</th><th>Type</th><th>Fish Type</th><th>Qty KG</th><th>Unit Price</th><th>Gross</th><th>Sale Exp</th><th>Net</th><th>Buyer</th></tr>
            </thead>
            <tbody>
                <?php if ($sales): ?>
                    <?php foreach ($sales as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['sale_date']) ?></td>
                        <td><?= $r['entry_type'] === 'direct' ? 'Direct' : 'Measured' ?></td>
                        <td><?= e($r['fish_type']) ?></td>
                        <td><?= $r['entry_type'] === 'direct' ? '—' : number_format((float)$r['quantity_kg'], 2) ?></td>
                        <td><?= $r['entry_type'] === 'direct' ? '—' : formatCurrency((float)$r['unit_price']) ?></td>
                        <td><?= formatCurrency((float)$r['gross_amount']) ?></td>
                        <td><?= formatCurrency((float)$r['sale_expense']) ?></td>
                        <td><?= formatCurrency((float)$r['net_amount']) ?></td>
                        <td><?= e($r['buyer_name'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center py-3 text-muted">No sales for this pond.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($sales): ?>
            <tfoot class="table-light">
                <tr><th colspan="6" class="text-end">Totals</th><th><?= formatCurrency($totalGross) ?></th><th><?= formatCurrency($totalSalExp) ?></th><th><?= formatCurrency($totalNetSale) ?></th><th></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php
    $pondsJson = json_encode([$pond]);
    $extraJs = "<script>const PONDS_DATA = $pondsJson;</script>";
    include __DIR__ . '/includes/layout_footer.php';
    exit;
}

/* ── List view ── */
$pageTitle = 'Ponds';
$search    = trim((string)($_GET['search'] ?? ''));

$sql = "
    SELECT p.id, p.pond_name, p.land_owner_name, p.lease_amount, p.notes, p.created_at,
           COALESCE(SUM(pe.amount),0) AS total_amount
    FROM ponds p
    LEFT JOIN pond_expenses pe ON pe.pond_id = p.id
";
$params = [];
if ($search !== '') {
    $sql .= " WHERE (p.pond_name LIKE :s OR p.land_owner_name LIKE :s)";
    $params['s'] = '%' . $search . '%';
}
$sql .= " GROUP BY p.id ORDER BY p.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ponds = $stmt->fetchAll();

$totalPonds       = count($ponds);
$totalPondExpense = array_sum(array_column($ponds, 'total_amount'));
$totalLease       = array_sum(array_column($ponds, 'lease_amount'));

$commonExpense = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM pond_expenses WHERE pond_id IS NULL")->fetchColumn();
$grandTotal    = $totalPondExpense + $commonExpense;

$flash = getFlash();
$pondsJson = json_encode($ponds);

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <div class="section-title">Ponds</div>
        <p class="section-text">Manage all ponds with lease and expense summary.</p>
    </div>
    <button class="btn btn-primary" onclick="openPondModal()">+ Add Pond</button>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Total Ponds</div><div class="stat-value"><?= $totalPonds ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Total Lease</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalLease) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Common Expense</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($commonExpense) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Grand Total Expense</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($grandTotal) ?></div></div></div>
</div>

<!-- Search -->
<div class="card-soft mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-7">
            <label class="form-label fw-semibold">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Pond name or owner name..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-5 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="<?= APP_URL ?>/ponds.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="card-soft">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Ponds</h5>
        <span class="text-muted small">Total: <?= $totalPonds ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">SL</th>
                    <th>Pond Name</th>
                    <th>Owner</th>
                    <th>Lease</th>
                    <th>Total Expense</th>
                    <th style="width:200px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ponds): ?>
                    <?php foreach ($ponds as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= e($p['pond_name']) ?></td>
                        <td><?= e($p['land_owner_name'] ?? '') ?></td>
                        <td><?= formatCurrency((float)$p['lease_amount']) ?></td>
                        <td><?= formatCurrency((float)$p['total_amount']) ?></td>
                        <td class="text-nowrap">
                            <a href="<?= APP_URL ?>/ponds.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <button class="btn btn-sm btn-outline-success" onclick="openPondModal(<?= (int)$p['id'] ?>)">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePond(<?= (int)$p['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No ponds found.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($ponds): ?>
            <tfoot class="table-light">
                <tr><th colspan="3" class="text-end">Totals</th><th><?= formatCurrency($totalLease) ?></th><th><?= formatCurrency($totalPondExpense) ?></th><th></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php if ($ponds): ?>
    <div class="mt-3 d-flex justify-content-end">
        <table class="table table-sm table-bordered mb-0" style="min-width:340px">
            <tr><th>Pond-wise Total Expense</th><td class="text-end"><?= formatCurrency($totalPondExpense) ?></td></tr>
            <tr><th>Common Expense</th><td class="text-end"><?= formatCurrency($commonExpense) ?></td></tr>
            <tr class="table-light fw-semibold"><th>Grand Total</th><td class="text-end"><?= formatCurrency($grandTotal) ?></td></tr>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ══ Pond Modal ══ -->
<div class="modal fade" id="pondModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="pondModalTitle">Add Pond</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="pondForm" onsubmit="submitPondForm(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="pondId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pond Name <span class="text-danger">*</span></label>
                        <input type="text" name="pond_name" id="pondName" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Owner Name <span class="text-danger">*</span></label>
                        <input type="text" name="owner_name" id="pondOwner" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Lease Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="lease_amount" id="pondLease" class="form-control" value="0" required>
                        <small class="text-muted">New pond এ lease দিলে auto expense তৈরি হবে।</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" id="pondNotes" class="form-control" rows="3"></textarea>
                    </div>
                    <div id="pondAlert"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="pondSubmitBtn">Save Pond</button>
            </div>
        </form>
    </div></div>
</div>

<?php
$extraJs = "<script>
const PONDS_DATA = $pondsJson;

function openPondModal(id = null) {
    const modal = new bootstrap.Modal('#pondModal');
    const form  = document.getElementById('pondForm');
    form.reset();
    document.getElementById('pondAlert').innerHTML = '';

    if (id) {
        const p = PONDS_DATA.find(x => x.id == id);
        if (!p) return;
        document.getElementById('pondModalTitle').textContent = 'Edit Pond';
        document.getElementById('pondSubmitBtn').textContent  = 'Update Pond';
        document.getElementById('pondId').value    = p.id;
        document.getElementById('pondName').value  = p.pond_name;
        document.getElementById('pondOwner').value = p.land_owner_name || '';
        document.getElementById('pondLease').value = p.lease_amount;
        document.getElementById('pondNotes').value = p.notes || '';
    } else {
        document.getElementById('pondModalTitle').textContent = 'Add Pond';
        document.getElementById('pondSubmitBtn').textContent  = 'Save Pond';
        document.getElementById('pondId').value = '';
    }
    modal.show();
}

async function submitPondForm(e) {
    e.preventDefault();
    const data   = formData(e.target);
    const isEdit = !!data.id;
    const url    = isEdit ? '" . APP_URL . "/ajax/ponds.php?action=edit' : '" . APP_URL . "/ajax/ponds.php?action=add';
    const btn    = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    const res    = await apiPost(url, data);
    btn.disabled = false;
    if (res.success) {
        bootstrap.Modal.getInstance('#pondModal').hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('pondAlert').innerHTML = '<div class=\"alert alert-danger py-2 mt-2\">' + res.message + '</div>';
    }
}

async function deletePond(id) {
    if (!confirmAction('Delete this pond? All related expenses and sales will also be affected.')) return;
    const res = await apiPost('" . APP_URL . "/ajax/ponds.php?action=delete', { id });
    if (res.success) { showToast(res.message, 'success'); setTimeout(reloadPage, 500); }
    else showToast(res.message, 'danger');
}
</script>";

include __DIR__ . '/includes/layout_footer.php';
?>
