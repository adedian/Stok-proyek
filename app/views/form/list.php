<?php
$typeBadge = [
    'pengiriman'    => 'bg-primary-subtle text-primary-emphasis',
    'kwitansi'      => 'bg-success-subtle text-success-emphasis',
    'order_form'    => 'bg-warning-subtle text-warning-emphasis',
    'bon_sementara' => 'bg-info-subtle text-info-emphasis',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Form</h4>
        <small class="text-muted">Form Pengiriman Dokumen, Kwitansi, Order Form, dan Pengajuan Bon Sementara</small>
    </div>
    <?php if (can('form', 'create')): ?>
        <a href="<?= BASE_URL ?>/form/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Form
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/form" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Jenis Form</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($types as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $filters['type'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Cari</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="ketik nama atau isi form...">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 no-print">
    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="fmSelectAll">
        <label class="form-check-label small" for="fmSelectAll">Centang Semua</label>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-dark" id="fmPrintSelectedBtn" disabled>
            <i class="bi bi-printer"></i> Cetak Terpilih
        </button>
        <a href="<?= BASE_URL ?>/index.php?module=form&action=printMany&<?= e(http_build_query(array_filter($filters))) ?>"
           target="_blank" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-printer-fill"></i> Cetak Semua
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="no-print" style="width: 36px;"></th>
                        <th>Jenis</th>
                        <th>Ringkasan</th>
                        <th>Dibuat</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-journal-text empty-icon"></i>
                                <div class="empty-title">Belum ada Form</div>
                                <div class="empty-desc">Klik "Tambah Form" lalu pilih jenis form yang dibutuhkan.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="no-print"><input type="checkbox" class="form-check-input fm-row-check" value="<?= (int) $r['id'] ?>"></td>
                            <td data-label="Jenis"><span class="badge <?= e($typeBadge[$r['form_type']] ?? 'bg-secondary-subtle text-secondary-emphasis') ?>"><?= e($types[$r['form_type']] ?? $r['form_type']) ?></span></td>
                            <td data-label="Ringkasan" class="fw-semibold"><?= e($r['summary']) ?></td>
                            <td data-label="Dibuat"><?= formatTanggal($r['created_at']) ?></td>
                            <td class="text-center no-print">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/form/print/<?= (int) $r['id'] ?>" target="_blank">
                                                <i class="bi bi-printer"></i> Cetak
                                            </a>
                                        </li>
                                        <?php if (can('form', 'edit')): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/form/edit/<?= (int) $r['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <?php if (can('form', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=form&action=delete"
                                                      class="js-confirm-delete" data-message="Hapus form &quot;<?= e($r['summary']) ?>&quot;?">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/views/partials/pagination.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var printBtn = document.getElementById('fmPrintSelectedBtn');

    function refreshPrintBtn() {
        printBtn.disabled = document.querySelector('.fm-row-check:checked') === null;
    }

    wireSelectAllCheckbox('#fmSelectAll', '.fm-row-check', refreshPrintBtn);

    printBtn.addEventListener('click', function () {
        var ids = Array.prototype.filter.call(document.querySelectorAll('.fm-row-check'), function (c) { return c.checked; })
            .map(function (c) { return c.value; });
        if (ids.length === 0) return;
        window.open('<?= BASE_URL ?>/index.php?module=form&action=printMany&ids=' + ids.join(','), '_blank');
    });

    document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, hapus').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });

    refreshPrintBtn();
});
</script>
