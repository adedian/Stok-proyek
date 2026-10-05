<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= e($po['po_number']) ?></h4>
        <small class="text-muted">Detail Purchase Order</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/purchase_order/print?ids=<?= (int) $po['id'] ?>"
           class="btn btn-outline-dark" target="_blank">
            <i class="bi bi-printer"></i> Cetak PO
        </a>
        <?php if ($po['status'] === 'waiting_approval' && can('purchase_order', 'approve')): ?>
            <form method="POST" action="<?= BASE_URL ?>/index.php?module=purchase_order&action=approve"
                  onsubmit="return confirm('Setujui PO <?= e($po['po_number']) ?>? Setelah disetujui, data PO ini tidak bisa diubah lagi kecuali oleh Super Admin.');">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $po['id'] ?>">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Setujui PO
                </button>
            </form>
        <?php endif; ?>
        <?php if (can('purchase_order', 'edit')): ?>
            <a href="<?= BASE_URL ?>/purchase_order/edit/<?= (int) $po['id'] ?>"
               class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/purchase_order" class="btn btn-outline-secondary">
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
                        <div class="text-muted small">Mata Uang</div>
                        <div class="fw-semibold"><?= e(normalizeCurrency($po['currency'] ?? 'IDR')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Status</div>
                        <span class="badge bg-<?= e($statusBadgeClass[$po['status']] ?? 'secondary') ?>">
                            <?= e($statusLabels[$po['status']] ?? $po['status']) ?>
                        </span>
                        <?php if (!empty($po['approved_at'])): ?>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-lock-fill"></i> Disetujui <?= e($po['approved_by_name'] ?? '-') ?>,
                                <?= formatTanggal(substr($po['approved_at'], 0, 10)) ?> <?= substr($po['approved_at'], 11, 5) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Pembuat PO</div>
                        <div class="fw-semibold"><?= e($po['pembuat_po'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Penerima Barang</div>
                        <div class="fw-semibold"><?php if (!empty($po['receiver_name'])): ?><?= e($po['receiver_name']) ?><?php else: ?><span class="text-muted fst-italic">Belum ditentukan</span><?php endif; ?></div>
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
                            <div><?= nl2br(e($po['notes'])) ?></div>
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
                        <div class="fw-semibold"><?= formatMoney($paymentInfo['total_amount'], $po['currency'] ?? 'IDR') ?></div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="text-muted small">Sudah Dibayar</div>
                        <div class="fw-semibold text-success"><?= formatMoney($paymentInfo['total_paid'], $po['currency'] ?? 'IDR') ?></div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="text-muted small">Sisa</div>
                        <div class="fw-semibold text-danger"><?= formatMoney($paymentInfo['remaining'], $po['currency'] ?? 'IDR') ?></div>
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
                        <a href="<?= BASE_URL ?>/payment/create?po_id=<?= (int) $po['id'] ?>"
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
                            <th class="text-end">Diskon</th>
                            <th class="text-end">PPN</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= e($item['unit']) ?></td>
                                <td class="text-end"><?= number_format((float) $item['qty_order'], 2, ',', '.') ?></td>
                                <td class="text-end"><?= formatMoney($item['price'], $po['currency'] ?? 'IDR') ?></td>
                                <td class="text-end"><?= (float) ($item['discount_percent'] ?? 0) > 0 ? number_format((float) $item['discount_percent'], 1, ',', '.') . '%' : '-' ?></td>
                                <td class="text-end"><?= !empty($item['ppn_enabled']) ? number_format((float) $item['ppn_percent'], 1, ',', '.') . '%' : '-' ?></td>
                                <td class="text-end"><?= formatMoney($item['subtotal'], $po['currency'] ?? 'IDR') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!empty($extraCosts)): ?>
                            <?php foreach ($extraCosts as $cost): ?>
                                <tr>
                                    <td colspan="6" class="text-muted"><i class="bi bi-plus-circle"></i> Biaya Tambahan: <?= e($cost['cost_name']) ?></td>
                                    <td class="text-end"><?= formatMoney($cost['amount'], $po['currency'] ?? 'IDR') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end fw-bold">Grand Total</td>
                            <td class="text-end fw-bold"><?= formatMoney($po['total_amount'], $po['currency'] ?? 'IDR') ?></td>
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
