<?php
function itemSortLink(string $col, string $label, string $sort, string $dir): string
{
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $url = BASE_URL . '/index.php?module=item&sort=' . urlencode($col) . '&dir=' . $nextDir;
    return '<a href="' . e($url) . '" class="text-dark text-decoration-none">' . e($label) . sortIndicator($col, $sort, $dir) . '</a>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Barang</h4>
        <small class="text-muted">Katalog master barang</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?module=master_data" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Master Data
        </a>
        <a href="<?= BASE_URL ?>/index.php?module=item&action=create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Barang
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="item">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (Nama / Kode)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Jenis Stok</label>
                <select name="stock_type" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($stockTypeLabels as $st => $stLabel): ?>
                        <option value="<?= e($st) ?>" <?= ($filters['stock_type'] ?? '') === $st ? 'selected' : '' ?>><?= e($stLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Kategori</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (string) $filters['category_id'] === (string) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-1 no-print">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Filter">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            <div class="col-md-2 d-flex gap-2 no-print">
                <a href="<?= BASE_URL ?>/index.php?module=item&action=exportCsv&<?= e($baseQuery) ?>" class="btn btn-sm btn-outline-success w-100">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer"></i>
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
                        <th><?= itemSortLink('item_code', 'Kode', $sort, $dir) ?></th>
                        <th><?= itemSortLink('item_name', 'Nama Barang', $sort, $dir) ?></th>
                        <th>Jenis Stok</th>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th class="text-end">Tersedia</th>
                        <th class="text-end"><?= itemSortLink('min_stock', 'Stok Min', $sort, $dir) ?></th>
                        <th class="text-center"><?= itemSortLink('status', 'Status', $sort, $dir) ?></th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="8" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-box empty-icon"></i>
                                <div class="empty-title">Belum ada barang</div>
                                <div class="empty-desc">Tambahkan barang ke katalog master untuk dipakai di PO & stok.</div>
                                <a href="<?= BASE_URL ?>/index.php?module=item&action=create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tambah Barang
                                </a>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $i): ?>
                        <tr>
                            <td><?= e($i['item_code']) ?></td>
                            <td><?= e($i['item_name']) ?></td>
                            <td><span class="badge text-bg-light border"><?= e($stockTypeLabels[$i['stock_type']] ?? $i['stock_type']) ?></span></td>
                            <td><?= e($i['category_name'] ?? '-') ?></td>
                            <td><?= e($i['unit_name']) ?></td>
                            <?php
                                $available = (float) ($i['total_available'] ?? 0);
                                $minStock = (float) $i['min_stock'];
                                $availableClass = $available <= 0 ? 'text-danger' : ($available <= $minStock ? 'text-warning' : '');
                            ?>
                            <td class="text-end fw-semibold <?= $availableClass ?>">
                                <?= number_format($available, 2, ',', '.') ?>
                            </td>
                            <td class="text-end"><?= number_format($minStock, 2, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $i['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= $i['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td class="text-center no-print">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=item&action=edit&id=<?= (int) $i['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=item&action=delete"
                                                  class="js-confirm-delete" data-message="Hapus barang <?= e($i['item_name']) ?>?">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
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
