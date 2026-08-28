<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Pembelian Offline</h4>
        <small class="text-muted">Pembelian manual di luar Purchase Order</small>
    </div>
    <a href="<?= BASE_URL ?>/index.php?module=offline_purchase&action=create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Tambah Pembelian
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="offline_purchase">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (No. Pembelian / Barang / Supplier)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
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
                    <option value="">Semua Status</option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/index.php?module=offline_purchase" class="btn btn-sm btn-outline-secondary">
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
                        <th>No. Pembelian</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Project</th>
                        <th class="text-end">Jml Item</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Bukti</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr><td colspan="9" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-shop empty-icon"></i>
                                <div class="empty-title">Belum ada pembelian offline</div>
                                <div class="empty-desc">Catat pembelian barang di luar mekanisme Purchase Order di sini.</div>
                                <a href="<?= BASE_URL ?>/index.php?module=offline_purchase&action=create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tambah Pembelian
                                </a>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($purchases as $p): ?>
                        <tr>
                            <td class="fw-semibold">
                                <a href="<?= BASE_URL ?>/index.php?module=offline_purchase&action=detail&id=<?= (int) $p['id'] ?>">
                                    <?= e($p['purchase_number']) ?>
                                </a>
                            </td>
                            <td><?= formatTanggal($p['purchase_date']) ?></td>
                            <td><?= e($p['supplier_name']) ?></td>
                            <td><?= e($p['project_name']) ?></td>
                            <td class="text-end"><?= (int) $p['item_count'] ?></td>
                            <td class="text-end"><?= formatRupiah($p['total_amount']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= e($statusBadgeClass[$p['status']] ?? 'secondary') ?>">
                                    <?= e($statusLabels[$p['status']] ?? $p['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($p['proof_file'])): ?>
                                    <a href="<?= e(fileUrl($p['proof_file'])) ?>" target="_blank" title="Bukti pembelian">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($p['photo_file'])): ?>
                                    <a href="<?= BASE_URL ?>/<?= e($p['photo_file']) ?>" target="_blank" title="Foto barang">
                                        <i class="bi bi-image"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (empty($p['proof_file']) && empty($p['photo_file'])): ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=offline_purchase&action=detail&id=<?= (int) $p['id'] ?>">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=offline_purchase&action=edit&id=<?= (int) $p['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <?php if ($p['status'] === 'belum_diterima' && hasRole([ROLE_SUPER_ADMIN, ROLE_FINANCE])): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=offline_purchase&action=delete"
                                                      onsubmit="return confirm('Yakin ingin menghapus pembelian offline ini?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
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
