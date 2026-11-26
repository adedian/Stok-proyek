<?php
/**
 * Form Pengajuan Fee (acuan: sheet "Pengajuan Fee" pada Pengajuan fee.xlsx).
 * Header: Invoice no., Customer, Sales, Penerima Fee, Status Tagihan, NPWP/NIK, Tanggal, Kota. Baris: BARANG, QTY, SATUAN, HARGA;
 * TOTAL = HARGA x QTY dan FEE = (TOTAL / (1+PPN%)) x Fee% dihitung live (tampilan saja -- server menghitung & memvalidasi ulang).
 * Potong Pph = Total Fee x PPh%; Total Fee diterima = Total Fee - Potong Pph. Default 10% / 11% / 2,5% = angka di rumus Excel.
 * Variabel: $mode, $f, $items, $posted, $number, $cities, $salesList, $recipients, $canSubmit
 */
$isEdit = $mode === 'edit';
$action = $isEdit ? 'update' : 'store';
$h = fn(string $k, $def = '') => $posted[$k] ?? ($f[$k] ?? $def);
$pctText = function (string $k, float $def) use ($posted, $f) {
    if ($posted !== null) {
        return (string) ($posted[$k . '_raw'] ?? '');
    }
    $v = isset($f[$k]) ? (float) $f[$k] : $def;
    return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
};
$numText = function ($v): string {
    return $v === '' || $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
};
$blank = ['item_name' => '', 'qty' => '', 'unit' => '', 'price' => ''];
$rows = $items ?: [$blank];
$backUrl = BASE_URL . '/pengajuan_fee' . ($isEdit ? '/detail/' . (int) $f['id'] : '');
$renderRow = function (array $it) use ($numText) {
    $qty = $it['qty_raw'] ?? $numText($it['qty']);
    $price = $it['price_raw'] ?? $numText($it['price']);
    ob_start(); ?>
    <tr class="fee-row">
        <td data-label="No" class="fee-no text-muted text-center"></td>
        <td data-label="Barang"><input type="text" name="item_name[]" class="form-control form-control-sm" maxlength="255" autocomplete="off" placeholder="mis. Flood Light HLS FD11 10W" value="<?= e($it['item_name']) ?>"></td>
        <td data-label="Qty"><input type="text" name="qty[]" class="form-control form-control-sm text-end fee-qty" inputmode="decimal" placeholder="0" value="<?= e($qty) ?>"></td>
        <td data-label="Satuan"><input type="text" name="unit[]" class="form-control form-control-sm" maxlength="30" autocomplete="off" placeholder="Pcs" value="<?= e($it['unit'] ?? '') ?>"></td>
        <td data-label="Harga"><input type="text" name="price[]" class="form-control form-control-sm text-end fee-price" inputmode="decimal" placeholder="0" value="<?= e($price) ?>"></td>
        <td data-label="Total" class="text-end fee-total text-nowrap">0</td>
        <td data-label="Fee" class="text-end fee-fee text-nowrap">0</td>
        <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger fee-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
    <?php
    return ob_get_clean();
};
?>
<style>
    @media (min-width: 768px) {
        #feeTable { min-width: 1000px; }
        #feeTable td { vertical-align: middle; }
    }
