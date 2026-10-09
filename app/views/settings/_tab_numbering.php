<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=saveNumbering">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Kode Purchase Order</label>
                    <input type="text" name="prefix_po" class="form-control" value="<?= e($numbering['prefix_po'] ?? 'PO.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_po'] ?? 'PO.HME') ?>/VIII/2026</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Penerimaan Barang</label>
                    <input type="text" name="prefix_gr" class="form-control" value="<?= e($numbering['prefix_gr'] ?? 'LPB.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_gr'] ?? 'LPB.HME') ?>/VIII/2026</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Stok Opname</label>
                    <input type="text" name="prefix_opn" class="form-control" value="<?= e($numbering['prefix_opn'] ?? 'SO.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_opn'] ?? 'SO.HME') ?>/VIII/2026</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Pengeluaran Barang</label>
                    <input type="text" name="prefix_sto" class="form-control" value="<?= e($numbering['prefix_sto'] ?? 'STO.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_sto'] ?? 'STO.HME') ?>/VIII/2026</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Pembelian Offline</label>
                    <input type="text" name="prefix_off" class="form-control" value="<?= e($numbering['prefix_off'] ?? 'OFF.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_off'] ?? 'OFF.HME') ?>/VIII/2026</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Invoice Keluar - Project</label>
                    <input type="text" name="prefix_sls" class="form-control" value="<?= e($numbering['prefix_sls'] ?? 'INV.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_sls'] ?? 'INV.HME') ?>/VIII/2026</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Invoice Keluar - Lampu</label>
                    <input type="text" name="prefix_fkt" class="form-control" value="<?= e($numbering['prefix_fkt'] ?? 'FKT.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_fkt'] ?? 'FKT.HME') ?>/VIII/2026</div>
                    <div class="form-text text-muted">Nomor urut Project &amp; Lampu terpisah (masing-masing mulai dari 001).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Surat Jalan</label>
                    <input type="text" name="prefix_sj" class="form-control" value="<?= e($numbering['prefix_sj'] ?? 'SJ.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_sj'] ?? 'SJ.HME') ?>/VIII/2026</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Tanda Terima</label>
                    <input type="text" name="prefix_tt" class="form-control" value="<?= e($numbering['prefix_tt'] ?? 'TT.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_tt'] ?? 'TT.HME') ?>/VIII/2026</div>
                </div>
            </div>
            <hr>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label mb-0 fw-semibold">Kode Pembayaran PO (per Sumber Dana)</label>
                    <div class="form-text mt-0">Nomor urut terpisah untuk masing-masing sumber dana (Bank/Kas Kecil/Kas Project).</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kode Bank (BK)</label>
                    <input type="text" name="prefix_pay_bk" class="form-control" value="<?= e($numbering['prefix_pay_bk'] ?? 'BK.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_pay_bk'] ?? 'BK.HME') ?>/VIII/2026</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kode Kas Kecil (KK)</label>
                    <input type="text" name="prefix_pay_kk" class="form-control" value="<?= e($numbering['prefix_pay_kk'] ?? 'KK.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_pay_kk'] ?? 'KK.HME') ?>/VIII/2026</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kode Kas Project (KKP)</label>
                    <input type="text" name="prefix_pay_kkp" class="form-control" value="<?= e($numbering['prefix_pay_kkp'] ?? 'KKP.HME') ?>">
                    <div class="form-text">Contoh: 001/<?= e($numbering['prefix_pay_kkp'] ?? 'KKP.HME') ?>/VIII/2026</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> Simpan Penomoran</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <h6 class="mb-1">Reset Nomor Urut</h6>
        <p class="text-muted small mb-3">
            Format nomor: <strong>No. Urut</strong>/<strong>Kode</strong>/<strong>Bulan</strong>/<strong>Tahun</strong>
            (mis. 147/PO.HME/X/2026). Di sini No. Urut, Bulan, dan Tahun pada nomor berikutnya bisa di-reset.
            <strong>Bulan/Tahun "Otomatis"</strong> mengikuti tanggal dokumen. Kalau diisi manual, SEMUA dokumen baru jenis itu
            memakai bulan/tahun yang diisi sampai Anda mengembalikannya ke "Otomatis". Kolom "Tahun dokumen" hanya
            penanda urutan (per tahun tanggal dokumen) dan tidak bisa diubah.
            Menurunkan nomor ke angka yang SUDAH pernah dipakai aman secara data -- dokumen baru dengan nomor
            bentrok otomatis gagal disimpan (nomor dokumen unik), tidak menimpa dokumen lama.
        </p>
        <?php
        $romanLabels = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];
        $curRoman = $romanLabels[(int) date('n')];
        ?>
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=saveCounters">
            <?= csrfField() ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards">
                    <thead class="table-light">
                        <tr>
                            <th>Jenis Dokumen</th>
                            <th style="width: 90px;">Tahun dokumen</th>
                            <th style="width: 130px;">No. Urut Berikutnya</th>
                            <th style="width: 130px;">Bulan</th>
                            <th style="width: 110px;">Tahun</th>
                            <th style="width: 230px;">Pratinjau nomor berikutnya</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($counters as $c):
                            $docType = $c['doc_type'];
                            $isPeriodType = (int) $c['year'] > 0 && isset(DocumentNumber::DOC_TYPE_CODE[$docType]);
                            $codeInfo = DocumentNumber::DOC_TYPE_CODE[$docType] ?? null;
                            $code = $codeInfo ? ($numbering[$codeInfo[0]] ?? $codeInfo[1]) : '';
                            $pm = $c['period_month'] !== null ? (int) $c['period_month'] : 0;
                            $py = $c['period_year'] !== null ? (int) $c['period_year'] : '';
                        ?>
                            <tr class="num-row" data-code="<?= e($code) ?>" data-year="<?= (int) $c['year'] ?>"
                                data-period="<?= $isPeriodType ? '1' : '0' ?>">
                                <td data-label="Jenis Dokumen">
                                    <?= e(DocumentNumber::label($docType)) ?>
                                    <?php if (!empty($c['virtual'])): ?><span class="badge text-bg-light border ms-1">belum pernah dipakai</span><?php endif; ?>
                                    <input type="hidden" name="doc_type[]" value="<?= e($docType) ?>">
                                </td>
                                <td data-label="Tahun dokumen">
                                    <?= (int) $c['year'] > 0 ? (int) $c['year'] : '-' ?>
                                    <input type="hidden" name="year[]" value="<?= (int) $c['year'] ?>">
                                </td>
                                <td data-label="No. Urut">
                                    <input type="number" min="1" name="next_number[]" class="form-control form-control-sm num-no"
                                           value="<?= (int) $c['next_number'] ?>">
                                </td>
                                <?php if ($isPeriodType): ?>
                                    <td data-label="Bulan">
                                        <select name="period_month[]" class="form-select form-select-sm num-month">
                                            <option value="0" <?= $pm === 0 ? 'selected' : '' ?>>Otomatis</option>
                                            <?php foreach ($romanLabels as $mn => $rl): ?>
                                                <option value="<?= $mn ?>" <?= $pm === $mn ? 'selected' : '' ?>><?= $rl ?> (<?= $mn ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td data-label="Tahun">
                                        <input type="number" min="2000" max="2100" name="period_year[]" class="form-control form-control-sm num-year"
                                               value="<?= e((string) $py) ?>" placeholder="Otomatis">
                                    </td>
                                    <td data-label="Pratinjau"><code class="num-preview"></code></td>
                                <?php else: ?>
                                    <td data-label="Bulan" class="text-muted small">-<input type="hidden" name="period_month[]" value="0"></td>
                                    <td data-label="Tahun" class="text-muted small">-<input type="hidden" name="period_year[]" value=""></td>
                                    <td data-label="Pratinjau" class="text-muted small">Nomor urut saja (tanpa bulan/tahun)</td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-outline-primary mt-2"><i class="bi bi-arrow-repeat"></i> Simpan No. Urut</button>
        </form>
    </div>
</div>

<script>
(function () {
    const roman = <?= json_encode(array_values($romanLabels)) ?>;
    const curMonth = <?= (int) date('n') ?>;
    function pad(n) { n = String(n || 1); while (n.length < 3) n = '0' + n; return n; }
    function refresh(tr) {
        if (tr.dataset.period !== '1') return;
        const no = tr.querySelector('.num-no').value;
        const m = parseInt(tr.querySelector('.num-month').value, 10) || curMonth;
        const y = parseInt(tr.querySelector('.num-year').value, 10) || tr.dataset.year;
        tr.querySelector('.num-preview').textContent = pad(no) + '/' + tr.dataset.code + '/' + roman[m - 1] + '/' + y;
    }
    document.querySelectorAll('tr.num-row').forEach(function (tr) {
        refresh(tr);
        tr.addEventListener('input', function () { refresh(tr); });
        tr.addEventListener('change', function () { refresh(tr); });
    });
})();
</script>
