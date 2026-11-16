<?php
/**
 * Form Edit Saldo Bank (manual). Variabel: $bank, $date, $current (balanceAt), $posted, $backQuery.
 * Menyimpan CATATAN saldo manual terpisah -- tidak membuat/mengubah transaksi Bank/Kas.
 */
$back = BASE_URL . '/index.php?' . http_build_query(['module' => 'pengajuan_budget_report'] + $backQuery);
$jenisLabel = ['loan' => 'Loan', 'hr' => 'HR', 'kas' => 'Kas'][$bank['jenis']] ?? $bank['jenis'];
$rq = [];
foreach ($backQuery as $k => $v) { $rq['r_' . $k] = $v; }
$curSaldo = (float) $current['saldo'];
$fmt = fn($v) => number_format((float) $v, 2, '.', ',');
$newVal = $posted['new_balance'] ?? '';
$noteVal = $posted['note'] ?? '';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Edit Saldo Bank</h4>
        <small class="text-muted">Catatan saldo manual untuk Laporan Pengajuan Budget &mdash; <strong>tidak</strong> mengubah transaksi Bank/Kas dan tidak membuat transaksi baru</small>
    </div>
    <a href="<?= e($back) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=pengajuan_budget_report&action=saveSaldo" id="saldoForm" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="bank_id" value="<?= (int) $bank['id'] ?>">
    <input type="hidden" name="confirmed" id="confirmed" value="">
    <?php foreach ($rq as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Rekening</label>
                    <input type="text" class="form-control" readonly value="<?= e(MasterBank::reportLabel($bank)) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jenis / Kategori</label>
                    <input type="text" class="form-control" readonly value="<?= e($jenisLabel) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pengguna yang Mengubah</label>
                    <input type="text" class="form-control" readonly value="<?= e(currentUserName()) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tanggal Berlaku <span class="text-danger">*</span></label>
                    <input type="date" name="effective_date" id="effDate" class="form-control" required max="<?= e(date('Y-m-d')) ?>" value="<?= e($date) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Saldo Saat Ini <small class="text-muted">(pada tanggal berlaku)</small></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" id="curSaldo" class="form-control text-end" readonly value="<?= e($fmt($curSaldo)) ?>">
                    </div>
                    <div class="form-text" id="curSource"><?= !empty($current['manual']) ? 'Berdasarkan saldo manual berlaku ' . e(date('d/m/Y', strtotime($current['manual']['effective_date']))) . ' + mutasi Bank sesudahnya.' : 'Dihitung dari transaksi Bank (belum ada saldo manual).' ?></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Saldo Baru <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" name="new_balance" id="newSaldo" class="form-control text-end currency-input" inputmode="decimal" autocomplete="off" required placeholder="0" value="<?= e($newVal) ?>">
                    </div>
                    <div class="form-text" id="diffInfo">Selisih: -</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Keterangan Perubahan <span class="text-danger">*</span></label>
                    <textarea name="note" id="note" class="form-control" rows="2" maxlength="500" required placeholder="mis. Penyesuaian sesuai mutasi rekening koran BCA tgl. 16/11/2026"><?= e($noteVal) ?></textarea>
                </div>
            </div>
            <div class="alert alert-info small mt-3 mb-0">
                Saldo Baru menjadi dasar saldo awal laporan Pengajuan Budget mulai Tanggal Berlaku. Transaksi Bank Masuk/Keluar sesudah tanggal itu tetap ikut dihitung.
                Setiap perubahan tersimpan di <strong>Riwayat Saldo</strong> dan tidak bisa dihapus.
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Saldo</button>
        <a href="<?= e($back) ?>" class="btn btn-light border">Batal</a>
        <a href="<?= BASE_URL ?>/index.php?<?= e(http_build_query(['module' => 'pengajuan_budget_report', 'action' => 'saldoHistory', 'bank_id' => $bank['id']] + $rq)) ?>" class="btn btn-outline-secondary ms-auto"><i class="bi bi-clock-history"></i> Riwayat Saldo</a>
    </div>
</form>

<script>
(function () {
    const form = document.getElementById('saldoForm');
    const eff = document.getElementById('effDate');
    const cur = document.getElementById('curSaldo');
    const src = document.getElementById('curSource');
    const nb = document.getElementById('newSaldo');
    const diff = document.getElementById('diffInfo');
    let curVal = <?= json_encode($curSaldo) ?>;

    // Sama dengan parseCurrencyInput() server: koma = ribuan, titik = desimal.
    function num(v) { v = String(v || '').replace(/,/g, '').trim(); const n = parseFloat(v); return isNaN(n) ? null : n; }
    function fmt(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function showDiff() {
        const n = num(nb.value);
        if (n === null) { diff.textContent = 'Selisih: -'; diff.className = 'form-text'; return; }
        const d = n - curVal;
        diff.textContent = 'Selisih: ' + (d >= 0 ? '+' : '-') + 'Rp ' + fmt(Math.abs(d));
        diff.className = 'form-text ' + (d === 0 ? 'text-muted' : (d > 0 ? 'text-success' : 'text-danger'));
    }
    nb.addEventListener('input', showDiff);
    showDiff();

    eff.addEventListener('change', function () {
        if (!eff.value) { return; }
        fetch('<?= BASE_URL ?>/index.php?module=pengajuan_budget_report&action=balanceAt&bank_id=<?= (int) $bank['id'] ?>&date=' + encodeURIComponent(eff.value), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.error) { return; }
                curVal = j.saldo; cur.value = fmt(j.saldo);
                src.textContent = j.manual_date ? 'Berdasarkan saldo manual berlaku ' + j.manual_date.split('-').reverse().join('/') + ' + mutasi Bank sesudahnya.' : 'Dihitung dari transaksi Bank (belum ada saldo manual).';
                showDiff();
            });
    });

    form.addEventListener('submit', function (e) {
        if (form.dataset.ok) { return; }
        e.preventDefault();
        const n = num(nb.value);
        let msg = '';
        if (!eff.value) { msg = 'Tanggal Berlaku wajib diisi.'; }
        else if (n === null || !/^-?[0-9][0-9,]*(\.[0-9]{1,2})?$/.test(nb.value.trim())) { msg = 'Saldo Baru harus berupa angka yang valid.'; }
        else if (Math.abs(n) > 999999999999) { msg = 'Saldo Baru terlalu besar.'; }
        else if (n === curVal) { msg = 'Saldo Baru sama dengan Saldo Saat Ini -- tidak ada yang diubah.'; }
        else if (document.getElementById('note').value.trim().length < 3) { msg = 'Keterangan perubahan wajib diisi.'; }
        if (msg) { alert(msg); return; }
        const text = 'Ubah saldo <?= e(addslashes($bank['bank_name'])) ?> pada ' + eff.value.split('-').reverse().join('/') + ' dari Rp ' + fmt(curVal) + ' menjadi Rp ' + fmt(n) + '? Transaksi Kas/Bank tidak berubah.';
        const go = function () { document.getElementById('confirmed').value = '1'; form.dataset.ok = '1'; form.submit(); };
        if (typeof confirmAction === 'function') { confirmAction(text, 'Ya, simpan').then(function (ok) { if (ok) { go(); } }); }
        else if (confirm(text)) { go(); }
    });
})();
</script>
