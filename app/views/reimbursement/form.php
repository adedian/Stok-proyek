<?php
/**
 * Form Reimbursement ("LAPORAN BIAYA REIMBURSE" -- acuan Reimbursement.xlsx).
 * Header: Nama, Tanggal, Divisi, Kota, Tugas, B S (kasbon). Baris PENGELUARAN: Tanggal, Kategori (kelompok, mis. Akomodasi),
 * Keterangan, Jumlah (boleh penjumlahan seperti Excel, mis. 9500+9000+9500), Bukti (opsional).
 * Total dihitung live di sini hanya sebagai tampilan -- server menghitung & memvalidasi ulang.
 * Variabel: $mode, $r, $items, $posted, $number, $defaultName, $categories, $divisions, $cities, $canSubmit
 */
$isEdit = $mode === 'edit';
$action = $isEdit ? 'update' : 'store';
$h = fn(string $k, $def = '') => $posted[$k] ?? ($r[$k] ?? $def);
$dateVal = $h('reimb_date', date('Y-m-d'));
$nameVal = $h('employee_name', $defaultName);
$kasbonVal = $posted !== null ? ($posted['kasbon_raw'] ?? '') : ((float) ($r['kasbon_amount'] ?? 0) > 0 ? rtrim(rtrim(number_format((float) $r['kasbon_amount'], 2, '.', ','), '0'), '.') : '');
$blank = ['expense_date' => '', 'category_id' => '', 'description' => '', 'amount' => '', 'amount_expr' => null, 'proof_file' => null, 'id' => 0];
$rows = $items ?: [$blank];
$backUrl = BASE_URL . '/reimbursement' . ($isEdit ? '/detail/' . (int) $r['id'] : '');
$amtText = function (array $it): string {
    if (isset($it['amount_raw'])) {
        return (string) $it['amount_raw'];
    }
    if (!empty($it['amount_expr'])) {
        return (string) $it['amount_expr'];
    }
    return $it['amount'] === '' || $it['amount'] === null ? '' : rtrim(rtrim(number_format((float) $it['amount'], 2, '.', ','), '0'), '.');
};
$catSelect = function ($sel) use ($categories) {
    $o = '<option value="">-- Tanpa Kategori --</option>';
    foreach ($categories as $c) {
        $o .= '<option value="' . (int) $c['id'] . '"' . ((string) $sel === (string) $c['id'] ? ' selected' : '') . '>' . e($c['category_name']) . '</option>';
    }
    return $o;
};
$renderRow = function (array $it) use ($catSelect, $amtText) {
    $iid = (int) ($it['item_id'] ?? $it['id'] ?? 0);
    $proof = $it['proof_file'] ?? null;
    ob_start(); ?>
    <tr class="rb-row">
        <td data-label="No" class="rb-no text-muted text-center"></td>
        <td data-label="Tanggal"><input type="date" name="expense_date[]" class="form-control form-control-sm" value="<?= e($it['expense_date'] ?? '') ?>"></td>
        <td data-label="Kategori"><select name="category_id[]" class="form-select form-select-sm"><?= $catSelect((string) ($it['category_id'] ?? '')) ?></select></td>
        <td data-label="Keterangan"><input type="text" name="description[]" class="form-control form-control-sm" maxlength="255" autocomplete="off" placeholder="mis. tol, bensin (granmax)" value="<?= e($it['description'] ?? '') ?>"></td>
        <td data-label="Jumlah (Rp)">
            <input type="text" name="amount[]" class="form-control form-control-sm text-end rb-amount" inputmode="text" autocomplete="off" placeholder="0 atau 9500+9000" value="<?= e($amtText($it)) ?>">
            <div class="small text-muted text-end rb-amount-sum"></div>
        </td>
        <td data-label="Bukti (opsional)">
            <input type="hidden" name="item_id[]" value="<?= $iid ?: '' ?>">
            <input type="hidden" name="proof_del[]" class="rb-proof-del" value="0">
            <?php if ($proof): ?>
                <div class="small rb-proof-cur mb-1"><a href="<?= e(fileUrl($proof)) ?>" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> Bukti tersimpan</a>
                    <label class="ms-2 text-danger"><input type="checkbox" class="rb-proof-rm"> hapus</label></div>
            <?php endif; ?>
            <input type="file" name="proof_file[]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.pdf">
        </td>
        <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger rb-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
    <?php
    return ob_get_clean();
};
?>
<style>
    @media (min-width: 768px) {
        #rbTable { min-width: 1100px; table-layout: fixed; }
        #rbTable td { vertical-align: top; }
    }
