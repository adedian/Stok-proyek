<?php
/**
 * Form PPN Keluaran (acuan: sheet "PPN Keluaran"). Header: tanggal, invoice, faktur pajak, customer, NPWP, keterangan.
 * Baris: nama barang, qty, pricelist (opsional), harga satuan (SUDAH termasuk PPN 11%). Subtotal = Harga x Qty,
 * DPP = Subtotal / 1,11, DPP Nilai Lain = DPP x 11/12, PPN = Subtotal - DPP dihitung live (tampilan saja; server menghitung ulang).
 * Customer = dropdown Master Client (+ "+ Tambah baru"); NPWP terisi otomatis dari master. Dua tombol simpan: Draft (hanya butuh
 * tanggal + customer) dan Lengkap (wajib No. Faktur Pajak, NPWP, dan >= 1 barang).
 * Variabel: $mode, $k, $items, $posted, $clients, $canQuickAdd
 */
$isEdit = $mode === 'edit';
$h = fn(string $key, $def = '') => $posted[$key] ?? ($k[$key] ?? $def);
$numText = function ($v): string {
    return $v === '' || $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
};
$blank = ['item_name' => '', 'qty' => '', 'pricelist' => '', 'unit_price' => ''];
$rows = $items ?: [$blank];
$dateVal = (string) $h('entry_date', date('Y-m-d'));
// Customer lama yang belum ada di master tampil sebagai opsi "(belum di master)" bernilai 0; namanya dibawa di hidden customer_legacy.
$cliSel = $h('client_id', '');
$customerLegacy = ($cliSel === '' || $cliSel === null) ? (string) ($posted !== null ? ($posted['customer_legacy'] ?? '') : ($k['customer_name'] ?? '')) : '';
$npwpMap = [];
foreach ($clients as $o) {
    $npwpMap[(string) $o['id']] = $o['npwp'];
}
$curStatus = $posted['status'] ?? ($k['status'] ?? '');
$backUrl = BASE_URL . '/rekap_ppn?tab=keluaran&' . http_build_query(['tahun' => (int) substr($dateVal, 0, 4), 'masa' => (int) substr($dateVal, 5, 2)]);
$renderRow = function (array $it) use ($numText) {
    $qty = $it['qty_raw'] ?? $numText($it['qty']);
    $list = $it['list_raw'] ?? $numText($it['pricelist'] ?? '');
    $price = $it['price_raw'] ?? $numText($it['unit_price']);
    ob_start(); ?>
    <tr class="kl-row">
        <td data-label="No" class="kl-no text-muted text-center"></td>
        <td data-label="Nama Barang"><input type="text" name="item_name[]" class="form-control form-control-sm" maxlength="255" autocomplete="off" placeholder="mis. LED Bulb 50W E27" value="<?= e($it['item_name']) ?>"></td>
        <td data-label="Qty"><input type="text" name="qty[]" class="form-control form-control-sm text-end currency-input kl-qty" inputmode="decimal" placeholder="0" value="<?= e($qty) ?>"></td>
        <td data-label="Pricelist"><input type="text" name="pricelist[]" class="form-control form-control-sm text-end currency-input kl-list" inputmode="decimal" placeholder="opsional" value="<?= e($list) ?>"></td>
        <td data-label="Harga Satuan"><input type="text" name="unit_price[]" class="form-control form-control-sm text-end currency-input kl-price" inputmode="decimal" placeholder="0" value="<?= e($price) ?>"></td>
        <td data-label="Diskon" class="text-end kl-disc text-nowrap">-</td>
        <td data-label="Subtotal" class="text-end kl-sub text-nowrap">0</td>
        <td data-label="DPP" class="text-end kl-dpp text-nowrap">0</td>
        <td data-label="PPN" class="text-end kl-ppn text-nowrap">0</td>
        <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger kl-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
    <?php
    return ob_get_clean();
};
?>
<style>
    @media (min-width: 768px) {
        #klTable { min-width: 1100px; }
        #klTable td { vertical-align: middle; }
    }
