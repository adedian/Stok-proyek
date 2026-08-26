<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Laporan Selisih Barang</h4>
        <small class="text-muted">Semua item dengan status kurang / lebih / barang lain</small>
    </div>
    <a href="<?= BASE_URL ?>/index.php?module=validation" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="validation">
            <input type="hidden" name="action" value="report">
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
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-md-3">
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
                        <th>Tanggal</th>
                        <th>No. Penerimaan</th>
                        <th>No. PO</th>
                        <th>Pembuat PO</th>
                        <th>Supplier</th>
                        <th>Project</th>
                        <th>Barang</th>
                        <th class="text-end">Qty PO</th>
                        <th class="text-end">Qty Diterima</th>
                        <th class="text-center">Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">Tidak ada selisih pada rentang ini. 🎉</td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= formatTanggal($item['receipt_date']) ?></td>
                            <td><?= e($item['receipt_number']) ?></td>
                            <td><?= e($item['po_number']) ?></td>
                            <td><?= e($item['pembuat_po'] ?? '-') ?></td>
                            <td><?= e($item['supplier_name']) ?></td>
                            <td><?= e($item['project_name']) ?></td>
                            <td><?= e($item['item_name']) ?></td>
                            <td class="text-end"><?= number_format((float) $item['qty_order'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float) $item['qty_received'], 2, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= e($statusBadgeClass[$item['comparison_status']]) ?>">
                                    <?= e($statusLabels[$item['comparison_status']]) ?>
                                </span>
                            </td>
                            <td class="small"><?= e($item['validation_notes'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="text-muted small mt-2">
    <i class="bi bi-info-circle"></i>
    Export PDF/Excel & print untuk laporan ini akan dilengkapi di modul Reporting (Phase 12).
</p>
