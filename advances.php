<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$pageTitle = 'Advances';
$pdo = db();

$search      = trim((string)($_GET['search'] ?? ''));
$partnerIdRaw = trim((string)($_GET['partner_id'] ?? ''));
$pondIdRaw   = trim((string)($_GET['pond_id'] ?? ''));
$filterPartnerId = $partnerIdRaw !== '' ? (int)$partnerIdRaw : null;
$filterPondId    = ($pondIdRaw !== '' && $pondIdRaw !== 'common') ? (int)$pondIdRaw : null;

$sql = "
    SELECT pa.id, pa.partner_id, pa.pond_id, pa.advance_date, pa.purpose,
           pa.amount, pa.deduct_from_profit, pa.notes, pa.created_at,
           pt.name AS partner_name, pt.type AS partner_type, pd.pond_name
    FROM partner_advances pa
    INNER JOIN partners pt ON pt.id = pa.partner_id
    LEFT JOIN ponds pd ON pd.id = pa.pond_id
";
$where  = [];
$params = [];

if ($search !== '') {
    $where[]     = "(pt.name LIKE :s OR pa.purpose LIKE :s OR pd.pond_name LIKE :s)";
    $params['s'] = '%' . $search . '%';
}

if ($filterPartnerId !== null && $filterPartnerId > 0) {
    $where[]       = "pa.partner_id = :pid";
    $params['pid'] = $filterPartnerId;
}

if ($pondIdRaw === 'common') {
    $where[] = "pa.pond_id IS NULL";
} elseif ($filterPondId !== null && $filterPondId > 0) {
    $where[]        = "pa.pond_id = :pnd";
    $params['pnd']  = $filterPondId;
}

if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY pa.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$advances = $stmt->fetchAll();

$allPartners = $pdo->query("SELECT id, name FROM partners WHERE status='active' ORDER BY name ASC")->fetchAll();
$allPonds    = $pdo->query("SELECT id, pond_name FROM ponds ORDER BY pond_name ASC")->fetchAll();

$totalAdvance       = array_sum(array_column($advances, 'amount'));
$totalDeductible    = array_sum(array_filter(array_map(fn($r) => (int)$r['deduct_from_profit'] === 1 ? $r['amount'] : 0, $advances)));
$totalNonDeductible = $totalAdvance - $totalDeductible;

$flash        = getFlash();
$advancesJson = json_encode($advances);
$partnersJson = json_encode($allPartners);
$pondsJson    = json_encode($allPonds);

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <div class="section-title">Advances</div>
        <p class="section-text">Manage partner advances and profit deductions.</p>
    </div>
    <button class="btn btn-primary" onclick="openAdvanceModal(<?= $filterPartnerId ? $filterPartnerId : 'null' ?>)">+ Add Advance</button>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="stat-title">Total Advance</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalAdvance) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-title">Deductible Advance</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalDeductible) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-title">Non-Deductible</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalNonDeductible) ?></div></div></div>
</div>