</style>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> PPN Keluaran</h4>
        <small class="text-muted">Faktur pajak keluaran ke customer. Harga satuan sudah termasuk PPN 11%; DPP &amp; PPN dihitung otomatis.</small>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=rekap_ppn&action=<?= $isEdit ? 'updateKeluaran' : 'storeKeluaran' ?>" id="keluaranForm" novalidate>
    <?= csrfField() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-3">Faktur Pajak Keluaran</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="entry_date" class="form-control" required value="<?= e($dateVal) ?>">
                    <div class="form-text">Bulan tanggal ini menjadi masa pajaknya.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice</label>
                    <input type="text" name="invoice_number" class="form-control" maxlength="60" placeholder="mis. 017/INV.HME/III/2026" value="<?= e($h('invoice_number')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Faktur Pajak</label>
                    <input type="text" name="faktur_number" class="form-control" maxlength="40" inputmode="numeric" placeholder="mis. 04002600111941818" value="<?= e($h('faktur_number')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nama Customer <span class="text-danger">*</span></label>
                    <select name="client_id" id="client_id" class="form-select" required<?= $canQuickAdd ? ' data-quick-add="client"' : '' ?>>
                        <option value="">-- Pilih Customer --</option>
                        <?php if ($customerLegacy !== ''): ?>
                            <option value="0" selected><?= e($customerLegacy) ?> (belum di master)</option>
                        <?php endif; ?>
                        <?php foreach ($clients as $o): ?>
                            <option value="<?= (int) $o['id'] ?>" data-npwp="<?= e($o['npwp']) ?>" <?= (string) $cliSel === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="customer_legacy" value="<?= e($customerLegacy) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">NPWP <span class="text-muted small">(terisi otomatis dari Master Customer)</span></label>
                    <input type="text" name="npwp" id="npwp" class="form-control" maxlength="30" inputmode="numeric" value="<?= e($h('npwp') !== '' && $h('npwp') !== null ? $h('npwp') : ($posted === null ? ($npwpMap[(string) $cliSel] ?? '') : '')) ?>">
                    <div class="form-check mt-1 d-none" id="saveNpwpWrap">
                        <input class="form-check-input" type="checkbox" name="save_npwp_master" value="1" id="saveNpwpMaster">
                        <label class="form-check-label small" for="saveNpwpMaster">Customer ini belum punya NPWP di master &mdash; simpan NPWP ini ke Master Customer</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="notes" class="form-control" maxlength="500" placeholder="mis. Dibayar 30%" value="<?= e($h('notes')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="card-section-title mb-0">Barang</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="klAddRow" data-inline-add="#klItemsBody"><i class="bi bi-plus-circle"></i> Tambah Baris</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0" id="klTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">No</th>
                            <th>Nama Barang <span class="text-danger">*</span></th>
                            <th style="width:100px" class="text-end">Qty <span class="text-danger">*</span></th>
                            <th style="width:140px" class="text-end">Pricelist</th>
                            <th style="width:150px" class="text-end">Harga Satuan <span class="text-danger">*</span></th>
                            <th style="width:80px" class="text-end">Diskon</th>
                            <th style="width:140px" class="text-end">Subtotal</th>
                            <th style="width:140px" class="text-end">DPP</th>
                            <th style="width:130px" class="text-end">PPN</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="klItemsBody">
                        <?php foreach ($rows as $it): echo $renderRow($it); endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-text mt-2">Harga satuan sudah termasuk PPN 11%. Pricelist hanya dipakai untuk menampilkan persentase diskon (boleh dikosongkan).</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6 ms-lg-auto">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between py-1"><span>DPP</span><strong id="sumDpp">0</strong></div>
                    <div class="d-flex justify-content-between py-1"><span>DPP Nilai Lain <small class="text-muted">(DPP &times; 11/12)</small></span><strong id="sumDnl">0</strong></div>
                    <div class="d-flex justify-content-between py-1"><span>PPN <small class="text-muted">(DPP Nilai Lain &times; 12%)</small></span><strong id="sumPpn">0</strong></div>
                    <div class="d-flex justify-content-between py-1 border-top"><strong>Total</strong><strong class="fs-5" id="sumTotal">0</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap align-items-center">
        <button type="submit" name="save_mode" value="lengkap" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan (Lengkap)</button>
        <button type="submit" name="save_mode" value="draft" class="btn btn-outline-warning text-dark" id="saveDraftBtn"><i class="bi bi-file-earmark"></i> Simpan Draft</button>
        <a href="<?= e($backUrl) ?>" class="btn btn-light border">Batal</a>
        <?php if ($isEdit && $curStatus === 'draft'): ?><span class="badge bg-warning text-dark">Status sekarang: Draft</span><?php endif; ?>
    </div>
    <div class="form-text mt-2"><strong>Lengkap</strong> wajib punya No. Faktur Pajak, NPWP, dan minimal 1 barang, serta ikut dihitung di Ringkasan PPN. <strong>Draft</strong> hanya butuh tanggal &amp; customer dan belum dihitung.</div>
