<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Rekap Status Pembayaran PO</h4>
        <small class="text-muted">Perbandingan total PO vs total dibayar per Purchase Order</small>
    </div>
    <a href="<?= BASE_URL ?>/payment" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali ke Pembayaran
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No. PO</th>
                        <th>Pembuat PO</th>
                        <th>Supplier</th>
                        <th class="text-end">Total PO</th>
                        <th class="text-end">Sudah Dibayar</th>
                        <th class="text-end">Persentase</th>
                        <th class="text-end">Sisa Tagihan</th>
                        <th class="text-center">Status Bayar</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($poSummary)): ?>
                        <tr>
                            <td colspan="9" class="p-0">
                                <div class="empty-state">
                                    <i class="bi bi-cart-x empty-icon"></i>
                                    <div class="empty-title">Belum ada Purchase Order</div>
                                    <div class="empty-desc mb-0">Rekap akan muncul setelah ada PO yang dibuat.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($poSummary as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($row['po_number']) ?></td>
                            <td><?= e($row['pembuat_po'] ?? '-') ?></td>
                            <td><?= e($row['supplier_name']) ?></td>
                            <td class="text-end"><?= formatRupiah($row['total_amount']) ?></td>
                            <td class="text-end">
                                <?= formatRupiah($row['total_paid']) ?>
                                <span class="text-muted small">/ <?= number_format($row['percentage'], 1, ',', '.') ?>%</span>
                            </td>
                            <td class="text-end fw-semibold" style="min-width: 110px;">
                                <?= number_format($row['percentage'], 1, ',', '.') ?>%
                                <div class="progress mt-1" style="height: 6px;">
                                    <div class="progress-bar bg-<?= $row['percentage'] >= 100 ? 'success' : 'primary' ?>"
                                         role="progressbar" style="width: <?= (float) $row['percentage'] ?>%"
                                         aria-valuenow="<?= (float) $row['percentage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td class="text-end"><?= formatRupiah($row['remaining']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= e($statusBadgeClass[$row['payment_status']]) ?>">
                                    <?= e($statusLabels[$row['payment_status']]) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($row['remaining'] > 0): ?>
                                    <a href="<?= BASE_URL ?>/payment/create?po_id=<?= (int) $row['id'] ?>"
                                       class="btn btn-sm btn-outline-primary" title="Bayar">
                                        <i class="bi bi-plus-circle"></i> Bayar
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Lunas</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
