<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Validasi Sesuai</h4>
        <small class="text-muted">Barang yang sudah divalidasi dan hasilnya SESUAI dengan PO</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?module=validation" class="btn btn-outline-secondary">
            <i class="bi bi-list-check"></i> Semua Validasi
        </a>
        <a href="<?= BASE_URL ?>/index.php?module=validation&action=problem" class="btn btn-outline-warning">
            <i class="bi bi-exclamation-triangle"></i> Validasi Belum Sesuai
        </a>
        <a href="<?= BASE_URL ?>/index.php?module=validation&action=report" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-bar-graph"></i> Laporan Selisih
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="validation">
            <input type="hidden" name="action" value="approved">
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Cari (No. Penerimaan / No. PO / Nama Barang)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
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
                        <th>No</th>
                        <th>No. PO</th>
                        <th>Pembuat PO</th>
                        <th>Supplier</th>
                        <th>Project</th>
                        <th>Tanggal</th>
                        <th>Nama Barang</th>
                        <th class="text-end">Qty PO</th>
                        <th class="text-end">Qty Diterima</th>
                        <th class="text-center">Status Validasi</th>
                        <th>Tanggal Validasi</th>
                        <th>Validator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="12" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-check2-circle empty-icon"></i>
                                <div class="empty-title">Belum ada barang yang sesuai</div>
                                <div class="empty-desc mb-0">Item penerimaan barang yang sudah divalidasi "Sesuai" akan muncul di sini.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($item['po_number']) ?></td>
                            <td><?= e($item['pembuat_po'] ?? '-') ?></td>
                            <td><?= e($item['supplier_name']) ?></td>
                            <td><?= e($item['project_name']) ?></td>
                            <td><?= formatTanggal($item['receipt_date']) ?></td>
                            <td><?= e($item['item_name']) ?></td>
                            <td class="text-end"><?= number_format((float) $item['qty_order'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float) $item['qty_received'], 2, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= e($statusBadgeClass[$item['comparison_status']]) ?>">
                                    <?= e($statusLabels[$item['comparison_status']]) ?>
                                </span>
                            </td>
                            <td><?= !empty($item['validated_at']) ? formatTanggal(substr($item['validated_at'], 0, 10)) . ' ' . substr($item['validated_at'], 11, 5) : '-' ?></td>
                            <td><?= e($item['validated_by_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
