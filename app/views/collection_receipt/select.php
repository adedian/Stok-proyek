<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Buat Tanda Terima</h4>
        <small class="text-muted">Customer: <strong><?= e($client['name'] ?? '-') ?></strong> &middot; <?= count($invoices) ?> invoice terpilih</small>
    </div>
    <a href="<?= BASE_URL ?>/sales_invoice" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=collection_receipt&action=store">
    <?= csrfField() ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. Invoice</th>
                            <th>Faktur Pajak</th>
                            <th style="width: 260px;">Surat Jalan <span class="text-muted small fw-normal">(opsional)</span></th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $grandTotal = 0; ?>
                        <?php foreach ($invoices as $inv): ?>
                            <?php $grandTotal += (float) $inv['total_amount']; ?>
                            <tr>
                                <td>
                                    <?= e($inv['invoice_number']) ?>
                                    <input type="hidden" name="invoice_ids[]" value="<?= (int) $inv['id'] ?>">
                                </td>
                                <td><?= e($inv['tax_invoice_number'] ?? '-') ?></td>
                                <td>
                                    <select name="delivery_note_id[<?= (int) $inv['id'] ?>]" class="form-select form-select-sm">
                                        <option value="">-- Tanpa Surat Jalan --</option>
                                        <?php foreach ($deliveryNotes as $dn): ?>
                                            <option value="<?= (int) $dn['id'] ?>"><?= e($dn['delivery_number']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="text-end"><?= formatRupiah($inv['total_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="3" class="text-end fw-bold">Grand Total</td>
                            <td class="text-end fw-bold"><?= formatRupiah($grandTotal) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tanggal Tanda Terima</label>
                    <input type="date" name="receipt_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama Penerima <span class="text-muted small">(opsional)</span></label>
                    <input type="text" name="recipient_name" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanda Tangan HME</label>
                    <select name="signature_id" class="form-select">
                        <option value="">-- Tanpa Tanda Tangan Gambar --</option>
                        <?php foreach ($signatures as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> &middot; <?= e($s['position']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Buat &amp; Cetak Tanda Terima</button>
        <a href="<?= BASE_URL ?>/sales_invoice" class="btn btn-light border">Batal</a>
    </div>
</form>
