<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$pageTitle = 'Partners';
$pdo = db();

/* ── Detail view ── */
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($viewId > 0) {
    $partnerStmt = $pdo->prepare("SELECT * FROM partners WHERE id = ? LIMIT 1");
    $partnerStmt->execute([$viewId]);
    $partner = $partnerStmt->fetch();

    if (!$partner) {
        setFlash('danger', 'Partner not found.');
        redirect(APP_URL . '/partners.php');
    }

    $advances = $pdo->prepare("
        SELECT pa.*, pd.pond_name
        FROM partner_advances pa
        LEFT JOIN ponds pd ON pd.id = pa.pond_id
        WHERE pa.partner_id = ?
        ORDER BY pa.advance_date DESC, pa.id DESC
    ");
    $advances->execute([$viewId]);
    $advances = $advances->fetchAll();
    $totalAdv = array_sum(array_column($advances, 'amount'));

    $pageTitle = e($partner['name']);
    include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <div class="section-title"><?= e($partner['name']) ?></div>
        <p class="section-text">Partner details and advance history.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/advances.php?partner_id=<?= $viewId ?>" class="btn btn-primary btn-sm">+ Add Advance</a>
        <button class="btn btn-outline-secondary btn-sm" onclick="openPartnerModal(<?= (int)$partner['id'] ?>)">Edit</button>
        <a href="<?= APP_URL ?>/partners.php" class="btn btn-light border btn-sm">← Back</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="stat-title">Type</div><div class="stat-value" style="font-size:22px"><?= ucfirst(e($partner['type'])) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-title">Share Unit</div><div class="stat-value" style="font-size:26px"><?= number_format((float)$partner['share_unit'], 2) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-title">Total Advance</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalAdv) ?></div></div></div>
</div>

<div class="card-soft mb-4">
    <h5 class="mb-3">Partner Information</h5>
    <table class="table table-bordered align-middle mb-0">
        <tr><th style="width:180px">Name</th><td><?= e($partner['name']) ?></td></tr>
        <tr><th>Phone</th><td><?= e($partner['phone'] ?? '') ?></td></tr>
        <tr><th>Address</th><td><?= e($partner['address'] ?? '') ?></td></tr>
        <tr><th>Type</th><td><span class="badge <?= $partner['type'] === 'owner' ? 'bg-primary' : 'bg-success' ?>"><?= ucfirst($partner['type']) ?></span></td></tr>
        <tr><th>Share Unit</th><td><?= number_format((float)$partner['share_unit'], 2) ?></td></tr>
        <tr><th>Status</th><td><span class="badge <?= $partner['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($partner['status']) ?></span></td></tr>
        <?php if ($partner['notes']): ?><tr><th>Notes</th><td><?= nl2br(e($partner['notes'])) ?></td></tr><?php endif; ?>
    </table>
</div>

<div class="card-soft">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Advance History</h5>
        <a href="<?= APP_URL ?>/advances.php?partner_id=<?= $viewId ?>" class="btn btn-sm btn-primary">+ Add Advance</a>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr><th style="width:50px">SL</th><th>Date</th><th>Pond</th><th>Purpose</th><th>Amount</th><th>Deduct?</th></tr>
            </thead>
            <tbody>
                <?php if ($advances): ?>
                    <?php foreach ($advances as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['advance_date']) ?></td>
                        <td><?= e($r['pond_name'] ?? 'Common') ?></td>
                        <td><?= e($r['purpose']) ?></td>
                        <td><?= formatCurrency((float)$r['amount']) ?></td>
                        <td>
                            <span class="badge <?= (int)$r['deduct_from_profit'] === 1 ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                                <?= (int)$r['deduct_from_profit'] === 1 ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-3 text-muted">No advances for this partner.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($advances): ?>
            <tfoot class="table-light">
                <tr><th colspan="4" class="text-end">Total</th><th><?= formatCurrency($totalAdv) ?></th><th></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php
    $partnersJson = json_encode([$partner]);
    $extraJs = "<script>const PARTNERS_DATA = $partnersJson;</script>";
    include __DIR__ . '/includes/layout_footer.php';
    exit;
}

/* ── List view ── */
$search = trim((string)($_GET['search'] ?? ''));
$type   = trim((string)($_GET['type'] ?? ''));

$sql    = "SELECT * FROM partners";
$where  = [];
$params = [];

if ($search !== '') {
    $where[]     = "(name LIKE :s OR phone LIKE :s OR address LIKE :s)";
    $params['s'] = '%' . $search . '%';
}

if (in_array($type, ['owner', 'worker'], true)) {
    $where[]       = "type = :t";
    $params['t']   = $type;
}

if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$partners = $stmt->fetchAll();

$totalPartners  = count($partners);
$totalOwners    = count(array_filter($partners, fn($p) => $p['type'] === 'owner'));
$totalWorkers   = $totalPartners - $totalOwners;
$totalShareUnit = array_sum(array_column($partners, 'share_unit'));