</style>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Buat' ?> Reimbursement</h4>
        <small class="text-muted">Laporan Biaya Reimburse &middot; No.: <strong><?= e($number) ?></strong> <?= $isEdit ? '' : '(otomatis; nomor resmi dibuat saat disimpan)' ?></small>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=reimbursement&action=<?= $action ?>" id="rbForm" enctype="multipart/form-data" novalidate>
    <?= csrfField() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-3">Laporan Biaya Reimburse</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nama <span class="text-danger">*</span></label>
                    <input type="text" name="employee_name" class="form-control" maxlength="150" required value="<?= e($nameVal) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="reimb_date" class="form-control" required value="<?= e($dateVal) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">B S <span class="text-muted small">(kasbon / bon sementara, opsional)</span></label>
                    <input type="text" name="kasbon_amount" id="rbKasbon" class="form-control text-end currency-input" inputmode="decimal" placeholder="0" value="<?= e($kasbonVal) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Divisi</label>
                    <input type="text" name="division" class="form-control" maxlength="100" list="rbDivisions" autocomplete="off" value="<?= e($h('division')) ?>">
                    <datalist id="rbDivisions"><?php foreach ($divisions as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kota</label>
                    <input type="text" name="city" class="form-control" maxlength="100" list="rbCities" autocomplete="off" value="<?= e($h('city')) ?>">
                    <datalist id="rbCities"><?php foreach ($cities as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tugas</label>
                    <input type="text" name="task" class="form-control" maxlength="255" value="<?= e($h('task')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="card-section-title mb-0">Pengeluaran</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="rbAddRow" data-inline-add="#rbItemsBody"><i class="bi bi-plus-circle"></i> Tambah Baris</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0" id="rbTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">No</th>
                            <th style="width:150px">Tanggal <span class="text-danger">*</span></th>
                            <th style="width:190px">Kategori</th>
                            <th>Keterangan <span class="text-danger">*</span></th>
                            <th style="width:210px" class="text-end">Jumlah <span class="text-danger">*</span></th>
                            <th style="width:230px">Bukti (opsional)</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody id="rbItemsBody">
                        <?php foreach ($rows as $it): echo $renderRow($it); endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-text mt-2">Kolom Jumlah boleh diisi penjumlahan seperti di Excel (mis. <code>9500+9000+9500+3000+6000</code>) &mdash; hasilnya tampil di bawah kolom. Bukti: JPG/PNG/WEBP/PDF, maks. 5 MB per baris. Kategori dipakai mengelompokkan baris di cetakan (mis. &ldquo;Akomodasi&rdquo;).</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between py-1"><span>TOTAL PENGELUARAN</span><strong id="rbTotal">Rp 0</strong></div>
            <div class="d-flex justify-content-between py-1"><span>B S (kasbon)</span><span id="rbKas">Rp 0</span></div>
            <div class="d-flex justify-content-between py-1 border-top"><strong>TOTAL KASBON &minus; PENGELUARAN</strong><strong id="rbNet">Rp 0</strong></div>
            <div class="small text-muted mt-1" id="rbNetNote"></div>
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

<template id="rbRowTemplate"><?= $renderRow($blank) ?></template>

<script>
(function () {
    const body = document.getElementById('rbItemsBody');
    const tpl = document.getElementById('rbRowTemplate');
    const form = document.getElementById('rbForm');
    const kasbonEl = document.getElementById('rbKasbon');

    // Sama dengan Reimbursement::parseAmount() di server: koma = ribuan, titik = desimal, "+" = jumlahkan.
    function parseAmt(raw) {
        raw = String(raw || '').trim();
        if (!/^[0-9][0-9.,]*(\s*\+\s*[0-9][0-9.,]*)*$/.test(raw)) { return null; }
        let sum = 0;
        raw.split('+').forEach(function (t) { sum += parseFloat(t.replace(/,/g, '').trim()) || 0; });
        return Math.round(sum * 100) / 100;
    }
    function num(v) { const n = parseFloat(String(v || '').replace(/,/g, '')); return isNaN(n) ? 0 : n; }
    function rp(n) { return (n < 0 ? '-' : '') + 'Rp ' + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }

    function recalc() {
        let total = 0;
        body.querySelectorAll('.rb-row').forEach(function (tr, i) {
            tr.querySelector('.rb-no').textContent = i + 1;
            const inp = tr.querySelector('.rb-amount');
            const hint = tr.querySelector('.rb-amount-sum');
            const v = parseAmt(inp.value);
            if (v === null) { hint.textContent = inp.value.trim() === '' ? '' : 'format tidak valid'; hint.classList.toggle('text-danger', inp.value.trim() !== ''); return; }
            hint.classList.remove('text-danger');
            hint.textContent = String(inp.value).indexOf('+') >= 0 ? '= ' + rp(v) : '';
            total += v;
        });
        const kas = num(kasbonEl.value);
        const net = Math.round((kas - total) * 100) / 100; // TOTAL KASBON - PENGELUARAN
        document.getElementById('rbTotal').textContent = rp(total);
        document.getElementById('rbKas').textContent = rp(kas);
        // Excel: baris ini = kasbon - pengeluaran; bila B S kosong nilainya = total pengeluaran (persis seperti Excel).
        document.getElementById('rbNet').textContent = rp(kas > 0 ? net : total);
        const note = document.getElementById('rbNetNote');
        if (kas <= 0) { note.textContent = total > 0 ? 'Tanpa kasbon: ' + rp(total) + ' diteruskan ke Pengajuan Budget setelah disetujui.' : ''; }
        else if (total > kas) { note.textContent = 'Kurang bayar ' + rp(total - kas) + ' akan diteruskan ke Pengajuan Budget setelah disetujui.'; }
        else { note.textContent = 'Kasbon ≥ pengeluaran: tidak ada kurang bayar, tidak diteruskan ke Pengajuan Budget.'; }
    }

    document.getElementById('rbAddRow').addEventListener('click', function () {
        body.appendChild(tpl.content.cloneNode(true));
        recalc();
    });
    body.addEventListener('click', function (e) {
        const btn = e.target.closest('.rb-remove');
        if (!btn) { return; }
        if (body.querySelectorAll('.rb-row').length <= 1) {
            body.querySelectorAll('input:not([type=hidden]), textarea').forEach(function (i) { if (i.type === 'checkbox') { i.checked = false; } else { i.value = ''; } });
            body.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; if (window.resyncSearchableSelect) { window.resyncSearchableSelect(s); } });
            body.querySelectorAll('input[name="item_id[]"]').forEach(function (i) { i.value = ''; });
            body.querySelectorAll('.rb-proof-cur').forEach(function (d) { d.remove(); });
        } else {
            btn.closest('.rb-row').remove();
        }
        recalc();
    });
    body.addEventListener('change', function (e) {
        if (e.target.classList.contains('rb-proof-rm')) {
            e.target.closest('td').querySelector('.rb-proof-del').value = e.target.checked ? '1' : '0';
        }
    });
    body.addEventListener('input', recalc);
    kasbonEl.addEventListener('input', recalc);

    form.addEventListener('submit', function (e) {
        let msg = '';
        if (!form.employee_name.value.trim()) { msg = 'Nama wajib diisi.'; }
        if (!form.reimb_date.value) { msg = msg || 'Tanggal wajib diisi.'; }
        let filled = 0;
        body.querySelectorAll('.rb-row').forEach(function (tr, i) {
            const d = tr.querySelector('[name="expense_date[]"]').value;
            const desc = tr.querySelector('[name="description[]"]').value.trim();
            const amt = tr.querySelector('.rb-amount').value.trim();
            const cat = tr.querySelector('[name="category_id[]"]').value;
            if (!d && !desc && !amt && !cat) { return; }
            filled++;
            if (!msg) {
                if (!d) { msg = 'Baris ' + (i + 1) + ': Tanggal wajib diisi.'; }
                else if (!desc) { msg = 'Baris ' + (i + 1) + ': Keterangan wajib diisi.'; }
                else if (!(parseAmt(amt) > 0)) { msg = 'Baris ' + (i + 1) + ': Jumlah harus angka lebih dari 0 (boleh penjumlahan, mis. 9500+9000).'; }
            }
        });
        if (!filled && !msg) { msg = 'Minimal harus ada 1 baris pengeluaran.'; }
        if (msg) { e.preventDefault(); alert(msg); }
    });
    recalc();
})();
</script>