<!-- Search -->
<div class="card-soft mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Partner, purpose, pond..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Filter by Partner</label>
            <select name="partner_id" class="form-select">
                <option value="">All Partners</option>
                <?php foreach ($allPartners as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $filterPartnerId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Filter by Pond</label>
            <select name="pond_id" class="form-select">
                <option value="">All Ponds</option>
                <option value="common" <?= $pondIdRaw === 'common' ? 'selected' : '' ?>>Common / No Pond</option>
                <?php foreach ($allPonds as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $filterPondId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['pond_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="<?= APP_URL ?>/advances.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="card-soft">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Advances</h5>
        <span class="text-muted small">Total: <?= count($advances) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">SL</th>
                    <th>Date</th>
                    <th>Partner</th>
                    <th>Type</th>
                    <th>Pond</th>
                    <th>Purpose</th>
                    <th>Amount</th>
                    <th>Deduct?</th>
                    <th style="width:150px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($advances): ?>
                    <?php foreach ($advances as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['advance_date']) ?></td>
                        <td class="fw-semibold"><?= e($r['partner_name']) ?></td>
                        <td><span class="badge <?= $r['partner_type'] === 'owner' ? 'bg-primary' : 'bg-success' ?>"><?= ucfirst($r['partner_type']) ?></span></td>
                        <td><?= e($r['pond_name'] ?? 'Common') ?></td>
                        <td><?= e($r['purpose']) ?></td>
                        <td><?= formatCurrency((float)$r['amount']) ?></td>
                        <td><span class="badge <?= (int)$r['deduct_from_profit'] === 1 ? 'bg-warning text-dark' : 'bg-secondary' ?>"><?= (int)$r['deduct_from_profit'] === 1 ? 'Yes' : 'No' ?></span></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-success" onclick="openAdvanceModal(null, <?= (int)$r['id'] ?>)">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteAdvance(<?= (int)$r['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No advances found.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($advances): ?>
            <tfoot class="table-light">
                <tr><th colspan="6" class="text-end">Total</th><th><?= formatCurrency($totalAdvance) ?></th><th colspan="2"></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- ══ Advance Modal ══ -->
<div class="modal fade" id="advanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="advanceModalTitle">Add Advance</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="advanceForm" onsubmit="submitAdvanceForm(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="advId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Partner <span class="text-danger">*</span></label>
                        <select name="partner_id" id="advPartnerId" class="form-select" required>
                            <option value="">Select Partner</option>
                            <?php foreach ($allPartners as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pond</label>
                        <select name="pond_id" id="advPondId" class="form-select">
                            <option value="">Common / No Pond</option>
                            <?php foreach ($allPonds as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= e($p['pond_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Advance Date <span class="text-danger">*</span></label>
                        <input type="date" name="advance_date" id="advDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Purpose <span class="text-danger">*</span></label>
                        <input type="text" name="purpose" id="advPurpose" class="form-control" placeholder="Travel, Food, Labor..." required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="advAmount" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Deduct From Profit?</label>
                        <select name="deduct_from_profit" id="advDeduct" class="form-select">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <small class="text-muted">Yes হলে profit থেকে কাটা হবে।</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" id="advNotes" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="advAlert"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="advSubmitBtn">Save Advance</button>
            </div>
        </form>
    </div></div>
</div>

<?php
$extraJs = "<script>
const ADVANCES_DATA = $advancesJson;

function openAdvanceModal(defaultPartnerId = null, id = null) {
    const modal = new bootstrap.Modal('#advanceModal');
    const form  = document.getElementById('advanceForm');
    form.reset();
    document.getElementById('advAlert').innerHTML = '';
    document.getElementById('advDate').value = new Date().toISOString().slice(0,10);

    if (id) {
        const r = ADVANCES_DATA.find(x => x.id == id);
        if (!r) return;
        document.getElementById('advanceModalTitle').textContent = 'Edit Advance';
        document.getElementById('advSubmitBtn').textContent      = 'Update Advance';
        document.getElementById('advId').value         = r.id;
        document.getElementById('advPartnerId').value  = r.partner_id;
        document.getElementById('advPondId').value     = r.pond_id || '';
        document.getElementById('advDate').value       = r.advance_date;
        document.getElementById('advPurpose').value    = r.purpose;
        document.getElementById('advAmount').value     = r.amount;
        document.getElementById('advDeduct').value     = r.deduct_from_profit;
        document.getElementById('advNotes').value      = r.notes || '';
    } else {
        document.getElementById('advanceModalTitle').textContent = 'Add Advance';
        document.getElementById('advSubmitBtn').textContent      = 'Save Advance';
        document.getElementById('advId').value = '';
        if (defaultPartnerId) document.getElementById('advPartnerId').value = defaultPartnerId;
    }
    modal.show();
}

async function submitAdvanceForm(e) {
    e.preventDefault();
    const data   = formData(e.target);
    const isEdit = !!data.id;
    const url    = isEdit ? '" . APP_URL . "/ajax/advances.php?action=edit' : '" . APP_URL . "/ajax/advances.php?action=add';
    const btn    = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    const res    = await apiPost(url, data);
    btn.disabled = false;
    if (res.success) {
        bootstrap.Modal.getInstance('#advanceModal').hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('advAlert').innerHTML = '<div class=\"alert alert-danger py-2 mt-2\">' + res.message + '</div>';
    }
}

async function deleteAdvance(id) {
    if (!confirmAction('Delete this advance?')) return;
    const res = await apiPost('" . APP_URL . "/ajax/advances.php?action=delete', { id });
    if (res.success) { showToast(res.message, 'success'); setTimeout(reloadPage, 500); }
    else showToast(res.message, 'danger');
}
</script>";

include __DIR__ . '/includes/layout_footer.php';
?>
