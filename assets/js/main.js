/* Pond Manager — Main JS */

// ── Sidebar toggle ──────────────────────────────────────────────
(function () {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay && overlay.classList.toggle('show');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }
})();

// ── Toast notifications ──────────────────────────────────────────
function showToast(message, type = 'success') {
    const area = document.getElementById('toastArea');
    if (!area) return;
    const id = 'toast_' + Date.now();
    const icons = { success: 'fa-check-circle', danger: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const html = `<div id="${id}" class="alert alert-${type} alert-dismissible fade show d-flex align-items-center gap-2 mb-2 shadow-sm" role="alert" style="min-width:260px">
        <i class="fas ${icons[type] || icons.info}"></i>
        <span>${message}</span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>`;
    area.insertAdjacentHTML('beforeend', html);
    setTimeout(() => { const el = document.getElementById(id); if (el) el.remove(); }, 4000);
}

// ── AJAX helper (FormData — matches PHP $_POST handlers) ──────────
async function apiPost(url, data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
    const res = await fetch(url, { method: 'POST', body: fd });
    return res.json();
}

// ── Form → plain object ──────────────────────────────────────────
function formData(form) {
    const obj = {};
    new FormData(form).forEach((v, k) => { obj[k] = v; });
    return obj;
}

// ── Confirm dialog ───────────────────────────────────────────────
function confirmAction(msg) {
    return window.confirm(msg || 'Are you sure?');
}

// ── Reload ───────────────────────────────────────────────────────
function reloadPage() { window.location.reload(); }