</form>

<?php if ($canQuickAdd): ?>
    <?php $quickAddVia = 'rekap_ppn'; $quickAddClientTargetId = 'client_id'; ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_client_modal.php'; ?>
<?php endif; ?>

<template id="klRowTemplate"><?= $renderRow($blank) ?></template>

<script>
(function () {
    const body = document.getElementById('klItemsBody');
    const tpl = document.getElementById('klRowTemplate');
    const form = document.getElementById('keluaranForm');
    const npwpMap = <?= json_encode($npwpMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const cliEl = document.getElementById('client_id'), npwpEl = document.getElementById('npwp');
    const saveWrap = document.getElementById('saveNpwpWrap'), saveChk = document.getElementById('saveNpwpMaster');

    // Sama dengan parseCurrencyInput() di server: koma = ribuan, titik = desimal.
    function num(v) { const n = parseFloat(String(v || '').replace(/,/g, '').trim()); return isNaN(n) ? 0 : n; }
    function r2(x) { return Math.round((x + Number.EPSILON) * 100) / 100; }
    function fmt(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }

    function recalc() {
        let sDpp = 0, sDnl = 0, sPpn = 0, sTot = 0;
        body.querySelectorAll('.kl-row').forEach(function (tr, i) {
            tr.querySelector('.kl-no').textContent = i + 1;
            const qty = num(tr.querySelector('.kl-qty').value), price = num(tr.querySelector('.kl-price').value), list = num(tr.querySelector('.kl-list').value);
            const sub = r2(qty * price);
            const dpp = r2(sub / 1.11);
            const ppn = r2(sub - dpp);
            const dnl = r2(dpp * 11 / 12);
            const disc = list > 0 ? r2((list - price) / list * 100) : null;
            tr.querySelector('.kl-sub').textContent = fmt(sub);
            tr.querySelector('.kl-dpp').textContent = fmt(dpp);
            tr.querySelector('.kl-ppn').textContent = fmt(ppn);
            tr.querySelector('.kl-disc').textContent = disc === null ? '-' : (Math.abs(disc) < 0.01 ? '0%' : fmt(disc) + '%');
            sDpp += dpp; sDnl += dnl; sPpn += ppn; sTot += sub;
        });
        document.getElementById('sumDpp').textContent = fmt(r2(sDpp));
        document.getElementById('sumDnl').textContent = fmt(r2(sDnl));
        document.getElementById('sumPpn').textContent = fmt(r2(sPpn));
        document.getElementById('sumTotal').textContent = fmt(r2(sTot));
    }

    document.getElementById('klAddRow').addEventListener('click', function () {
        body.appendChild(tpl.content.cloneNode(true));
        recalc();
    });
    body.addEventListener('click', function (e) {
        const btn = e.target.closest('.kl-remove');
        if (!btn) { return; }
        if (body.querySelectorAll('.kl-row').length <= 1) {
            body.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        } else {
            btn.closest('.kl-row').remove();
        }
        recalc();
    });
    body.addEventListener('input', recalc);

    // NPWP terisi otomatis dari Master Customer yang dipilih (opsi hasil "Tambah baru" membawa data-npwp sendiri).
    function masterNpwp() {
        const opt = cliEl.options[cliEl.selectedIndex];
        if (!cliEl.value || cliEl.value === '0') { return null; }
        return (opt && opt.dataset.npwp !== undefined) ? opt.dataset.npwp : (npwpMap[cliEl.value] || '');
    }
    // Tawarkan "simpan NPWP ke master" hanya bila master customer belum punya NPWP tapi NPWP sudah diketik.
    function syncSaveNpwp() {
        const m = masterNpwp();
        const show = m === '' && npwpEl.value.trim() !== '';
        saveWrap.classList.toggle('d-none', !show);
        if (!show) { saveChk.checked = false; }
    }
    cliEl.addEventListener('change', function () {
        const m = masterNpwp();
        if (m !== null) { npwpEl.value = m; }
        syncSaveNpwp();
    });
    npwpEl.addEventListener('input', syncSaveNpwp);
    syncSaveNpwp();

    // "+ Tambah baru" di dasar dropdown -> modal Tambah Cepat Client dengan nama terisi dari kata yang diketik.
    form.addEventListener('quickadd', function (e) {
        const modalEl = document.getElementById('modalQuickAddClient');
        if (!modalEl) { return; }
        const nameInput = modalEl.querySelector('[name="client_name"]');
        if (nameInput) { nameInput.value = (e.detail && e.detail.term) || ''; }
        modalEl.addEventListener('shown.bs.modal', function () {
            const empty = Array.prototype.find.call(modalEl.querySelectorAll('input.form-control:not([type=hidden])'), function (i) { return !i.value && !i.readOnly; });
            (empty || nameInput || modalEl).focus();
        }, { once: true });
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    form.addEventListener('submit', function (e) {
        const isDraft = e.submitter && e.submitter.value === 'draft';
        let msg = '';
        if (!form.entry_date.value) { msg = 'Tanggal wajib diisi.'; }
        else if (!cliEl.value) { msg = 'Customer wajib dipilih.'; }
        else if (!isDraft && !form.faktur_number.value.trim()) { msg = 'No. Faktur Pajak wajib diisi untuk Simpan (Lengkap) -- atau pakai Simpan Draft.'; }
        else if (!isDraft && !npwpEl.value.trim()) { msg = 'NPWP wajib diisi untuk Simpan (Lengkap) -- atau pakai Simpan Draft.'; }
        let filled = 0;
        body.querySelectorAll('.kl-row').forEach(function (tr, i) {
            const name = tr.querySelector('[name="item_name[]"]').value.trim();
            const qty = tr.querySelector('.kl-qty').value.trim();
            const price = tr.querySelector('.kl-price').value.trim();
            const list = tr.querySelector('.kl-list').value.trim();
            if (!name && !qty && !price && !list) { return; }
            filled++;
            if (!msg) {
                if (!name) { msg = 'Baris ' + (i + 1) + ': Nama barang wajib diisi.'; }
                else if (!(num(qty) > 0)) { msg = 'Baris ' + (i + 1) + ': Qty harus lebih dari 0.'; }
                else if (!(num(price) > 0)) { msg = 'Baris ' + (i + 1) + ': Harga satuan harus lebih dari 0.'; }
            }
        });
        if (!filled && !msg && !isDraft) { msg = 'Minimal harus ada 1 baris barang untuk Simpan (Lengkap) -- atau pakai Simpan Draft.'; }
        if (msg) { e.preventDefault(); alert(msg); }
    });
    recalc();
})();
</script>