$flash        = getFlash();
$partnersJson = json_encode($partners);

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <div class="section-title">Partners</div>
        <p class="section-text">Manage all owners and working partners.</p>
    </div>
    <button class="btn btn-primary" onclick="openPartnerModal()">+ Add Partner</button>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Total Partners</div><div class="stat-value"><?= $totalPartners ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Owners</div><div class="stat-value"><?= $totalOwners ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Workers</div><div class="stat-value"><?= $totalWorkers ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-title">Total Share Unit</div><div class="stat-value" style="font-size:26px"><?= number_format($totalShareUnit, 2) ?></div></div></div>
</div>

<!-- Search -->
<div class="card-soft mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label fw-semibold">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Name, phone, address..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Filter by Type</label>
            <select name="type" class="form-select">
                <option value="">All Types</option>
                <option value="owner" <?= $type === 'owner' ? 'selected' : '' ?>>Owner</option>
                <option value="worker" <?= $type === 'worker' ? 'selected' : '' ?>>Worker</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="<?= APP_URL ?>/partners.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="card-soft">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Partners</h5>
        <span class="text-muted small">Total: <?= $totalPartners ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">SL</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Share Unit</th>
                    <th>Status</th>
                    <th style="width:200px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($partners): ?>
                    <?php foreach ($partners as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= e($p['name']) ?></td>
                        <td><?= e($p['phone'] ?? '') ?></td>
                        <td><span class="badge <?= $p['type'] === 'owner' ? 'bg-primary' : 'bg-success' ?>"><?= ucfirst($p['type']) ?></span></td>
                        <td><?= number_format((float)$p['share_unit'], 2) ?></td>
                        <td><span class="badge <?= $p['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($p['status']) ?></span></td>
                        <td class="text-nowrap">
                            <a href="<?= APP_URL ?>/partners.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <button class="btn btn-sm btn-outline-success" onclick="openPartnerModal(<?= (int)$p['id'] ?>)">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePartner(<?= (int)$p['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No partners found.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($partners): ?>
            <tfoot class="table-light">
                <tr><th colspan="4" class="text-end">Total Share Unit</th><th><?= number_format($totalShareUnit, 2) ?></th><th colspan="2"></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- ══ Partner Modal ══ -->
<div class="modal fade" id="partnerModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="partnerModalTitle">Add Partner</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="partnerForm" onsubmit="submitPartnerForm(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="partnerId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="partnerName" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="phone" id="partnerPhone" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                        <select name="type" id="partnerType" class="form-select" required>
                            <option value="worker">Worker</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Share Unit <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="share_unit" id="partnerShare" class="form-control" value="0" required>
                        <small class="text-muted">e.g. Owner = 1, Worker = 0.50</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" id="partnerStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" name="address" id="partnerAddress" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" id="partnerNotes" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="partnerAlert"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="partnerSubmitBtn">Save Partner</button>
            </div>
        </form>
    </div></div>
</div>

<?php
$extraJs = "<script>
const PARTNERS_DATA = $partnersJson;

function openPartnerModal(id = null) {
    const modal = new bootstrap.Modal('#partnerModal');
    const form  = document.getElementById('partnerForm');
    form.reset();
    document.getElementById('partnerAlert').innerHTML = '';

    if (id) {
        const p = PARTNERS_DATA.find(x => x.id == id);
        if (!p) return;
        document.getElementById('partnerModalTitle').textContent = 'Edit Partner';
        document.getElementById('partnerSubmitBtn').textContent  = 'Update Partner';
        document.getElementById('partnerId').value      = p.id;
        document.getElementById('partnerName').value    = p.name;
        document.getElementById('partnerPhone').value   = p.phone || '';
        document.getElementById('partnerType').value    = p.type;
        document.getElementById('partnerShare').value   = p.share_unit;
        document.getElementById('partnerStatus').value  = p.status;
        document.getElementById('partnerAddress').value = p.address || '';
        document.getElementById('partnerNotes').value   = p.notes || '';
    } else {
        document.getElementById('partnerModalTitle').textContent = 'Add Partner';
        document.getElementById('partnerSubmitBtn').textContent  = 'Save Partner';
        document.getElementById('partnerId').value = '';
    }
    modal.show();
}

async function submitPartnerForm(e) {
    e.preventDefault();
    const data   = formData(e.target);
    const isEdit = !!data.id;
    const url    = isEdit ? '" . APP_URL . "/ajax/partners.php?action=edit' : '" . APP_URL . "/ajax/partners.php?action=add';
    const btn    = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    const res    = await apiPost(url, data);
    btn.disabled = false;
    if (res.success) {
        bootstrap.Modal.getInstance('#partnerModal').hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('partnerAlert').innerHTML = '<div class=\"alert alert-danger py-2 mt-2\">' + res.message + '</div>';
    }
}

async function deletePartner(id) {
    if (!confirmAction('Delete this partner? All advances will also be deleted.')) return;
    const res = await apiPost('" . APP_URL . "/ajax/partners.php?action=delete', { id });
    if (res.success) { showToast(res.message, 'success'); setTimeout(reloadPage, 500); }
    else showToast(res.message, 'danger');
}
</script>";

include __DIR__ . '/includes/layout_footer.php';
?>
