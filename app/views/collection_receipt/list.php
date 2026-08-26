<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Tanda Terima</h4>
        <small class="text-muted">Tanda terima penagihan, dibuat dari Invoice Keluar terpilih</small>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="collection_receipt">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (No. Tanda Terima / Client)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
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
        <input type="checkbox" class="form-check-input" id="crSelectAll">
        <label class="form-check-label small" for="crSelectAll">Centang Semua</label>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-dark" id="crPrintSelectedBtn" disabled>
            <i class="bi bi-printer"></i> Cetak Terpilih
        </button>
        <a href="<?= BASE_URL ?>/index.php?module=collection_receipt&action=printMany&<?= e(http_build_query(array_filter($filters))) ?>"
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
                        <th>No. Tanda Terima</th>
                        <th>Client</th>
                        <th>Tanggal</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($receipts)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-journal-check empty-icon"></i>
                                <div class="empty-title">Belum ada Tanda Terima</div>
                                <div class="empty-desc">Buat Tanda Terima dari Invoice Keluar yang sudah dicentang.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($receipts as $r): ?>
                        <tr>
                            <td class="no-print"><input type="checkbox" class="form-check-input cr-row-check" value="<?= (int) $r['id'] ?>"></td>
                            <td class="fw-semibold"><?= e($r['receipt_number']) ?></td>
                            <td><?= e($r['client_name']) ?></td>
                            <td><?= formatTanggal($r['receipt_date']) ?></td>
                            <td class="text-end"><?= formatRupiah($r['grand_total']) ?></td>
                            <td class="text-center no-print">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=collection_receipt&action=print&id=<?= (int) $r['id'] ?>" target="_blank">
                                                <i class="bi bi-printer"></i> Cetak
                                            </a>
                                        </li>
                                        <?php if (can('collection_receipt', 'edit')): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=collection_receipt&action=edit&id=<?= (int) $r['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <?php if (can('collection_receipt', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=collection_receipt&action=delete"
                                                      class="js-confirm-delete" data-message="Hapus Tanda Terima <?= e($r['receipt_number']) ?>?">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    var printBtn = document.getElementById('crPrintSelectedBtn');

    function refreshPrintBtn() {
        printBtn.disabled = document.querySelector('.cr-row-check:checked') === null;
    }

    wireSelectAllCheckbox('#crSelectAll', '.cr-row-check', refreshPrintBtn);

    printBtn.addEventListener('click', function () {
        var ids = Array.prototype.filter.call(document.querySelectorAll('.cr-row-check'), function (c) { return c.checked; })
            .map(function (c) { return c.value; });
        if (ids.length === 0) return;
        window.open('<?= BASE_URL ?>/index.php?module=collection_receipt&action=printMany&ids=' + ids.join(','), '_blank');
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
