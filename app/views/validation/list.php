<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= !empty($isProblemView) ? 'Validasi Belum Sesuai' : 'Validasi Barang Datang' ?></h4>
        <small class="text-muted">Bandingkan barang PO vs barang yang benar-benar diterima</small>
    </div>
    <div class="d-flex gap-2">
        <?php if (empty($isProblemView)): ?>
            <a href="<?= BASE_URL ?>/index.php?module=validation&action=problem" class="btn btn-outline-warning">
                <i class="bi bi-exclamation-triangle"></i> Validasi Belum Sesuai
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/index.php?module=validation" class="btn btn-outline-secondary">
                <i class="bi bi-list-check"></i> Semua Validasi
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php?module=validation&action=approved" class="btn btn-outline-success">
            <i class="bi bi-check2-circle"></i> Validasi Sesuai
        </a>
        <a href="<?= BASE_URL ?>/index.php?module=validation&action=report" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-bar-graph"></i> Laporan Selisih
        </a>
    </div>
</div>

<?php if (!empty($isProblemView)): ?>
    <div class="alert alert-info small">
        <i class="bi bi-info-circle"></i>
        Menampilkan semua item yang <strong>belum divalidasi</strong> atau hasil validasinya
        <strong>bukan Sesuai</strong> (Kurang / Lebih / Barang Lain) -- item di sini belum dianggap
        aman/valid untuk stok sampai ditindaklanjuti.
    </div>
<?php endif; ?>

<?php if ($pendingCount > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Ada <strong><?= (int) $pendingCount ?></strong> item dengan selisih yang masih menunggu validasi.
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="validation">
            <?php if (!empty($isProblemView)): ?>
                <input type="hidden" name="action" value="problem">
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (No. Penerimaan / No. PO / Nama Barang)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
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
                <label class="form-label small text-muted mb-1">Validasi</label>
                <select name="validated" class="form-select form-select-sm">
                    <option value="no" <?= $filters['validated'] === 'no' ? 'selected' : '' ?>>Belum Divalidasi</option>
                    <option value="yes" <?= $filters['validated'] === 'yes' ? 'selected' : '' ?>>Sudah Divalidasi</option>
                    <option value="" <?= $filters['validated'] === '' ? 'selected' : '' ?>>Semua</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
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
                        <th>No. Penerimaan</th>
                        <th>No. PO</th>
                        <th>Pembuat PO</th>
                        <th>Barang</th>
                        <th class="text-end">Qty PO</th>
                        <th class="text-end">Qty Diterima</th>
                        <th class="text-center">Status</th>
                        <th>Validator</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="9" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-check2-square empty-icon"></i>
                                <div class="empty-title">Tidak ada data</div>
                                <div class="empty-desc mb-0">Tidak ada item penerimaan barang yang cocok dengan filter ini.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['receipt_number']) ?></td>
                            <td><?= e($item['po_number']) ?></td>
                            <td><?= e($item['pembuat_po'] ?? '-') ?></td>
                            <td><?= e($item['item_name']) ?></td>
                            <td class="text-end"><?= number_format((float) $item['qty_order'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float) $item['qty_received'], 2, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= e($statusBadgeClass[$item['comparison_status']]) ?>">
                                    <?= e($statusLabels[$item['comparison_status']]) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($item['validated_at'])): ?>
                                    <span class="small"><?= e($item['validated_by_name'] ?? '-') ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#validateModal<?= (int) $item['id'] ?>">
                                    <i class="bi bi-clipboard-check"></i> Validasi
                                </button>
                            </td>
                        </tr>

                        <!-- Modal validasi per item -->
                        <div class="modal fade" id="validateModal<?= (int) $item['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="<?= BASE_URL ?>/index.php?module=validation&action=validateItem">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Validasi: <?= e($item['item_name']) ?></h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small text-muted mb-3">
                                                No. PO <?= e($item['po_number']) ?> &middot;
                                                Penerimaan <?= e($item['receipt_number']) ?><br>
                                                Qty PO: <?= number_format((float) $item['qty_order'], 2, ',', '.') ?>
                                                &middot; Qty Diterima: <?= number_format((float) $item['qty_received'], 2, ',', '.') ?>
                                            </p>
                                            <div class="mb-3">
                                                <label class="form-label">Status Validasi</label>
                                                <select name="comparison_status" class="form-select" required>
                                                    <?php foreach ($statusLabels as $key => $label): ?>
                                                        <option value="<?= e($key) ?>" <?= $item['comparison_status'] === $key ? 'selected' : '' ?>>
                                                            <?= e($label) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">Catatan (wajib jika bukan "Sesuai")</label>
                                                <textarea name="validation_notes" class="form-control" rows="2"><?= e($item['validation_notes'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Validasi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
