<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$pageTitle = 'Expenses';
$pdo = db();

$search    = trim((string)($_GET['search'] ?? ''));
$pondIdRaw = trim((string)($_GET['pond_id'] ?? ''));
$pondId    = ($pondIdRaw !== '' && $pondIdRaw !== 'common') ? (int)$pondIdRaw : null;

$sql = "
    SELECT pe.id, pe.pond_id, pe.expense_date, pe.category, pe.description,
           pe.amount, pe.paid_by, pe.notes, pe.created_at, p.pond_name
    FROM pond_expenses pe
    LEFT JOIN ponds p ON p.id = pe.pond_id
";
$where  = [];
$params = [];

if ($search !== '') {
    $where[]       = "(pe.category LIKE :s OR pe.description LIKE :s OR pe.paid_by LIKE :s OR p.pond_name LIKE :s)";
    $params['s']   = '%' . $search . '%';
}

if ($pondIdRaw === 'common') {
    $where[] = "pe.pond_id IS NULL";
} elseif ($pondId !== null && $pondId > 0) {
    $where[]          = "pe.pond_id = :pid";
    $params['pid']    = $pondId;
}

if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY pe.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$ponds    = $pdo->query("SELECT id, pond_name FROM ponds ORDER BY pond_name ASC")->fetchAll();
$totalAmt = array_sum(array_column($expenses, 'amount'));

$categories = ['Feed','Medicine','Labor','Transport','Equipment','Electricity','Lease','Pond Preparation','Miscellaneous'];

$flash       = getFlash();
$expensesJson = json_encode($expenses);
$pondsJson    = json_encode($ponds);

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <div class="section-title">Expenses</div>
        <p class="section-text">Manage pond-specific and common expenses.</p>
    </div>
    <button class="btn btn-primary" onclick="openExpenseModal(<?= $pondId ? $pondId : 'null' ?>)">+ Add Expense</button>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Search -->
<div class="card-soft mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label fw-semibold">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Category, pond, description, paid by..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Filter by Pond</label>
            <select name="pond_id" class="form-select">
                <option value="">All Expenses</option>
                <option value="common" <?= $pondIdRaw === 'common' ? 'selected' : '' ?>>Common Expense</option>
                <?php foreach ($ponds as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $pondId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['pond_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="<?= APP_URL ?>/expenses.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="card-soft">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Expenses</h5>
        <span class="text-muted small">Total: <?= count($expenses) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">SL</th>
                    <th>Date</th>
                    <th>Pond</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Paid By</th>
                    <th style="width:150px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expenses): ?>
                    <?php foreach ($expenses as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['expense_date']) ?></td>
                        <td><?= e($r['pond_name'] ?? 'Common') ?></td>
                        <td><?= e($r['category']) ?></td>
                        <td><?= e($r['description'] ?? '') ?></td>
                        <td><?= formatCurrency((float)$r['amount']) ?></td>
                        <td><?= e($r['paid_by'] ?? '') ?></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-success" onclick="openExpenseModal(null, <?= (int)$r['id'] ?>)">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteExpense(<?= (int)$r['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No expenses found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-3 text-end fw-semibold">
        Total Expense: <?= formatCurrency($totalAmt) ?>
    </div>
</div>

<!-- ══ Expense Modal ══ -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="expenseModalTitle">Add Expense</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="expenseForm" onsubmit="submitExpenseForm(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="expId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pond</label>
                        <select name="pond_id" id="expPondId" class="form-select">
                            <option value="">Common Expense (All Ponds)</option>
                            <?php foreach ($ponds as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= e($p['pond_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Expense Date <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" id="expDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select name="category" id="expCategory" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="expAmount" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Paid By</label>
                        <input type="text" name="paid_by" id="expPaidBy" class="form-control" placeholder="Who paid?">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" id="expDesc" class="form-control" placeholder="Short description">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" id="expNotes" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="expAlert"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="expSubmitBtn">Save Expense</button>
            </div>
        </form>
    </div></div>
</div>

<?php
$extraJs = "<script>
const EXPENSES_DATA = $expensesJson;
const PONDS_DATA    = $pondsJson;

function openExpenseModal(defaultPondId = null, id = null) {
    const modal = new bootstrap.Modal('#expenseModal');
    const form  = document.getElementById('expenseForm');
    form.reset();
    document.getElementById('expAlert').innerHTML = '';
    document.getElementById('expDate').value = new Date().toISOString().slice(0,10);

    if (id) {
        const r = EXPENSES_DATA.find(x => x.id == id);
        if (!r) return;
        document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
        document.getElementById('expSubmitBtn').textContent      = 'Update Expense';
        document.getElementById('expId').value       = r.id;
        document.getElementById('expPondId').value   = r.pond_id || '';
        document.getElementById('expDate').value     = r.expense_date;
        document.getElementById('expCategory').value = r.category;
        document.getElementById('expAmount').value   = r.amount;
        document.getElementById('expPaidBy').value   = r.paid_by || '';
        document.getElementById('expDesc').value     = r.description || '';
        document.getElementById('expNotes').value    = r.notes || '';
    } else {
        document.getElementById('expenseModalTitle').textContent = 'Add Expense';
        document.getElementById('expSubmitBtn').textContent      = 'Save Expense';
        document.getElementById('expId').value = '';
        if (defaultPondId) document.getElementById('expPondId').value = defaultPondId;
    }
    modal.show();
}

async function submitExpenseForm(e) {
    e.preventDefault();
    const data   = formData(e.target);
    const isEdit = !!data.id;
    const url    = isEdit ? '" . APP_URL . "/ajax/expenses.php?action=edit' : '" . APP_URL . "/ajax/expenses.php?action=add';
    const btn    = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    const res    = await apiPost(url, data);
    btn.disabled = false;
    if (res.success) {
        bootstrap.Modal.getInstance('#expenseModal').hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('expAlert').innerHTML = '<div class=\"alert alert-danger py-2 mt-2\">' + res.message + '</div>';
    }
}

async function deleteExpense(id) {
    if (!confirmAction('Delete this expense?')) return;
    const res = await apiPost('" . APP_URL . "/ajax/expenses.php?action=delete', { id });
    if (res.success) { showToast(res.message, 'success'); setTimeout(reloadPage, 500); }
    else showToast(res.message, 'danger');
}
</script>";

include __DIR__ . '/includes/layout_footer.php';
?>
