<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
requireLogin();

$pageTitle = 'Sales';
$pdo = db();

$search    = trim((string)($_GET['search'] ?? ''));
$pondIdRaw = trim((string)($_GET['pond_id'] ?? ''));
$pondId    = ($pondIdRaw !== '' && $pondIdRaw !== 'common') ? (int)$pondIdRaw : null;

$sql = "
    SELECT ps.id, ps.pond_id, ps.sale_scope, ps.entry_type, ps.sale_date,
           ps.fish_type, ps.quantity_kg, ps.unit_price, ps.gross_amount,
           ps.sale_expense, ps.net_amount, ps.buyer_name, ps.buyer_phone,
           ps.notes, ps.created_at, p.pond_name
    FROM pond_sales ps
    LEFT JOIN ponds p ON p.id = ps.pond_id
";
$where  = [];
$params = [];

if ($search !== '') {
    $where[]     = "(ps.fish_type LIKE :s OR ps.buyer_name LIKE :s OR ps.buyer_phone LIKE :s OR p.pond_name LIKE :s)";
    $params['s'] = '%' . $search . '%';
}

if ($pondIdRaw === 'common') {
    $where[] = "ps.sale_scope = 'common'";
} elseif ($pondId !== null && $pondId > 0) {
    $where[]       = "ps.pond_id = :pid";
    $params['pid'] = $pondId;
}

if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY ps.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$ponds      = $pdo->query("SELECT id, pond_name FROM ponds ORDER BY pond_name ASC")->fetchAll();
$totalGross = array_sum(array_column($sales, 'gross_amount'));
$totalSalEx = array_sum(array_column($sales, 'sale_expense'));
$totalNet   = array_sum(array_column($sales, 'net_amount'));

$flash      = getFlash();
$salesJson  = json_encode($sales);
$pondsJson  = json_encode($ponds);

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div>
        <div class="section-title">Sales</div>
        <p class="section-text">Manage pond-wise and common sales.</p>
    </div>
    <button class="btn btn-primary" onclick="openSaleModal(<?= $pondId ? $pondId : 'null' ?>)">+ Add Sale</button>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="stat-title">Total Gross Sale</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalGross) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-title">Total Sale Expense</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalSalEx) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-title">Total Net Sale</div><div class="stat-value" style="font-size:26px"><?= formatCurrency($totalNet) ?></div></div></div>
</div>

