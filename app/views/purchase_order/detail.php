<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= e($po['po_number']) ?></h4>
        <small class="text-muted">Detail Purchase Order</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?module=purchase_order&action=print&ids=<?= (int) $po['id'] ?>"
           class="btn btn-outline-dark" target="_blank">
            <i class="bi bi-printer"></i> Cetak PO
        </a>
        <a href="<?= BASE_URL ?>/index.php?module=purchase_order&action=edit&id=<?= (int) $po['id'] ?>"
           class="btn btn-outline-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <a href="<?= BASE_URL ?>/index.php?module=purchase_order" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 mb-2">
                    <div class="col-md-6">
                        <div class="text-muted small">Supplier</div>
                        <div class="fw-semibold"><?= e($po['supplier_name']) ?> (<?= e($po['supplier_code']) ?>)</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Project</div>
                        <div class="fw-semibold"><?= e($po['project_name']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Tanggal PO</div>
                        <div class="fw-semibold"><?= formatTanggal($po['po_date']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Status</div>
                        <span class="badge bg-<?= e($statusBadgeClass[$po['status']] ?? 'secondary') ?>">
                            <?= e($statusLabels[$po['status']] ?? $po['status']) ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Pembuat PO</div>
                        <div class="fw-semibold"><?= e($po['pembuat_po'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Tanda Tangan</div>
                        <div class="fw-semibold">
                            <?= e($po['signature_name'] ?? '-') ?>
                            <?php if (!empty($po['signature_position'])): ?>
                                <span class="text-muted">(<?= e($po['signature_position']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Dibuat oleh (akun sistem)</div>
                        <div class="fw-semibold"><?= e($po['created_by_name'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Lokasi Pengiriman</div>
                        <div class="fw-semibold"><?= e($po['delivery_location_name'] ?? '-') ?></div>
                    </div>
                    <?php if (!empty($po['notes'])): ?>
                        <div class="col-12">
                            <div class="text-muted small">Catatan</div>
                            <div><?= e($po['notes']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="bi bi-credit-card"></i> Info Pembayaran</h6>
                    <span class="badge bg-<?= e($paymentStatusBadgeClass[$paymentInfo['status']] ?? 'secondary') ?>">
                        <?= e($paymentStatusLabels[$paymentInfo['status']] ?? $paymentInfo['status']) ?>
                    </span>
                </div>
                <div class="row g-3 mb-2">
                    <div class="col-md-3 col-6">
                        <div class="text-muted small">Total PO</div>
                        <div class="fw-semibold"><?= formatRupiah($paymentInfo['total_amount']) ?></div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="text-muted small">Sudah Dibayar</div>
                        <div class="fw-semibold text-success"><?= formatRupiah($paymentInfo['total_paid']) ?></div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="text-muted small">Sisa</div>
                        <div class="fw-semibold text-danger"><?= formatRupiah($paymentInfo['remaining']) ?></div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="text-muted small">Persentase</div>
                        <div class="fw-semibold"><?= number_format($paymentInfo['percentage'], 1, ',', '.') ?>%</div>
                    </div>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-<?= $paymentInfo['percentage'] >= 100 ? 'success' : 'primary' ?>"
                         role="progressbar" style="width: <?= (float) $paymentInfo['percentage'] ?>%"
                         aria-valuenow="<?= (float) $paymentInfo['percentage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <?php if ($paymentInfo['remaining'] > 0 && canCreate('payment')): ?>
                    <div class="mt-3">
                        <a href="<?= BASE_URL ?>/index.php?module=payment&action=create&po_id=<?= (int) $po['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-circle"></i> Tambah Pembayaran
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Barang</th>
                            <th>Satuan</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Harga</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= e($item['unit']) ?></td>
                                <td class="text-end"><?= number_format((float) $item['qty_order'], 2, ',', '.') ?></td>
                                <td class="text-end"><?= formatRupiah($item['price']) ?></td>
                                <td class="text-end"><?= formatRupiah($item['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold"><?= formatRupiah($po['total_amount']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3"><i class="bi bi-clock-history"></i> History Perubahan</h6>
                <?php if (empty($history)): ?>
                    <p class="text-muted small mb-0">Belum ada history.</p>
                <?php else: ?>
                    <ul class="list-unstyled m-0">
                        <?php foreach ($history as $h): ?>
                            <li class="mb-3 pb-3 border-bottom">
                                <div class="small text-muted">
                                    <?= formatTanggal(substr($h['created_at'], 0, 10)) ?>
                                    <?= substr($h['created_at'], 11, 5) ?>
                                    &middot; <?= e($h['full_name'] ?? 'Sistem') ?>
                                </div>
                                <div><?= e($h['description']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