</style>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Buat' ?> Pengajuan Fee</h4>
        <small class="text-muted">No.: <strong><?= e($number) ?></strong> <?= $isEdit ? '' : '(otomatis; nomor resmi dibuat saat disimpan)' ?></small>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=pengajuan_fee&action=<?= $action ?>" id="feeForm" novalidate>
    <?= csrfField() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-3">Pengajuan Fee</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Invoice no.</label>
                    <input type="text" name="invoice_number" class="form-control" maxlength="60" placeholder="mis. JL00001545" value="<?= e($h('invoice_number')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Customer</label>
                    <input type="text" name="customer_name" class="form-control" maxlength="150" value="<?= e($h('customer_name')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sales</label>
                    <input type="text" name="sales_name" class="form-control" maxlength="150" list="feeSales" autocomplete="off" value="<?= e($h('sales_name')) ?>">
                    <datalist id="feeSales"><?php foreach ($salesList as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status Tagihan</label>
                    <input type="text" name="billing_status" class="form-control" maxlength="120" placeholder="mis. Lunas (04/09/2026)" value="<?= e($h('billing_status')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Penerima Fee</label>
                    <input type="text" name="recipient_name" class="form-control" maxlength="150" list="feeRecipients" autocomplete="off" value="<?= e($h('recipient_name')) ?>">
                    <datalist id="feeRecipients"><?php foreach ($recipients as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label">NPWP / NIK</label>
                    <input type="text" name="recipient_tax_id" class="form-control" maxlength="40" inputmode="numeric" value="<?= e($h('recipient_tax_id')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="fee_date" class="form-control" required value="<?= e($h('fee_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kota</label>
                    <input type="text" name="city" class="form-control" maxlength="100" list="feeCities" autocomplete="off" value="<?= e($h('city')) ?>">
                    <datalist id="feeCities"><?php foreach ($cities as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="card-section-title mb-0">Barang</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="feeAddRow" data-inline-add="#feeItemsBody"><i class="bi bi-plus-circle"></i> Tambah Baris</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0" id="feeTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">No</th>
                            <th>Barang <span class="text-danger">*</span></th>
                            <th style="width:110px" class="text-end">Qty <span class="text-danger">*</span></th>
                            <th style="width:100px">Satuan</th>
                            <th style="width:150px" class="text-end">Harga <span class="text-danger">*</span></th>
                            <th style="width:150px" class="text-end">Total</th>
                            <th style="width:150px" class="text-end">Fee</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody id="feeItemsBody">
                        <?php foreach ($rows as $it): echo $renderRow($it); endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-text mt-2">Total = Harga &times; Qty. Fee = (Total &divide; (1 + PPN%)) &times; Fee% &mdash; &ldquo;Fee dari DPP&rdquo;, sama dengan rumus Excel.</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="card-section-title mb-2">Tarif</div>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label small mb-1">Fee % (dari DPP)</label>
                            <input type="text" name="fee_percent" id="feePct" class="form-control form-control-sm text-end" inputmode="decimal" value="<?= e($pctText('fee_percent', 10.0)) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">PPN %</label>
                            <input type="text" name="ppn_percent" id="ppnPct" class="form-control form-control-sm text-end" inputmode="decimal" value="<?= e($pctText('ppn_percent', 11.0)) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">Potong PPh %</label>
                            <input type="text" name="pph_percent" id="pphPct" class="form-control form-control-sm text-end" inputmode="decimal" value="<?= e($pctText('pph_percent', 2.5)) ?>">
                        </div>
                    </div>
                    <div class="form-text">Bawaan 10 / 11 / 2,5 sama dengan angka di rumus Excel; ubah hanya bila tarifnya memang berbeda.</div>
                    <div class="row g-2 mt-2">
                        <div class="col-12">
                            <label class="form-label small mb-1">NOTE <span class="text-muted">(mis. Fee : Lampu 963.000 / pcs)</span></label>
                            <input type="text" name="note" class="form-control form-control-sm" maxlength="500" value="<?= e($h('note')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">NB <span class="text-muted">(kosong = &ldquo;Fee 10% dari DPP&rdquo;)</span></label>
                            <input type="text" name="nb" class="form-control form-control-sm" maxlength="500" value="<?= e($h('nb')) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between py-1"><span>TOTAL</span><strong id="feeSumTotal">0</strong></div>
                    <div class="d-flex justify-content-between py-1"><span>TOTAL FEE</span><strong id="feeSumFee">0</strong></div>
                    <div class="d-flex justify-content-between py-1"><span>Potong Pph <span id="pphLabel">2,5%</span></span><span id="feePph">0</span></div>
                    <div class="d-flex justify-content-between py-1 border-top"><strong>Total Fee diterima</strong><strong class="fs-5" id="feeNet">0</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan sebagai Draft</button>
        <?php if ($canSubmit): ?>
            <button type="submit" name="submit_after" value="1" class="btn btn-success"><i class="bi bi-send"></i> Simpan &amp; Ajukan</button>
        <?php endif; ?>
        <a href="<?= e($backUrl) ?>" class="btn btn-light border">Batal</a>
    </div>
</form>

<template id="feeRowTemplate"><?= $renderRow($blank) ?></template>

<script>
(function () {
    const body = document.getElementById('feeItemsBody');
    const tpl = document.getElementById('feeRowTemplate');
    const form = document.getElementById('feeForm');

    // Sama dengan parseCurrencyInput() di server: koma = ribuan, titik = desimal.
    function num(v) { const n = parseFloat(String(v || '').replace(/,/g, '').trim()); return isNaN(n) ? 0 : n; }
    function pct(id, def) { const raw = document.getElementById(id).value.trim().replace(',', '.'); return raw === '' ? def : (parseFloat(raw) || 0); }
    function r2(x) { return Math.round((x + Number.EPSILON) * 100) / 100; }
    function fmt(n) { return (n < 0 ? '-' : '') + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }

    function recalc() {
        const feeP = pct('feePct', 10), ppnP = pct('ppnPct', 11), pphP = pct('pphPct', 2.5);
        let sumTotal = 0, sumFee = 0;
        body.querySelectorAll('.fee-row').forEach(function (tr, i) {
            tr.querySelector('.fee-no').textContent = i + 1;
            const total = r2(num(tr.querySelector('.fee-qty').value) * num(tr.querySelector('.fee-price').value));      // =G*E
            const fee = r2((total / (1 + ppnP / 100)) * (feeP / 100));                                                   // =(H/1.11)*10%
            tr.querySelector('.fee-total').textContent = fmt(total);
            tr.querySelector('.fee-fee').textContent = fmt(fee);
            sumTotal += total; sumFee += fee;
        });
        sumTotal = r2(sumTotal); sumFee = r2(sumFee);
        const pph = r2(sumFee * (pphP / 100));                                                                           // =-I18*H19
        document.getElementById('feeSumTotal').textContent = fmt(sumTotal);
        document.getElementById('feeSumFee').textContent = fmt(sumFee);
        document.getElementById('feePph').textContent = fmt(-pph);
        document.getElementById('pphLabel').textContent = String(pphP).replace('.', ',') + '%';
        document.getElementById('feeNet').textContent = fmt(r2(sumFee - pph));
    }

    document.getElementById('feeAddRow').addEventListener('click', function () {
        body.appendChild(tpl.content.cloneNode(true));
        recalc();
    });
    body.addEventListener('click', function (e) {
        const btn = e.target.closest('.fee-remove');
        if (!btn) { return; }
        if (body.querySelectorAll('.fee-row').length <= 1) {
            body.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        } else {
            btn.closest('.fee-row').remove();
        }
        recalc();
    });
    body.addEventListener('input', recalc);
    ['feePct', 'ppnPct', 'pphPct'].forEach(function (id) { document.getElementById(id).addEventListener('input', recalc); });
    // NB bawaan ("Fee 10% dari DPP") ikut berubah bersama Fee % selama belum diubah manual.
    const nbEl = form.querySelector('[name=nb]'), feeEl = document.getElementById('feePct');
    let prevAuto = 'Fee ' + String(pct('feePct', 10)) + '% dari DPP';
    feeEl.addEventListener('input', function () {
        const auto = 'Fee ' + String(pct('feePct', 10)) + '% dari DPP';
        if (nbEl.value.trim() === '' || nbEl.value.trim() === prevAuto) { nbEl.value = auto; }
        prevAuto = auto;
    });

    form.addEventListener('submit', function (e) {
        let msg = '';
        if (!form.fee_date.value) { msg = 'Tanggal wajib diisi.'; }
        let filled = 0;
        body.querySelectorAll('.fee-row').forEach(function (tr, i) {
            const name = tr.querySelector('[name="item_name[]"]').value.trim();
            const qty = tr.querySelector('.fee-qty').value.trim();
            const price = tr.querySelector('.fee-price').value.trim();
            const unit = tr.querySelector('[name="unit[]"]').value.trim();
            if (!name && !qty && !price && !unit) { return; }
            filled++;
            if (!msg) {
                if (!name) { msg = 'Baris ' + (i + 1) + ': Barang wajib diisi.'; }
                else if (!(num(qty) > 0)) { msg = 'Baris ' + (i + 1) + ': Qty harus lebih dari 0.'; }
                else if (!(num(price) > 0)) { msg = 'Baris ' + (i + 1) + ': Harga harus lebih dari 0.'; }
            }
        });
        if (!filled && !msg) { msg = 'Minimal harus ada 1 baris barang.'; }
        if (msg) { e.preventDefault(); alert(msg); }
    });
    recalc();
})();
</script>
