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
            Nomor urut (angka di depan format, mis. <strong>001</strong>/PO.HME/IX/2026) berjalan otomatis
            per jenis dokumen &amp; TAHUN. Ubah "No. Urut Berikutnya" kalau perlu mulai dari angka tertentu
            (mis. menyamakan dengan nomor terakhir dari sistem lama). Menurunkan nomor ke angka yang SUDAH
            pernah dipakai aman secara data -- dokumen baru dengan nomor bentrok otomatis gagal disimpan
            (nomor dokumen unik), tidak menimpa dokumen lama.
        </p>
        <?php if (empty($counters)): ?>
            <p class="text-muted small mb-0">Belum ada jenis dokumen yang pernah membuat nomor otomatis.</p>
        <?php else: ?>
            <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=saveCounters">
                <?= csrfField() ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle entry-cards">
                        <thead class="table-light">
                            <tr>
                                <th>Jenis Dokumen</th>
                                <th style="width: 100px;">Tahun</th>
                                <th style="width: 160px;">No. Urut Berikutnya</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($counters as $c): ?>
                                <tr>
                                    <td>
                                        <?= e(DocumentNumber::DOC_TYPE_LABELS[$c['doc_type']] ?? $c['doc_type']) ?>
                                        <input type="hidden" name="doc_type[]" value="<?= e($c['doc_type']) ?>">
                                    </td>
                                    <td>
                                        <?= (int) $c['year'] ?>
                                        <input type="hidden" name="year[]" value="<?= (int) $c['year'] ?>">
                                    </td>
                                    <td>
                                        <input type="number" min="1" name="next_number[]" class="form-control form-control-sm"
                                               value="<?= (int) $c['next_number'] ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-outline-primary mt-2"><i class="bi bi-arrow-repeat"></i> Simpan No. Urut</button>
            </form>
        <?php endif; ?>
    </div>
</div>
