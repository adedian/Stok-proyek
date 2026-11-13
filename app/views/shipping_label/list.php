<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Label Pengiriman</h4>
        <small class="text-muted">Form alamat Kepada &amp; Pengirim, dicetak sebagai dua kotak</small>
    </div>
    <?php if (can('shipping_label', 'create')): ?>
        <a href="<?= BASE_URL ?>/shipping_label/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tambah Label
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/shipping_label" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (nama / alamat / kota)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="ketik nama, alamat, atau kota...">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-3">
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
        <input type="checkbox" class="form-check-input" id="slSelectAll">
        <label class="form-check-label small" for="slSelectAll">Centang Semua</label>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-dark" id="slPrintSelectedBtn" disabled>
            <i class="bi bi-printer"></i> Cetak Terpilih
        </button>
        <a href="<?= BASE_URL ?>/index.php?module=shipping_label&action=printMany&<?= e(http_build_query(array_filter($filters))) ?>"
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
                        <th>Kepada</th>
                        <th>Pengirim</th>
                        <th>Dibuat</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-envelope-paper empty-icon"></i>
                                <div class="empty-title">Belum ada Label Pengiriman</div>
                                <div class="empty-desc">Klik "Tambah Label" untuk mengisi alamat Kepada &amp; Pengirim.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="no-print"><input type="checkbox" class="form-check-input sl-row-check" value="<?= (int) $r['id'] ?>"></td>
                            <td>
                                <div class="fw-semibold"><?= e($r['to_name']) ?></div>
                                <div class="small text-muted"><?= e($r['to_address']) ?><?= !empty($r['to_city']) ? ', ' . e($r['to_city']) : '' ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($r['from_name']) ?></div>
                                <div class="small text-muted"><?= e($r['from_address']) ?><?= !empty($r['from_city']) ? ', ' . e($r['from_city']) : '' ?></div>
                            </td>
                            <td><?= formatTanggal($r['created_at']) ?></td>
                            <td class="text-center no-print">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/shipping_label/print/<?= (int) $r['id'] ?>" target="_blank">
                                                <i class="bi bi-printer"></i> Cetak
                                            </a>
                                        </li>
                                        <?php if (can('shipping_label', 'edit')): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/shipping_label/edit/<?= (int) $r['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <?php if (can('shipping_label', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=shipping_label&action=delete"
                                                      class="js-confirm-delete" data-message="Hapus Label Pengiriman untuk <?= e($r['to_name']) ?>?">
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
    var printBtn = document.getElementById('slPrintSelectedBtn');

    function refreshPrintBtn() {
        printBtn.disabled = document.querySelector('.sl-row-check:checked') === null;
    }

    wireSelectAllCheckbox('#slSelectAll', '.sl-row-check', refreshPrintBtn);

    printBtn.addEventListener('click', function () {
        var ids = Array.prototype.filter.call(document.querySelectorAll('.sl-row-check'), function (c) { return c.checked; })
            .map(function (c) { return c.value; });
        if (ids.length === 0) return;
        window.open('<?= BASE_URL ?>/index.php?module=shipping_label&action=printMany&ids=' + ids.join(','), '_blank');
    });

    document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, hapus').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });
});
</script>
