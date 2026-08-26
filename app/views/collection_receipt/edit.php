<?php
// Peta invoice_id -> delivery_note_id yang sudah tersimpan, untuk prefill dropdown.
$currentDeliveryNoteByInvoice = [];
foreach ($currentItems as $it) {
    $currentDeliveryNoteByInvoice[(int) $it['sales_invoice_id']] = (int) ($it['delivery_note_id'] ?? 0);
}
// Gabungkan invoice yang SUDAH ada di receipt ini (currentItems, sudah join info
// invoice_number/tax_invoice_number) dengan invoice LAIN milik client yang sama
// yang masih boleh ditambahkan (availableInvoices, belum join) -- union by id.
$rows = [];
foreach ($currentItems as $it) {
    $rows[(int) $it['sales_invoice_id']] = [
        'id' => (int) $it['sales_invoice_id'],
        'invoice_number' => $it['invoice_number'],
        'tax_invoice_number' => $it['tax_invoice_number'],
        'total_amount' => $it['total_amount'],
        'checked' => true,
    ];
}
foreach ($availableInvoices as $inv) {
    if (!isset($rows[(int) $inv['id']])) {
        $rows[(int) $inv['id']] = [
            'id' => (int) $inv['id'],
            'invoice_number' => $inv['invoice_number'],
            'tax_invoice_number' => $inv['tax_invoice_number'] ?? null,
            'total_amount' => $inv['total_amount'],
            'checked' => false,
        ];
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Edit Pembayaran</h4>
        <small class="text-muted">
            No. <strong><?= e($receipt['receipt_number']) ?></strong> &middot; Customer: <strong><?= e($receipt['client_name']) ?></strong>
            <span class="text-muted">(nomor &amp; client tidak bisa diganti)</span>
        </small>
    </div>
    <a href="<?= BASE_URL ?>/index.php?module=collection_receipt" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=collection_receipt&action=update">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int) $receipt['id'] ?>">

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 36px;"></th>
                            <th>No. Invoice</th>
                            <th>Faktur Pajak</th>
                            <th style="width: 260px;">Surat Jalan <span class="text-muted small fw-normal">(opsional)</span></th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input" name="invoice_ids[]"
                                           value="<?= (int) $row['id'] ?>" <?= $row['checked'] ? 'checked' : '' ?>>
                                </td>
                                <td><?= e($row['invoice_number']) ?></td>
                                <td><?= e($row['tax_invoice_number'] ?? '-') ?></td>
                                <td>
                                    <select name="delivery_note_id[<?= (int) $row['id'] ?>]" class="form-select form-select-sm">
                                        <option value="">-- Tanpa Surat Jalan --</option>
                                        <?php foreach ($deliveryNotes as $dn): ?>
                                            <option value="<?= (int) $dn['id'] ?>"
                                                <?= ($currentDeliveryNoteByInvoice[$row['id']] ?? 0) === (int) $dn['id'] ? 'selected' : '' ?>>
                                                <?= e($dn['delivery_number']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="text-end"><?= formatRupiah($row['total_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="alert alert-light border small mb-3">
        <i class="bi bi-info-circle"></i> Centang invoice yang termasuk di Pembayaran ini. Hapus centang untuk melepas invoice (invoice itu bisa dipakai lagi di Pembayaran lain).
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tanggal Pembayaran</label>
                    <input type="date" name="receipt_date" class="form-control" value="<?= e($receipt['receipt_date']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama Penerima <span class="text-muted small">(opsional)</span></label>
                    <input type="text" name="recipient_name" class="form-control" value="<?= e($receipt['recipient_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanda Tangan HME</label>
                    <select name="signature_id" class="form-select">
                        <option value="">-- Tanpa Tanda Tangan Gambar --</option>
                        <?php foreach ($signatures as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) ($receipt['signature_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['name']) ?> &middot; <?= e($s['position']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2"><?= e($receipt['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
        <a href="<?= BASE_URL ?>/index.php?module=collection_receipt" class="btn btn-light border">Batal</a>
    </div>
</form>
