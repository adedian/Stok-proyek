<?php
function warehouseSortLink(string $col, string $label, string $sort, string $dir): string
{
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $url = route('warehouse', 'index', ['sort' => $col, 'dir' => $nextDir]);
    return '<a href="' . e($url) . '" class="text-dark text-decoration-none">' . e($label) . sortIndicator($col, $sort, $dir) . '</a>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Gudang</h4>
        <small class="text-muted">Master data gudang</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/master_data" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Master Data
        </a>
        <a href="<?= BASE_URL ?>/warehouse/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Gudang
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/warehouse" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Cari (Nama / Kode)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/warehouse" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
            <div class="col-md-2 no-print">
                <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= warehouseSortLink('warehouse_code', 'Kode', $sort, $dir) ?></th>
                        <th><?= warehouseSortLink('warehouse_name', 'Nama Gudang', $sort, $dir) ?></th>
                        <th>PIC</th>
                        <th>Telepon</th>
                        <th class="text-center"><?= warehouseSortLink('status', 'Status', $sort, $dir) ?></th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($warehouses)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-building empty-icon"></i>
                                <div class="empty-title">Belum ada gudang</div>
                                <div class="empty-desc">Tambahkan gudang untuk mengelola lokasi penyimpanan stok.</div>
                                <a href="<?= BASE_URL ?>/warehouse/create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tambah Gudang
                                </a>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($warehouses as $w): ?>
                        <tr>
                            <td><?= e($w['warehouse_code']) ?></td>
                            <td><?= e($w['warehouse_name']) ?></td>
                            <td><?= e($w['pic_name'] ?? '-') ?></td>
                            <td><?= e($w['phone'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $w['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= $w['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td class="text-center no-print">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/warehouse/edit/<?= (int) $w['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=warehouse&action=delete"
                                                  class="js-confirm-delete" data-message="Hapus gudang <?= e($w['warehouse_name']) ?>?">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </li>
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
