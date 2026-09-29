<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$selectedTermId = $selectedTermId ?? ($payment['sales_invoice_term_id'] ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Pembayaran Invoice</h4>
        <small class="text-muted">
            No. Kwitansi:
            <strong><?= $paymentNumber ? e($paymentNumber) : '(otomatis, dibuat saat disimpan)' ?></strong>
        </small>
    </div>
    <a href="<?= BASE_URL ?>/sales_invoice_payment" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST"
              action="<?= BASE_URL ?>/index.php?module=sales_invoice_payment&action=<?= $actionUrl ?>"
              enctype="multipart/form-data">
            <?= csrfField() ?>
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Termin Invoice <span class="text-danger">*</span></label>
                    <select name="sales_invoice_term_id" id="termSelect" class="form-select" required>
                        <option value="">-- Pilih Termin --</option>
                        <?php foreach ($termList as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (string) $selectedTermId === (string) $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['invoice_number']) ?> &mdash; <?= e($t['client_name']) ?> &mdash; <?= e($t['label']) ?> (<?= formatPercent($t['percentage']) ?>%, <?= formatRupiah($t['total_amount']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" id="remainingInfo">
                        <?php
                            $selectedTerm = null;
                            foreach ($termList as $t) {
                                if ((string) $t['id'] === (string) $selectedTermId) {
                                    $selectedTerm = $t;
                                    break;
                                }
                            }
                        ?>
                        <?php if ($selectedTerm): ?>
                            Sisa tagihan termin ini: <strong><?= formatRupiah($selectedTerm['remaining']) ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tanggal Bayar <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" class="form-control"
                           value="<?= e($payment['payment_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nominal Diterima <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" name="amount" id="amountInput" class="form-control currency-input" inputmode="numeric"
                               value="<?= e(!empty($payment['amount']) ? number_format((float) $payment['amount'], 2, '.', ',') : '') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bukti Transfer/Kwitansi <?= $isEdit ? '(kosongkan jika tidak ganti)' : '' ?></label>
                    <input type="file" name="proof_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <div class="form-text">Foto (JPG/PNG/WEBP) maks. 5 MB, atau PDF maks. 25 MB.</div>
                    <?php if ($isEdit && !empty($payment['proof_file'])): ?>
                        <div class="form-text">
                            File saat ini:
                            <a href="<?= e(fileUrl($payment['proof_file'])) ?>" target="_blank">lihat bukti</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" value="<?= e($payment['notes'] ?? '') ?>" placeholder="Opsional">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan
                </button>
                <a href="<?= BASE_URL ?>/sales_invoice_payment" class="btn btn-light border">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const termSelect = document.getElementById('termSelect');
    const remainingInfo = document.getElementById('remainingInfo');
    const excludePaymentId = <?= $isEdit ? (int) $payment['id'] : 0 ?>;

    termSelect.addEventListener('change', function () {
        const termId = this.value;
        if (!termId) {
            remainingInfo.textContent = '';
            return;
        }
        remainingInfo.textContent = 'Memuat sisa tagihan...';

        const url = '<?= BASE_URL ?>/index.php?module=sales_invoice_payment&action=ajaxRemaining&term_id=' + termId
            + '&exclude_payment_id=' + excludePaymentId;

        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    remainingInfo.textContent = data.error;
                    return;
                }
                remainingInfo.innerHTML = 'Sisa tagihan termin ini: <strong>' + data.remaining_formatted + '</strong>';
            })
            .catch(function () {
                remainingInfo.textContent = 'Gagal memuat sisa tagihan.';
            });
    });
})();
</script>
