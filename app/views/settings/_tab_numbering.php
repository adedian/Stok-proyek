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
