<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Purchase Order</h4>
        <small class="text-muted">Daftar seluruh Purchase Order</small>
    </div>
    <?php if (can('purchase_order', 'create')): ?>
    <a href="<?= BASE_URL ?>/purchase_order/create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Tambah PO
    </a>
    <?php endif; ?>
</div>

<?php if (hasRole([ROLE_SUPER_ADMIN])): ?>
    <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rangeDeleteModal">
            <i class="bi bi-calendar-x"></i> Hapus per Rentang Tanggal
        </button>
    </div>
    <?php $rangeDeleteAction = 'purchase_order/rangeDelete'; $rangeDeleteLabel = 'Purchase Order';
          require ROOT_PATH . '/app/views/partials/range_delete_modal.php'; ?>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/purchase_order" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (No. PO / Supplier)</label>
                <input type="text" name="keyword" class="form-control form-control-sm"
                       value="<?= e($filters['keyword']) ?>" placeholder="PO/2026/08/0001 atau nama supplier">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Project</label>
                <select name="project_id" class="form-select form-select-sm">
                    <option value="">Semua Project</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) $filters['project_id'] === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/purchase_order" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 no-print">
    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="poSelectAll">
        <label class="form-check-label small" for="poSelectAll">Select All</label>
    </div>
    <button type="button" class="btn btn-sm btn-outline-dark" id="poPrintSelectedBtn" disabled>
        <i class="bi bi-printer"></i> Cetak Data Terpilih
    </button>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="no-print" style="width: 36px;"></th>
                        <th>No</th>
                        <th>No. PO</th>
                        <th>Kode Sup</th>
                        <th>Supplier</th>
                        <th>Pembuat PO</th>
                        <th>Project</th>
                        <th>Tanggal</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchaseOrders)): ?>
                        <tr>
                            <td colspan="11" class="p-0">
                                <div class="empty-state">
                                    <i class="bi bi-cart-x empty-icon"></i>
                                    <div class="empty-title">Belum ada Purchase Order</div>
                                    <div class="empty-desc">Buat PO pertama untuk mulai memesan barang ke supplier.</div>
                                    <?php if (can('purchase_order', 'create')): ?>
                                    <a href="<?= BASE_URL ?>/purchase_order/create" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Tambah PO
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($purchaseOrders as $i => $po): ?>
                        <tr>
                            <td class="no-print">
                                <input type="checkbox" class="form-check-input po-row-check" name="ids[]" value="<?= (int) $po['id'] ?>">
                            </td>
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= e($po['po_number']) ?></td>
                            <td><?= e($po['supplier_code']) ?></td>
                            <td><?= e($po['supplier_name']) ?></td>
                            <td><?= e($po['pembuat_po'] ?? '-') ?></td>
                            <td><?= e($po['project_name']) ?></td>
                            <td><?= formatTanggal($po['po_date']) ?></td>
                            <td class="text-end"><?= formatRupiah($po['total_amount']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= e($statusBadgeClass[$po['status']] ?? 'secondary') ?>">
                                    <?= e($statusLabels[$po['status']] ?? $po['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/purchase_order/detail/<?= (int) $po['id'] ?>">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        </li>
                                        <?php if (isPeriodClosed('purchase_order', $po['po_date'])): ?>
                                        <li><span class="dropdown-item-text text-muted small"><i class="bi bi-lock-fill"></i> Periode ditutup</span></li>
                                        <?php else: ?>
                                        <?php if (can('purchase_order', 'edit')): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/purchase_order/edit/<?= (int) $po['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (can('purchase_order', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=purchase_order&action=delete"
                                                      onsubmit="return confirm('Yakin ingin menghapus PO <?= e($po['po_number']) ?>?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $po['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
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
    var printBtn = document.getElementById('poPrintSelectedBtn');

    function refreshPrintBtn() {
        var anyChecked = document.querySelector('.po-row-check:checked') !== null;
        printBtn.disabled = !anyChecked;
    }

    // Sinkronisasi dua arah Select All <-> baris individual (lihat checkbox-select-all.js
    // untuk root cause bug lama: hanya menangani 1 arah dari 2 arah yang dibutuhkan).
    wireSelectAllCheckbox('#poSelectAll', '.po-row-check', refreshPrintBtn);

    printBtn.addEventListener('click', function () {
        var ids = Array.prototype.filter.call(document.querySelectorAll('.po-row-check'), function (c) { return c.checked; })
            .map(function (c) { return c.value; });
        if (ids.length === 0) return;
        var url = '<?= BASE_URL ?>/index.php?module=purchase_order&action=print&ids=' + ids.join(',');
        window.open(url, '_blank');
    });

    refreshPrintBtn();
});
</script>
