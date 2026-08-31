<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$selectedPoId = $selectedPo['id'] ?? ($payment['purchase_order_id'] ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Pembayaran</h4>
        <small class="text-muted">
            No. Pembayaran:
            <strong><?= $paymentNumber ? e($paymentNumber) : '(otomatis sesuai sumber dana, dibuat saat disimpan)' ?></strong>
            <?= $paymentNumber ? '(otomatis)' : '' ?>
        </small>
    </div>
    <a href="<?= BASE_URL ?>/payment" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST"
              action="<?= BASE_URL ?>/index.php?module=payment&action=<?= $actionUrl ?>"
              enctype="multipart/form-data">
            <?= csrfField() ?>
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Purchase Order <span class="text-danger">*</span></label>
                    <select name="purchase_order_id" id="poSelect" class="form-select" required>
                        <option value="">-- Pilih Purchase Order --</option>
                        <?php foreach ($poList as $po): ?>
                            <option value="<?= (int) $po['id'] ?>" <?= (string) $selectedPoId === (string) $po['id'] ? 'selected' : '' ?>>
                                <?= e($po['po_number']) ?> &mdash; <?= e($po['supplier_name']) ?> (<?= formatRupiah($po['total_amount']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" id="remainingInfo">
                        <?php if ($remaining !== null && $progress !== null): ?>
                            Sisa tagihan PO ini: <strong><?= formatRupiah($remaining) ?></strong>
                            &middot; Sudah dibayar: <strong><?= number_format($progress['percentage'], 1, ',', '.') ?>%</strong>
                        <?php endif; ?>
                    </div>
                    <?php $progressPct = $progress['percentage'] ?? 0.0; ?>
                    <div class="progress mt-1" style="height: 6px;">
                        <div class="progress-bar bg-<?= $progressPct >= 100 ? 'success' : 'primary' ?>" id="remainingProgressBar"
                             role="progressbar" style="width: <?= (float) $progressPct ?>%"
                             aria-valuenow="<?= (float) $progressPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Termin Ke- <span class="text-danger">*</span></label>
                    <input type="number" name="termin" class="form-control" min="1"
                           value="<?= e($payment['termin'] ?? 1) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Bayar <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" class="form-control"
                           value="<?= e($payment['payment_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Melalui <span class="text-danger">*</span></label>
                    <?php $fundingSource = $payment['funding_source'] ?? 'bank'; ?>
                    <select name="funding_source" id="fundingSourceSelect" class="form-select" required>
                        <?php foreach ($fundingSourceLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $fundingSource === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Bank = kode BK, Kas Kecil = kode KK, Kas Project = kode KKP (nomor urut terpisah per sumber dana).</div>
                </div>
                <div class="col-md-6" id="paymentMethodWrap">
                    <label class="form-label">Jenis Pembayaran <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="payment_method_id" id="payment_method_id" class="form-select">
                            <option value="">-- Pilih Jenis (Cek/Giro/Transfer Bank/Tunai) --</option>
                            <?php foreach ($paymentMethods as $pm): ?>
                                <option value="<?= (int) $pm['id'] ?>"
                                    <?= (string) ($payment['payment_method_id'] ?? '') === (string) $pm['id'] ? 'selected' : '' ?>>
                                    <?= e($pm['method_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (canQuickAdd('payment_method')): ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddPaymentMethod" title="Tambah Metode Cepat">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nominal Pembayaran <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" name="amount" id="amountInput" class="form-control currency-input" inputmode="numeric"
                               value="<?= e(!empty($payment['amount']) ? number_format((float) $payment['amount'], 2, '.', ',') : '') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bukti Transfer <?= $isEdit ? '(kosongkan jika tidak ganti)' : '' ?></label>
                    <input type="file" name="proof_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
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
                <a href="<?= BASE_URL ?>/payment" class="btn btn-light border">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php if (canQuickAdd('payment_method')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_payment_method_modal.php'; ?>
<?php endif; ?>

<script>
(function () {
    const poSelect = document.getElementById('poSelect');
    const remainingInfo = document.getElementById('remainingInfo');
    const excludePaymentId = <?= $isEdit ? (int) $payment['id'] : 0 ?>;

    // Jenis Pembayaran (Cek/Giro/Transfer Bank/Tunai) HANYA relevan kalau
    // "Melalui" = Bank -- backend (PaymentController::collectInput/validateInput)
    // tetap jadi otoritas sebenarnya, ini cuma UX supaya field tidak
    // membingungkan saat sumber dana Kas Kecil/Kas Project.
    const fundingSourceSelect = document.getElementById('fundingSourceSelect');
    const paymentMethodWrap = document.getElementById('paymentMethodWrap');
    const paymentMethodSelect = document.getElementById('payment_method_id');

    function applyFundingSourceState() {
        const isBank = fundingSourceSelect.value === 'bank';
        paymentMethodWrap.classList.toggle('d-none', !isBank);
        paymentMethodSelect.required = isBank;
        if (!isBank) {
            paymentMethodSelect.value = '';
        }
    }

    fundingSourceSelect.addEventListener('change', applyFundingSourceState);
    applyFundingSourceState();

    function formatPercentage(bar, percentage) {
        if (!bar) { return; }
        bar.style.width = percentage + '%';
        bar.classList.toggle('bg-success', percentage >= 100);
        bar.classList.toggle('bg-primary', percentage < 100);
    }

    // AJAX: setiap kali user ganti pilihan PO, ambil sisa tagihan + persentase terbaru dari server
    poSelect.addEventListener('change', function () {
        const poId = this.value;
        const progressBar = document.getElementById('remainingProgressBar');
        if (!poId) {
            remainingInfo.textContent = '';
            return;
        }
        remainingInfo.textContent = 'Memuat sisa tagihan...';

        const url = '<?= BASE_URL ?>/index.php?module=payment&action=ajaxRemaining&po_id=' + poId
            + '&exclude_payment_id=' + excludePaymentId;

        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    remainingInfo.textContent = data.error;
                    return;
                }
                remainingInfo.innerHTML = 'Sisa tagihan PO ini: <strong>' + data.remaining_formatted + '</strong>'
                    + ' &middot; Sudah dibayar: <strong>' + data.percentage.toString().replace('.', ',') + '%</strong>';
                formatPercentage(progressBar, data.percentage);
            })
            .catch(function () {
                remainingInfo.textContent = 'Gagal memuat sisa tagihan.';
            });
    });
})();
</script>
