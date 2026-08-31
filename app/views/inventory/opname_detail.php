<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">
            <?= e($opname['opname_number']) ?>
            <span class="badge bg-<?= $statusBadgeClass[$opname['status']] ?> ms-2"><?= e($statusLabels[$opname['status']]) ?></span>
        </h4>
        <small class="text-muted">
            <span class="badge bg-<?= $opname['stock_scope'] === 'kantor' ? 'info text-dark' : 'primary' ?>"><?= e($scopeLabels[$opname['stock_scope']] ?? $opname['stock_scope']) ?></span>
            <?= e($opname['project_name'] ?? 'Tanpa Project (Kantor)') ?> &middot; <?= formatTanggal($opname['opname_date']) ?>
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/inventory/opnameIndex" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <?php if ($opname['status'] === 'draft' && can('inventory', 'complete')): ?>
            <form method="POST" action="<?= BASE_URL ?>/index.php?module=inventory&action=opnameComplete" class="d-inline"
                  onsubmit="return confirm('Selesaikan opname ini? Stok sistem akan disesuaikan otomatis mengikuti hasil hitung fisik dan TIDAK bisa dibatalkan.');">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $opname['id'] ?>">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2-circle"></i> Selesaikan & Sesuaikan Stok
                </button>
            </form>
            <?php if (can('inventory', 'delete')): ?>
            <form method="POST" action="<?= BASE_URL ?>/index.php?module=inventory&action=opnameDelete" class="d-inline"
                  onsubmit="return confirm('Hapus data opname draft ini?');">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $opname['id'] ?>">
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
            <?php endif; ?>
        <?php elseif ($opname['status'] === 'completed' && can('inventory', 'delete')): ?>
            <form method="POST" action="<?= BASE_URL ?>/index.php?module=inventory&action=opnameDelete" class="d-inline"
                  onsubmit="return confirm('Hapus opname yang SUDAH SELESAI ini? Penyesuaian stok yang sudah diterapkan ke Stok Barang akan DIBATALKAN (dicatat sebagai transaksi pembalik). Tindakan ini tidak bisa dibatalkan.');">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $opname['id'] ?>">
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash"></i> Hapus
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($opname['notes'])): ?>
    <div class="alert alert-light border mb-3"><?= e($opname['notes']) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Barang</th>
                        <th class="text-end">Stok Sistem</th>
                        <th class="text-end">Stok Fisik</th>
                        <th class="text-end">Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada item.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <?php $diff = (float) $item['difference']; ?>
                        <tr>
                            <td>
                                <?= e($item['item_name']) ?> <span class="text-muted small">(<?= e($item['unit']) ?>)</span>
                                <?php if (!empty($item['inventory_deleted_at'])): ?>
                                    <span class="badge bg-secondary" title="Barang ini sudah dihapus dari Stok Barang setelah opname ini dibuat -- histori tetap dipertahankan.">sudah dihapus</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format((float) $item['qty_system'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float) $item['qty_actual'], 2, ',', '.') ?></td>
                            <td class="text-end">
                                <?php if ($diff == 0): ?>
                                    <span class="text-muted">0</span>
                                <?php elseif ($diff > 0): ?>
                                    <span class="badge bg-info text-dark">+<?= number_format($diff, 2, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><?= number_format($diff, 2, ',', '.') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
