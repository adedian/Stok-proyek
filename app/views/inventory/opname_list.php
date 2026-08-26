<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Stok Opname</h4>
        <small class="text-muted">Perhitungan fisik stok vs sistem</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?module=inventory" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kartu Stok
        </a>
        <?php if (hasRole([ROLE_SUPER_ADMIN, ROLE_GUDANG])): ?>
            <a href="<?= BASE_URL ?>/index.php?module=inventory&action=opnameCreate" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah Opname
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="inventory">
            <input type="hidden" name="action" value="opnameIndex">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Kategori Stok</label>
                <select name="stock_scope" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($scopeLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['stock_scope'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
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
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/index.php?module=inventory&action=opnameIndex" class="btn btn-sm btn-outline-secondary">
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
                        <th>No. Opname</th>
                        <th>Kategori</th>
                        <th>Project</th>
                        <th>Tanggal</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($opnames)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-clipboard-check empty-icon"></i>
                                <div class="empty-title">Belum ada stok opname</div>
                                <?php if (hasRole([ROLE_SUPER_ADMIN, ROLE_GUDANG])): ?>
                                    <div class="empty-desc">Lakukan perhitungan fisik stok untuk membandingkan dengan catatan sistem.</div>
                                    <a href="<?= BASE_URL ?>/index.php?module=inventory&action=opnameCreate" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Tambah Opname
                                    </a>
                                <?php else: ?>
                                    <div class="empty-desc mb-0">Belum ada perhitungan fisik stok yang tercatat.</div>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($opnames as $o): ?>
                        <tr>
                            <td><?= e($o['opname_number']) ?></td>
                            <td><span class="badge bg-<?= $o['stock_scope'] === 'kantor' ? 'info text-dark' : 'primary' ?>"><?= e($scopeLabels[$o['stock_scope']] ?? $o['stock_scope']) ?></span></td>
                            <td><?= e($o['project_name'] ?? '-') ?></td>
                            <td><?= formatTanggal($o['opname_date']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $statusBadgeClass[$o['status']] ?>"><?= e($statusLabels[$o['status']]) ?></span>
                            </td>
                            <td class="text-center">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=inventory&action=opnameDetail&id=<?= (int) $o['id'] ?>">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        </li>
                                        <?php if ($o['status'] === 'draft' && hasRole([ROLE_SUPER_ADMIN, ROLE_GUDANG])): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=inventory&action=opnameDelete"
                                                      class="js-confirm-delete" data-message="Hapus data opname draft <?= e($o['opname_number']) ?>? Seluruh item hitung fisiknya juga akan terhapus.">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            </li>
                                        <?php elseif ($o['status'] === 'completed' && hasRole([ROLE_SUPER_ADMIN])): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=inventory&action=opnameDelete"
                                                      class="js-confirm-delete" data-message="Hapus opname <?= e($o['opname_number']) ?> yang SUDAH SELESAI? Penyesuaian stok yang sudah diterapkan ke Stok Barang akan DIBATALKAN (dicatat sebagai transaksi pembalik). Tindakan ini tidak bisa dibatalkan.">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
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
