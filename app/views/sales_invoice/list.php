<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Invoice Keluar</h4>
        <small class="text-muted">Invoice HME ke client</small>
    </div>
    <?php if (can('sales_invoice', 'create')): ?>
        <a href="<?= BASE_URL ?>/index.php?module=sales_invoice&action=create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Invoice
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="sales_invoice">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Cari (No. Invoice / Client)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Client</label>
                <select name="client_id" class="form-select form-select-sm">
                    <option value="">Semua Client</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (string) $filters['client_id'] === (string) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['client_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Jenis Invoice</label>
                <select name="invoice_type" class="form-select form-select-sm">
                    <option value="">Semua Jenis</option>
                    <option value="project" <?= $filters['invoice_type'] === 'project' ? 'selected' : '' ?>>Project</option>
                    <option value="lampu" <?= $filters['invoice_type'] === 'lampu' ? 'selected' : '' ?>>Lampu</option>
                </select>
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
                <label class="form-label small text-muted mb-1">Status Tagih</label>
                <select name="billing_status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="belum_tertagih" <?= $filters['billing_status'] === 'belum_tertagih' ? 'selected' : '' ?>>Belum Tertagih</option>
                    <option value="tertagih" <?= $filters['billing_status'] === 'tertagih' ? 'selected' : '' ?>>Sudah Tertagih</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/index.php?module=sales_invoice" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 no-print">
    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="siSelectAll">
        <label class="form-check-label small" for="siSelectAll">Centang Semua</label>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-dark" id="siPrintSelectedBtn" disabled>
            <i class="bi bi-printer"></i> Cetak Terpilih
        </button>
        <a href="<?= BASE_URL ?>/index.php?module=sales_invoice&action=print&<?= e(http_build_query(array_filter($filters))) ?>"
           target="_blank" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-printer-fill"></i> Cetak Semua
        </a>
        <?php if (can('collection_receipt', 'create')): ?>
            <button type="button" class="btn btn-sm btn-outline-primary" id="siMakeReceiptBtn" disabled>
                <i class="bi bi-journal-check"></i> Buat Pembayaran
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="no-print" style="width: 36px;"></th>
                        <th>No. Invoice</th>
                        <th>Jenis</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Tanggal</th>
                        <th class="text-center">DP</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status Tagih</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="10" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-cash-stack empty-icon"></i>
                                <div class="empty-title">Belum ada Invoice Keluar</div>
                                <div class="empty-desc">Buat invoice pertama untuk menagih client.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td class="no-print">
                                <input type="checkbox" class="form-check-input si-row-check" value="<?= (int) $inv['id'] ?>">
                            </td>
                            <td class="fw-semibold"><?= e($inv['invoice_number']) ?></td>
                            <td>
                                <span class="badge <?= $inv['invoice_type'] === 'lampu' ? 'text-bg-warning' : 'text-bg-info' ?>">
                                    <?= $inv['invoice_type'] === 'lampu' ? 'Lampu' : 'Project' ?>
                                </span>
                            </td>
                            <td><?= e($inv['client_name']) ?></td>
                            <td><?= e($inv['project_name'] ?? '-') ?></td>
                            <td><?= formatTanggal($inv['invoice_date']) ?></td>
                            <td class="text-center"><?= formatPercent($inv['dp_percentage']) ?>%</td>
                            <td class="text-end"><?= formatRupiah($inv['total_amount']) ?></td>
                            <td class="text-center">
                                <?php if ((int) $inv['is_tertagih'] === 1): ?>
                                    <span class="badge text-bg-success">Sudah Tertagih</span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">Belum Tertagih</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center no-print">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=sales_invoice&action=detail&id=<?= (int) $inv['id'] ?>">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=sales_invoice&action=print&ids=<?= (int) $inv['id'] ?>" target="_blank">
                                                <i class="bi bi-printer"></i> Cetak
                                            </a>
                                        </li>
                                        <?php if (can('sales_invoice', 'edit')): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=sales_invoice&action=edit&id=<?= (int) $inv['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <?php if (can('sales_invoice', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=sales_invoice&action=delete"
                                                      class="js-confirm-delete" data-message="Hapus invoice <?= e($inv['invoice_number']) ?>?">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
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
    var printBtn = document.getElementById('siPrintSelectedBtn');
    var receiptBtn = document.getElementById('siMakeReceiptBtn');

    function refreshPrintBtn() {
        var anyChecked = document.querySelector('.si-row-check:checked') !== null;
        printBtn.disabled = !anyChecked;
        if (receiptBtn) receiptBtn.disabled = !anyChecked;
    }

    // Reuse helper 2-arah yang sudah diperbaiki (lihat checkbox-select-all.js) --
    // jangan tulis listener Select All baru.
    wireSelectAllCheckbox('#siSelectAll', '.si-row-check', refreshPrintBtn);

    printBtn.addEventListener('click', function () {
        var ids = Array.prototype.filter.call(document.querySelectorAll('.si-row-check'), function (c) { return c.checked; })
            .map(function (c) { return c.value; });
        if (ids.length === 0) return;
        window.open('<?= BASE_URL ?>/index.php?module=sales_invoice&action=print&ids=' + ids.join(','), '_blank');
    });

    if (receiptBtn) {
        receiptBtn.addEventListener('click', function () {
            var ids = Array.prototype.filter.call(document.querySelectorAll('.si-row-check'), function (c) { return c.checked; })
                .map(function (c) { return c.value; });
            if (ids.length === 0) return;
            window.location.href = '<?= BASE_URL ?>/index.php?module=collection_receipt&action=select&ids=' + ids.join(',');
        });
    }

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
