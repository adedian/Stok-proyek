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
                    <select name="purchase_order_id" id="poSelect" class="form-select" required <?= $isEdit ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Purchase Order --</option>
                        <?php foreach ($poList as $po): ?>
                            <option value="<?= (int) $po['id'] ?>" data-currency="<?= e(normalizeCurrency($po['currency'] ?? 'IDR')) ?>" <?= (string) $selectedPoId === (string) $po['id'] ? 'selected' : '' ?>>
                                <?= e($po['po_number']) ?> [<?= e(PurchaseOrder::categoryLabel($po['po_category'] ?? null)) ?>] &mdash; <?= e($po['supplier_name']) ?> (<?= formatMoney($po['total_amount'], $po['currency'] ?? 'IDR') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="purchase_order_id" value="<?= (int) $selectedPoId ?>">
                        <div class="form-text">PO pada pembayaran tidak dapat diubah (nomor termin melekat pada PO ini).</div>
                    <?php endif; ?>
                    <div class="form-text" id="remainingInfo">
                        <?php if ($remaining !== null && $progress !== null): ?>
                            Sisa tagihan PO ini: <strong><?= formatMoney($remaining, $selectedPo['currency'] ?? 'IDR') ?></strong>
                            &middot; Sudah dibayar: <strong><?= e($progress['percentage_text']) ?>%</strong>
                        <?php endif; ?>
                    </div>
                    <?php $progressBar = $progress['percentage_bar'] ?? 0.0; $progressStatus = $progress['status'] ?? 'pending'; ?>
                    <div class="progress mt-1" style="height: 6px;">
                        <div class="progress-bar bg-<?= $progressStatus === 'paid' ? 'success' : ($progressStatus === 'overpaid' ? 'danger' : 'primary') ?>" id="remainingProgressBar"
                             role="progressbar" style="width: <?= (float) $progressBar ?>%"
                             aria-valuenow="<?= (float) $progressBar ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jenis Pembayaran <span class="text-danger">*</span></label>
                    <?php if ($isEdit): ?>
                        <input type="text" class="form-control" value="<?= e(($payment['payment_type'] ?? 'termin') === 'pelunasan' ? 'Pelunasan' : 'Termin ' . (int) $payment['termin']) ?>" readonly tabindex="-1">
                        <input type="hidden" name="payment_type" id="paymentTypeHidden" value="<?= e($paymentType) ?>">
                        <div class="form-text">Jenis & nomor tidak dapat diubah.</div>
                    <?php else: ?>
                        <div class="btn-group w-100" role="group" aria-label="Jenis Pembayaran">
                            <?php foreach ($typeLabels as $tKey => $tLabel): ?>
                                <input type="radio" class="btn-check" name="payment_type" id="ptype_<?= e($tKey) ?>" value="<?= e($tKey) ?>" autocomplete="off" <?= $paymentType === $tKey ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="ptype_<?= e($tKey) ?>"><?= e($tLabel) ?></label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Bayar <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" <?= !$isEdit ? BackDate::minAttr('payment') : '' ?> class="form-control"
                           value="<?= e($payment['payment_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-12">
                    <div id="terminInfoBox" class="alert alert-light border small mb-0 d-none">
                        <i class="bi bi-hash"></i> Nomor termin dibuat otomatis oleh sistem.
                        Pembayaran ini akan tercatat sebagai <strong id="terminNextLabel">Termin <?= (int) ($nextTermin ?? 1) ?></strong>.
                    </div>
                    <div id="pelunasanInfoBox" class="card border-success d-none">
                        <div class="card-body py-2">
                            <div class="fw-semibold text-success mb-2"><i class="bi bi-check2-circle"></i> Pelunasan &mdash; seluruh sisa tagihan PO</div>
                            <div class="row g-2 small">
                                <div class="col-6 col-md-3"><div class="text-muted">Total PO</div><div class="fw-semibold" id="plTotal">-</div></div>
                                <div class="col-6 col-md-3"><div class="text-muted">Total Pembayaran Sebelumnya</div><div class="fw-semibold" id="plPaid">-</div></div>
                                <div class="col-6 col-md-3"><div class="text-muted">Sisa Tagihan</div><div class="fw-semibold text-danger" id="plRemaining">-</div></div>
                                <div class="col-6 col-md-3"><div class="text-muted">Jenis Pembayaran</div><div class="fw-semibold">Pelunasan</div></div>
                            </div>
                            <div class="form-text mt-2 mb-0">Nominal pelunasan diisi otomatis = sisa tagihan (tanpa pembulatan) dan dihitung ulang server saat disimpan. Jika sisa berubah karena pembayaran lain, simpan akan ditolak agar Anda memeriksa ulang.</div>
                        </div>
                    </div>
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
                    <label class="form-label">Metode Pembayaran <span class="text-danger">*</span></label>
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
                <?php
                // Mata uang pembayaran = mata uang PO yang dipilih (read-only; ikut berganti saat PO
                // diganti). Kurs HANYA tampil & wajib untuk mata uang selain IDR; IDR = kurs 1 otomatis
                // (field disembunyikan + tidak dikirim; backend tetap menetapkan 1).
                $formCurrency = normalizeCurrency($selectedPo['currency'] ?? 'IDR');
                $kursNeeded = $formCurrency !== 'IDR';
                $kursValue = ($isEdit && $kursNeeded && isset($payment['kurs'])) ? formatKurs($payment['kurs']) : '';
                ?>
                <div class="col-md-3">
                    <label class="form-label">Mata Uang</label>
                    <input type="text" id="paymentCurrencyDisplay" class="form-control" value="<?= e($formCurrency) ?>" readonly tabindex="-1">
                    <input type="hidden" name="currency" id="paymentCurrency" value="<?= e($formCurrency) ?>">
                    <div class="form-text">Mengikuti mata uang PO.</div>
                </div>
                <div class="col-md-3 <?= $kursNeeded ? '' : 'd-none' ?>" id="kursWrap">
                    <label class="form-label">Kurs <span class="text-danger">*</span></label>
                    <input type="text" name="kurs" id="kursInput" class="form-control currency-input" data-decimals="6" data-format="id"
                           inputmode="decimal" autocomplete="off" placeholder="mis. 16.500"
                           value="<?= e($kursValue) ?>" <?= $kursNeeded ? 'required' : 'disabled' ?>>
                    <div class="form-text" id="kursHint">Kurs saat bayar (Rp per 1 <span id="kursCurrencyLabel"><?= e($formCurrency) ?></span>), format Indonesia: titik = ribuan, koma = desimal (16.500 atau 12,75). Nominal Rupiah dibagi kurs ini menjadi nilai dalam mata uang PO.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nominal Pembayaran (IDR) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">IDR</span>
                        <input type="text" name="amount" id="amountInput" class="form-control currency-input" inputmode="numeric"
                               value="<?= e(!empty($payment['amount_idr']) ? number_format((float) $payment['amount_idr'], 2, '.', ',') : (!empty($payment['amount']) ? number_format((float) $payment['amount'], 2, '.', ',') : '')) ?>" required>
                    </div>
                    <div class="form-text <?= $kursNeeded ? '' : 'd-none' ?>" id="amountForeignHint">
                        Setara <strong id="amountForeignValue">-</strong> <span id="amountForeignCode"><?= e($formCurrency) ?></span> (Nominal &divide; Kurs, dihitung ulang oleh server saat disimpan).
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bukti Transfer <?= $isEdit ? '(kosongkan jika tidak ganti)' : '' ?></label>
                    <input type="file" name="proof_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <div class="form-text">Foto (JPG/PNG/WEBP) maks. 5 MB, atau PDF maks. 25 MB. PDF hasil scan otomatis dikecilkan saat diunggah.</div>
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
    const isEditMode = <?= $isEdit ? 'true' : 'false' ?>;
    // Sisa tagihan PO dalam mata uang PO (dari server) -- dasar isi otomatis Nominal Pembayaran.
    let remainingForeign = <?= $remaining !== null ? (float) $remaining : 'null' ?>;
    // Sisa/total/dibayar sebagai STRING desimal persis dari server (tanpa float) -- dipakai isi
    // nominal Pelunasan & panel info. Persentase/status TIDAK dihitung di JS (selalu dari server).
    let remainingExact = <?= $remaining !== null ? json_encode((string) $remaining) : 'null' ?>;
    let totalExact = <?= $progress !== null ? json_encode((string) $progress['total_amount']) : 'null' ?>;
    let paidExact = <?= $progress !== null ? json_encode((string) $progress['total_paid']) : 'null' ?>;
    let nextTermin = <?= (int) ($nextTermin ?? 1) ?>;
    const currencyPrefixes = <?= json_encode(array_combine(poCurrencies(), array_map('currencyPrefix', poCurrencies()))) ?>;

    // "2029028.15" -> "2,029,028.15" tanpa float (pemisah ribuan koma, desimal titik -- format input nominal).
    function fmtExact(str) {
        if (str === null || str === undefined) { return ''; }
        const parts = String(str).split('.');
        const neg = parts[0].charAt(0) === '-';
        const int = parts[0].replace('-', '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return (neg ? '-' : '') + int + '.' + ((parts[1] || '') + '00').slice(0, 2);
    }
    function moneyLabel(str, code) {
        return (currencyPrefixes[code] || code) + ' ' + fmtExact(str);
    }
    function currentType() {
        const checked = document.querySelector('input[name="payment_type"]:checked');
        if (checked) { return checked.value; }
        const hidden = document.getElementById('paymentTypeHidden');
        return hidden ? hidden.value : 'termin';
    }
    function currentCode() {
        return (document.getElementById('paymentCurrency').value || 'IDR');
    }

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
            if (window.resyncSearchableSelect) {
                window.resyncSearchableSelect(paymentMethodSelect);
            }
        }
    }

    fundingSourceSelect.addEventListener('change', applyFundingSourceState);
    applyFundingSourceState();

    // Mata uang & Kurs: ikut PO terpilih (data-currency). IDR -> sembunyikan Kurs, tidak wajib,
    // tidak dikirim (backend menetapkan 1). Selain IDR -> tampilkan Kurs & wajib diisi.
    const kursWrap = document.getElementById('kursWrap');
    const kursInput = document.getElementById('kursInput');
    function applyCurrencyState(code) {
        code = code || 'IDR';
        const isIdr = code === 'IDR';
        document.getElementById('paymentCurrency').value = code;
        document.getElementById('paymentCurrencyDisplay').value = code;
        document.getElementById('kursCurrencyLabel').textContent = code;
        kursWrap.classList.toggle('d-none', isIdr);
        kursInput.disabled = isIdr;
        kursInput.required = !isIdr;
        document.getElementById('amountForeignHint').classList.toggle('d-none', isIdr);
        document.getElementById('amountForeignCode').textContent = code;
        updateForeign();
    }
    // Setara mata uang PO realtime = nominal IDR / kurs (HANYA tampilan; server menghitung ulang).
    // Nominal: koma ribuan/titik desimal. Kurs: format Indonesia (titik ribuan, koma desimal).
    function updateForeign() {
        const idr = parseFloat((document.getElementById('amountInput').value || '').replace(/,/g, ''));
        const kurs = parseFloat((kursInput.value || '').replace(/\./g, '').replace(',', '.'));
        const out = document.getElementById('amountForeignValue');
        if (!isNaN(idr) && !isNaN(kurs) && idr > 0 && kurs > 0) {
            out.textContent = (Math.round(idr / kurs * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            out.textContent = '-';
        }
    }
    // Isi otomatis Nominal Pembayaran (IDR) = sisa tagihan PO x Kurs (PO baru tanpa pembayaran =
    // nilai PO x Kurs). Hanya saat Tambah; berhenti kalau user sudah mengetik nominal sendiri
    // (bisa diisi ulang dengan mengosongkan nominal). Server tetap menghitung ulang saat simpan.
    const amountInput = document.getElementById('amountInput');
    let amountTouched = isEditMode;
    function autoFillAmount() {
        const pelunasan = currentType() === 'pelunasan';
        if (!pelunasan && amountTouched && amountInput.value.trim() !== '') { return; }
        if (remainingExact === null || parseFloat(remainingExact) <= 0) { return; }
        if (currentCode() === 'IDR') {
            // IDR: isi dari string sisa persis (tanpa float) -> desimal Pelunasan tidak berubah.
            amountInput.value = fmtExact(remainingExact);
        } else {
            const kurs = parseFloat((kursInput.value || '').replace(/\./g, '').replace(',', '.'));
            if (isNaN(kurs) || kurs <= 0 || remainingForeign === null) { return; }
            amountInput.value = (Math.round(remainingForeign * kurs * 100) / 100)
                .toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        amountTouched = false;
    }
    amountInput.addEventListener('input', function (e) {
        if (e.isTrusted) { amountTouched = amountInput.value.trim() !== ''; }
        updateForeign();
    });
    kursInput.addEventListener('input', function () { autoFillAmount(); updateForeign(); });
    function selectedPoCurrency() {
        const opt = poSelect.options[poSelect.selectedIndex];
        return (opt && opt.dataset.currency) ? opt.dataset.currency : 'IDR';
    }
    applyCurrencyState(selectedPoCurrency());
    poSelect.addEventListener('change', function () { applyCurrencyState(selectedPoCurrency()); });

    function formatPercentage(bar, barWidth, status) {
        if (!bar) { return; }
        bar.style.width = barWidth + '%';
        bar.classList.toggle('bg-success', status === 'paid');
        bar.classList.toggle('bg-danger', status === 'overpaid');
        bar.classList.toggle('bg-primary', status !== 'paid' && status !== 'overpaid');
    }

    // Jenis Pembayaran: Termin (nomor otomatis) / Pelunasan (nominal = sisa tagihan, terkunci).
    const terminBox = document.getElementById('terminInfoBox');
    const pelunasanBox = document.getElementById('pelunasanInfoBox');
    function applyTypeState() {
        const pelunasan = currentType() === 'pelunasan';
        terminBox.classList.toggle('d-none', pelunasan || isEditMode);
        pelunasanBox.classList.toggle('d-none', !pelunasan);
        amountInput.readOnly = pelunasan;
        if (pelunasan) {
            const code = currentCode();
            document.getElementById('plTotal').textContent = totalExact === null ? '-' : moneyLabel(totalExact, code);
            document.getElementById('plPaid').textContent = paidExact === null ? '-' : moneyLabel(paidExact, code);
            document.getElementById('plRemaining').textContent = remainingExact === null ? '-' : moneyLabel(remainingExact, code);
            autoFillAmount();
            updateForeign();
        }
        document.getElementById('terminNextLabel').textContent = 'Termin ' + nextTermin;
    }
    document.querySelectorAll('input[name="payment_type"]').forEach(function (r) {
        r.addEventListener('change', applyTypeState);
    });
    kursInput.addEventListener('input', function () { if (currentType() === 'pelunasan') { autoFillAmount(); updateForeign(); } });

    // Konfirmasi Pelunasan sebelum simpan (nominal ditampilkan sekali lagi).
    amountInput.form.addEventListener('submit', function (e) {
        if (currentType() === 'pelunasan' && !isEditMode) {
            const ok = confirm('Konfirmasi PELUNASAN?\n\nNominal: IDR ' + amountInput.value
                + '\nSeluruh sisa tagihan PO akan dibayar dan PO menjadi LUNAS.');
            if (!ok) { e.preventDefault(); }
        }
    });

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
                    + ' &middot; Sudah dibayar: <strong>' + data.percentage_text + '%</strong>'
                    + ' &middot; <span class="badge bg-secondary">' + data.payment_status_label + '</span>';
                formatPercentage(progressBar, data.percentage_bar, data.payment_status);
                remainingForeign = data.remaining;
                remainingExact = data.remaining_exact;
                totalExact = data.total_amount_exact;
                paidExact = data.total_paid_exact;
                nextTermin = data.next_termin;
                applyCurrencyState(data.currency);
                autoFillAmount();
                updateForeign();
                applyTypeState();
            })
            .catch(function () {
                remainingInfo.textContent = 'Gagal memuat sisa tagihan.';
            });
    });

    applyTypeState();
})();
</script>
