<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= e($invoice['invoice_number']) ?></h4>
        <small class="text-muted">Detail Invoice Keluar</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?module=sales_invoice&action=print&ids=<?= (int) $invoice['id'] ?>"
           class="btn btn-outline-dark" target="_blank">
            <i class="bi bi-printer"></i> Cetak Invoice
        </a>
        <?php if (can('sales_invoice', 'edit')): ?>
            <a href="<?= BASE_URL ?>/index.php?module=sales_invoice&action=edit&id=<?= (int) $invoice['id'] ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php?module=sales_invoice" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<?php if ($isBilled): ?>
    <div class="alert alert-info small mb-3">
        <i class="bi bi-info-circle"></i> Invoice ini sudah tercatat di sebuah Tanda Terima.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 mb-2">
                    <div class="col-md-3">
                        <div class="text-muted small">Jenis Invoice</div>
                        <div class="fw-semibold">
                            <span class="badge <?= $invoice['invoice_type'] === 'lampu' ? 'text-bg-warning' : 'text-bg-info' ?>">
                                <?= $invoice['invoice_type'] === 'lampu' ? 'Lampu' : 'Project' ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Client</div>
                        <div class="fw-semibold"><?= e($invoice['client_name']) ?> (<?= e($invoice['client_code']) ?>)</div>
                        <?php if (!empty($invoice['client_address'])): ?>
                            <div class="text-muted small"><?= nl2br(e($invoice['client_address'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Project</div>
                        <div class="fw-semibold"><?= e($invoice['project_name'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Tanggal Invoice</div>
                        <div class="fw-semibold"><?= formatTanggal($invoice['invoice_date']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">No. Kontrak</div>
                        <div class="fw-semibold"><?= e($invoice['contract_number'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">No. Faktur Pajak</div>
                        <div class="fw-semibold"><?= e($invoice['tax_invoice_number'] ?? '-') ?></div>
                    </div>
                </div>

                <div class="table-responsive mt-2">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Deskripsi</th>
                                <th class="text-end">Qty</th>
                                <th>Satuan</th>
                                <th class="text-end">Harga Satuan</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><?= e($it['description']) ?></td>
                                    <td class="text-end"><?= number_format((float) $it['qty'], 2, ',', '.') ?></td>
                                    <td><?= e($it['unit']) ?></td>
                                    <td class="text-end"><?= formatRupiah($it['unit_price']) ?></td>
                                    <td class="text-end"><?= formatRupiah($it['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="4" class="text-end fw-semibold">Jumlah</td><td class="text-end fw-semibold"><?= formatRupiah($invoice['subtotal']) ?></td></tr>
                            <tr><td colspan="4" class="text-end fw-semibold">Tagihan (DP <?= formatPercent($invoice['dp_percentage']) ?>%)</td><td class="text-end fw-semibold"><?= formatRupiah($invoice['dp_amount']) ?></td></tr>
                            <tr><td colspan="4" class="text-end fw-semibold">PPN (<?= formatPercent($invoice['ppn_percent']) ?>%)</td><td class="text-end fw-semibold"><?= formatRupiah($invoice['ppn_amount']) ?></td></tr>
                            <tr class="table-light"><td colspan="4" class="text-end fw-bold">Total</td><td class="text-end fw-bold"><?= formatRupiah($invoice['total_amount']) ?></td></tr>
                        </tfoot>
                    </table>
                </div>

                <?php if (!empty($invoice['notes'])): ?>
                    <div class="mt-3">
                        <div class="text-muted small">Catatan</div>
                        <div><?= nl2br(e($invoice['notes'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="text-muted small">Tanda Tangan</div>
                <div class="fw-semibold"><?= e($invoice['signature_name'] ?? '-') ?></div>
                <?php if (!empty($invoice['signature_position'])): ?>
                    <div class="text-muted small"><?= e($invoice['signature_position']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