<!-- Search -->
<div class="card-soft mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label fw-semibold">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Fish type, buyer, pond..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Filter by Pond</label>
            <select name="pond_id" class="form-select">
                <option value="">All Sales</option>
                <option value="common" <?= $pondIdRaw === 'common' ? 'selected' : '' ?>>Common Sale</option>
                <?php foreach ($ponds as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $pondId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['pond_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="<?= APP_URL ?>/sales.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="card-soft">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Sales</h5>
        <span class="text-muted small">Total: <?= count($sales) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">SL</th>
                    <th>Date</th>
                    <th>Scope</th>
                    <th>Pond</th>
                    <th>Type</th>
                    <th>Fish</th>
                    <th>Qty KG</th>
                    <th>Unit Price</th>
                    <th>Gross</th>
                    <th>Sale Exp</th>
                    <th>Net</th>
                    <th>Buyer</th>
                    <th style="width:150px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sales): ?>
                    <?php foreach ($sales as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['sale_date']) ?></td>
                        <td><?= $r['sale_scope'] === 'common' ? 'Common' : 'Pond' ?></td>
                        <td><?= e($r['pond_name'] ?? 'Common') ?></td>
                        <td><?= $r['entry_type'] === 'direct' ? 'Direct' : 'Measured' ?></td>
                        <td><?= e($r['fish_type']) ?></td>
                        <td><?= $r['entry_type'] === 'direct' ? '—' : number_format((float)$r['quantity_kg'], 2) ?></td>
                        <td><?= $r['entry_type'] === 'direct' ? '—' : formatCurrency((float)$r['unit_price']) ?></td>
                        <td><?= formatCurrency((float)$r['gross_amount']) ?></td>
                        <td><?= formatCurrency((float)$r['sale_expense']) ?></td>
                        <td><?= formatCurrency((float)$r['net_amount']) ?></td>
                        <td><?= e($r['buyer_name'] ?? '') ?></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-success" onclick="openSaleModal(null, <?= (int)$r['id'] ?>)">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSale(<?= (int)$r['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="13" class="text-center py-4 text-muted">No sales found.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($sales): ?>
            <tfoot class="table-light">
                <tr><th colspan="8" class="text-end">Totals</th><th><?= formatCurrency($totalGross) ?></th><th><?= formatCurrency($totalSalEx) ?></th><th><?= formatCurrency($totalNet) ?></th><th colspan="2"></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- ══ Sale Modal ══ -->
<div class="modal fade" id="saleModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="saleModalTitle">Add Sale</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="saleForm" onsubmit="submitSaleForm(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="saleId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Sale Scope <span class="text-danger">*</span></label>
                        <select name="sale_scope" id="saleScope" class="form-select" required onchange="toggleSaleFields()">
                            <option value="pond">Pond-wise Sale</option>
                            <option value="common">Common / Mixed Sale</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Entry Type <span class="text-danger">*</span></label>
                        <select name="entry_type" id="saleEntryType" class="form-select" required onchange="toggleSaleFields()">
                            <option value="measured">Measured (Qty × Unit Price)</option>
                            <option value="direct">Direct Amount</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pond</label>
                        <select name="pond_id" id="salePondId" class="form-select">
                            <option value="">Select Pond</option>
                            <?php foreach ($ponds as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= e($p['pond_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Common sale এ pond না দিলেও হবে।</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Sale Date <span class="text-danger">*</span></label>
                        <input type="date" name="sale_date" id="saleDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Fish Type <span class="text-danger">*</span></label>
                        <input type="text" name="fish_type" id="saleFishType" class="form-control" placeholder="e.g. Rui, Catla, Mixed" required>
                    </div>
                    <div class="col-md-6" id="qtyWrap">
                        <label class="form-label fw-semibold">Quantity (KG)</label>
                        <input type="number" step="0.01" min="0" name="quantity_kg" id="saleQty" class="form-control" placeholder="KG">
                    </div>
                    <div class="col-md-6" id="unitPriceWrap">
                        <label class="form-label fw-semibold">Unit Price</label>
                        <input type="number" step="0.01" min="0" name="unit_price" id="saleUnitPrice" class="form-control" placeholder="Per KG price">
                    </div>
                    <div class="col-md-6 d-none" id="directWrap">
                        <label class="form-label fw-semibold">Direct Sale Amount</label>
                        <input type="number" step="0.01" min="0" name="direct_amount" id="saleDirectAmt" class="form-control" placeholder="Total sale amount">
                        <small class="text-muted">নিলাম বা ওজন ছাড়া sale এ ব্যবহার করুন।</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Sale Expense</label>
                        <input type="number" step="0.01" min="0" name="sale_expense" id="saleSaleExp" class="form-control" value="0">
                        <small class="text-muted">Transport / market / labor খরচ।</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Buyer Name</label>
                        <input type="text" name="buyer_name" id="saleBuyerName" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Buyer Phone</label>
                        <input type="text" name="buyer_phone" id="saleBuyerPhone" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" id="saleNotes" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="saleAlert"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="saleSubmitBtn">Save Sale</button>
            </div>
        </form>
    </div></div>
</div>

<?php
$extraJs = "<script>
const SALES_DATA = $salesJson;
const PONDS_DATA = $pondsJson;

function toggleSaleFields() {
    const type  = document.getElementById('saleEntryType').value;
    const isDirect = type === 'direct';
    document.getElementById('qtyWrap').classList.toggle('d-none', isDirect);
    document.getElementById('unitPriceWrap').classList.toggle('d-none', isDirect);
    document.getElementById('directWrap').classList.toggle('d-none', !isDirect);
}

function openSaleModal(defaultPondId = null, id = null) {
    const modal = new bootstrap.Modal('#saleModal');
    const form  = document.getElementById('saleForm');
    form.reset();
    document.getElementById('saleAlert').innerHTML = '';
    document.getElementById('saleDate').value = new Date().toISOString().slice(0,10);
    document.getElementById('saleSaleExp').value = '0';
    toggleSaleFields();

    if (id) {
        const r = SALES_DATA.find(x => x.id == id);
        if (!r) return;
        document.getElementById('saleModalTitle').textContent = 'Edit Sale';
        document.getElementById('saleSubmitBtn').textContent  = 'Update Sale';
        document.getElementById('saleId').value          = r.id;
        document.getElementById('saleScope').value        = r.sale_scope;
        document.getElementById('saleEntryType').value    = r.entry_type;
        document.getElementById('salePondId').value       = r.pond_id || '';
        document.getElementById('saleDate').value         = r.sale_date;
        document.getElementById('saleFishType').value     = r.fish_type;
        document.getElementById('saleQty').value          = r.quantity_kg || '';
        document.getElementById('saleUnitPrice').value    = r.unit_price || '';
        document.getElementById('saleDirectAmt').value    = r.entry_type === 'direct' ? r.gross_amount : '';
        document.getElementById('saleSaleExp').value      = r.sale_expense || '0';
        document.getElementById('saleBuyerName').value    = r.buyer_name || '';
        document.getElementById('saleBuyerPhone').value   = r.buyer_phone || '';
        document.getElementById('saleNotes').value        = r.notes || '';
        toggleSaleFields();
    } else {
        document.getElementById('saleModalTitle').textContent = 'Add Sale';
        document.getElementById('saleSubmitBtn').textContent  = 'Save Sale';
        document.getElementById('saleId').value = '';
        if (defaultPondId) document.getElementById('salePondId').value = defaultPondId;
    }
    modal.show();
}

async function submitSaleForm(e) {
    e.preventDefault();
    const data   = formData(e.target);
    const isEdit = !!data.id;
    const url    = isEdit ? '" . APP_URL . "/ajax/sales.php?action=edit' : '" . APP_URL . "/ajax/sales.php?action=add';
    const btn    = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    const res    = await apiPost(url, data);
    btn.disabled = false;
    if (res.success) {
        bootstrap.Modal.getInstance('#saleModal').hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('saleAlert').innerHTML = '<div class=\"alert alert-danger py-2 mt-2\">' + res.message + '</div>';
    }
}

async function deleteSale(id) {
    if (!confirmAction('Delete this sale?')) return;
    const res = await apiPost('" . APP_URL . "/ajax/sales.php?action=delete', { id });
    if (res.success) { showToast(res.message, 'success'); setTimeout(reloadPage, 500); }
    else showToast(res.message, 'danger');
}
</script>";

include __DIR__ . '/includes/layout_footer.php';
?>
