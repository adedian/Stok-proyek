<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Stok Barang</h4>
        <small class="text-muted">Kartu stok realtime per project</small>
    </div>
    <a href="<?= BASE_URL ?>/inventory/opnameIndex" class="btn btn-outline-primary">
        <i class="bi bi-clipboard-check"></i> Stok Opname
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/inventory" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Cari Barang</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-2">
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
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Kategori Stok</label>
                <select name="stock_scope" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="proyek" <?= $filters['stock_scope'] === 'proyek' ? 'selected' : '' ?>>Stok Proyek</option>
                    <option value="kantor" <?= $filters['stock_scope'] === 'kantor' ? 'selected' : '' ?>>Stok Kantor</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Status Stok</label>
                <select name="stock_filter" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="low" <?= $filters['stock_filter'] === 'low' ? 'selected' : '' ?>>Stok Minimum</option>
                    <option value="empty" <?= $filters['stock_filter'] === 'empty' ? 'selected' : '' ?>>Stok Habis</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/inventory" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
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
                        <th>Barang</th>
                        <th>Project</th>
                        <th class="text-end">Stok Tersedia</th>
                        <th class="text-end">Stok Minimum</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-clipboard-data empty-icon"></i>
                                <div class="empty-title">Belum ada data stok</div>
                                <div class="empty-desc mb-0">Stok akan muncul otomatis setelah ada penerimaan barang.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                            $qty = (float) $item['qty_available'];
                            $min = (float) $item['min_stock'];
                            if ($qty <= 0) {
                                $badge = ['bg-danger', 'Habis'];
                            } elseif ($qty <= $min) {
                                $badge = ['bg-warning text-dark', 'Minimum'];
                            } else {
                                $badge = ['bg-success', 'Aman'];
                            }
                        ?>
                        <tr>
                            <td><?= e($item['item_name']) ?></td>
                            <td>
                                <?php if ($item['stock_scope'] === 'kantor'): ?>
                                    <span class="badge bg-secondary">Kantor</span>
                                <?php else: ?>
                                    <?= e($item['project_name'] ?? '-') ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format($qty, 2, ',', '.') ?> <?= e($item['unit']) ?></td>
                            <td class="text-end"><?= number_format($min, 2, ',', '.') ?> <?= e($item['unit']) ?></td>
                            <td class="text-center"><span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                            <td class="text-center">
                                <?php if (can('inventory', 'delete_stock')): ?>
                                    <div class="dropdown row-actions">
                                        <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/inventory/history/<?= (int) $item['id'] ?>">
                                                    <i class="bi bi-clock-history"></i> Mutasi
                                                </a>
                                            </li>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=inventory&action=deleteItem"
                                                      class="js-confirm-delete"
                                                      data-message="Hapus baris stok '<?= e($item['item_name']) ?>' (<?= e($item['project_name'] ?? 'Kantor') ?>)? Sisa stok saat ini <?= number_format($qty, 2, ',', '.') ?> <?= e($item['unit']) ?> akan otomatis disesuaikan ke 0 dan dicatat di kartu stok.">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/inventory/history/<?= (int) $item['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Kartu Stok / Mutasi">
                                        <i class="bi bi-clock-history"></i> Mutasi
                                    </a>
                                <?php endif; ?>
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
