<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Kartu Stok - <?= e($item['item_name']) ?></h4>
        <small class="text-muted"><?= e($item['project_name']) ?> &middot; Satuan: <?= e($item['unit']) ?></small>
    </div>
    <a href="<?= BASE_URL ?>/index.php?module=inventory" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-4">
                <div class="text-muted small">Stok Tersedia</div>
                <div class="fs-4 fw-bold"><?= number_format((float) $item['qty_available'], 2, ',', '.') ?> <?= e($item['unit']) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Stok Minimum</div>
                <div class="fs-4 fw-bold"><?= number_format((float) $item['min_stock'], 2, ',', '.') ?> <?= e($item['unit']) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Total Mutasi</div>
                <div class="fs-4 fw-bold"><?= count($transactions) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Referensi</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Sebelum</th>
                        <th class="text-end">Sesudah</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada mutasi stok untuk barang ini.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($transactions as $t): ?>
                        <?php
                            $typeBadge = [
                                'in'         => 'bg-success',
                                'out'        => 'bg-danger',
                                'adjustment' => 'bg-warning text-dark',
                            ][$t['transaction_type']] ?? 'bg-secondary';
                            $typeLabel = [
                                'in' => 'Masuk', 'out' => 'Keluar', 'adjustment' => 'Penyesuaian',
                            ][$t['transaction_type']] ?? $t['transaction_type'];
                        ?>
                        <tr>
                            <td><?= formatTanggal($t['transaction_date']) ?></td>
                            <td><span class="badge <?= $typeBadge ?>"><?= $typeLabel ?></span></td>
                            <td class="text-muted small"><?= e($t['reference_type'] ?? '-') ?> #<?= (int) $t['reference_id'] ?></td>
                            <td class="text-end"><?= number_format((float) $t['qty'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float) $t['qty_before'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float) $t['qty_after'], 2, ',', '.') ?></td>
                            <td class="text-muted small"><?= e($t['notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
