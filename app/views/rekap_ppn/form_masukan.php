<?php
/**
 * Form PPN Masukan (acuan: sheet "PPN Masukan"). Input: faktur, tanggal, NPWP, nama, masa/tahun, DPP, project, keterangan.
 * DPP Nilai Lain = DPP x 11/12, PPN = DPP Nilai Lain x 12%, Jumlah = DPP + PPN dihitung live (tampilan saja; server menghitung ulang).
 * Variabel: $mode, $r, $posted, $defMasa, $defTahun, $vendors, $npwpMap, $projects
 */
$isEdit = $mode === 'edit';
$h = fn(string $k, $def = '') => $posted[$k] ?? ($r[$k] ?? $def);
$dppText = $posted !== null ? (string) ($posted['dpp'] ?? '') : (isset($r['dpp']) ? rtrim(rtrim(number_format((float) $r['dpp'], 2, '.', ','), '0'), '.') : '');
$masaVal = (string) $h('masa', $defMasa);
$tahunVal = (string) $h('tahun', $defTahun);
$backUrl = BASE_URL . '/rekap_ppn?tab=masukan&' . http_build_query(['tahun' => (int) $tahunVal, 'masa' => (int) $masaVal]);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> PPN Masukan</h4>
        <small class="text-muted">Faktur pajak masukan dari vendor. DPP Nilai Lain, PPN, dan Jumlah dihitung otomatis.</small>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=rekap_ppn&action=<?= $isEdit ? 'updateMasukan' : 'storeMasukan' ?>" id="masukanForm" novalidate>
    <?= csrfField() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-3">Faktur Pajak Masukan</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Vendor <span class="text-danger">*</span></label>
                    <input type="text" name="vendor_name" id="vendorName" class="form-control" maxlength="150" list="vendorList" autocomplete="off" required value="<?= e($h('vendor_name')) ?>">
                    <datalist id="vendorList"><?php foreach ($vendors as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="col-md-6">
                    <label class="form-label">NPWP</label>
                    <input type="text" name="npwp" id="npwp" class="form-control" maxlength="30" inputmode="numeric" placeholder="mis. 0013050083055000" value="<?= e($h('npwp')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">No. Faktur <span class="text-danger">*</span></label>
                    <input type="text" name="faktur_number" class="form-control" maxlength="40" inputmode="numeric" required placeholder="mis. 04002600435206741" value="<?= e($h('faktur_number')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tanggal Faktur <span class="text-danger">*</span></label>
                    <input type="date" name="faktur_date" id="fakturDate" class="form-control" required value="<?= e($h('faktur_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Masa Pajak <span class="text-danger">*</span></label>
                    <select name="masa" id="masa" class="form-select">
                        <?php foreach (RekapPpn::MONTHS as $num => $name): ?>
                            <option value="<?= $num ?>" <?= (int) $masaVal === $num ? 'selected' : '' ?>><?= $num ?> &ndash; <?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Tahun <span class="text-danger">*</span></label>
                    <input type="text" name="tahun" id="tahun" class="form-control" maxlength="4" inputmode="numeric" required value="<?= e($tahunVal) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Project</label>
                    <input type="text" name="project_name" class="form-control" maxlength="150" list="projectList" autocomplete="off" value="<?= e($h('project_name')) ?>">
                    <datalist id="projectList"><?php foreach ($projects as $v): ?><option value="<?= e($v) ?>"><?php endforeach; ?></datalist>
                </div>
                <div class="col-12">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="notes" class="form-control" maxlength="500" value="<?= e($h('notes')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="card-section-title mb-2">Nilai</div>
                    <label class="form-label">DPP <span class="text-danger">*</span></label>
                    <input type="text" name="dpp" id="dpp" class="form-control text-end currency-input" inputmode="decimal" required placeholder="0" value="<?= e($dppText) ?>">
                    <div class="form-text">Dasar Pengenaan Pajak sesuai faktur pajak vendor.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between py-1"><span>DPP Nilai Lain <small class="text-muted">(DPP &times; 11/12)</small></span><strong id="calcDnl">0</strong></div>
                    <div class="d-flex justify-content-between py-1"><span>PPN <small class="text-muted">(DPP Nilai Lain &times; 12%)</small></span><strong id="calcPpn">0</strong></div>
                    <div class="d-flex justify-content-between py-1 border-top"><strong>Jumlah (DPP + PPN)</strong><strong class="fs-5" id="calcTotal">0</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= e($backUrl) ?>" class="btn btn-light border">Batal</a>
    </div>
</form>

<script>
(function () {
    const form = document.getElementById('masukanForm');
    const npwpMap = <?= json_encode($npwpMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const dppEl = document.getElementById('dpp');
    const dateEl = document.getElementById('fakturDate');
    const masaEl = document.getElementById('masa');
    const tahunEl = document.getElementById('tahun');
    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
    // Sama dengan parseCurrencyInput() di server: koma = ribuan, titik = desimal.
    function num(v) { const n = parseFloat(String(v || '').replace(/,/g, '').trim()); return isNaN(n) ? 0 : n; }
    function r2(x) { return Math.round((x + Number.EPSILON) * 100) / 100; }
    function fmt(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function recalc() {
        const dpp = num(dppEl.value);
        const ppn = r2(dpp * 11 / 100);
        document.getElementById('calcDnl').textContent = fmt(r2(dpp * 11 / 12));
        document.getElementById('calcPpn').textContent = fmt(ppn);
        document.getElementById('calcTotal').textContent = fmt(r2(dpp + ppn));
    }
    dppEl.addEventListener('input', recalc);

    // Masa & Tahun mengikuti tanggal faktur selama belum diubah manual (masa pajak boleh beda dari bulan faktur, seperti di Excel).
    let masaTouched = isEdit;
    masaEl.addEventListener('change', function () { masaTouched = true; });
    tahunEl.addEventListener('input', function () { masaTouched = true; });
    dateEl.addEventListener('change', function () {
        if (masaTouched || !dateEl.value) { return; }
        const p = dateEl.value.split('-');
        tahunEl.value = p[0];
        masaEl.value = String(parseInt(p[1], 10));
    });

    // NPWP terisi otomatis dari vendor yang pernah diinput (hanya bila NPWP masih kosong).
    const vendorEl = document.getElementById('vendorName'), npwpEl = document.getElementById('npwp');
    vendorEl.addEventListener('change', function () {
        if (!npwpEl.value.trim() && npwpMap[vendorEl.value]) { npwpEl.value = npwpMap[vendorEl.value]; }
    });

    form.addEventListener('submit', function (e) {
        let msg = '';
        if (!form.vendor_name.value.trim()) { msg = 'Nama vendor wajib diisi.'; }
        else if (!form.faktur_number.value.trim()) { msg = 'No. Faktur wajib diisi.'; }
        else if (!dateEl.value) { msg = 'Tanggal faktur wajib diisi.'; }
        else if (!/^\d{4}$/.test(tahunEl.value.trim())) { msg = 'Tahun pajak harus 4 digit.'; }
        else if (!(num(dppEl.value) > 0)) { msg = 'DPP harus lebih dari 0.'; }
        if (msg) { e.preventDefault(); alert(msg); }
    });
    recalc();
})();
</script>
