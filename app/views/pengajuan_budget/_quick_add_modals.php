<?php
/**
 * Modal Tambah Cepat untuk form Pengajuan Budget: Rekening Sumber (Master Bank), Rekening Tujuan,
 * Kategori (Kategori Biaya). Project memakai partial quick_add_project_modal.php (mode dinamis).
 *
 * Hanya di-include bila can('pengajuan_budget','quick_add'). Semua form mengirim _via=pengajuan_budget
 * sehingga endpoint quickStore tiap master memakai izin 'pengajuan_budget'.'quick_add' (lihat
 * Middleware::viaPengajuanQuickAdd). Target <select> (baris yang sedang diisi) diatur form lewat
 * window.__pbQaTarget; opsi baru disebar ke semua baris lewat broadcastSelector.
 */
?>
<div class="modal fade" id="pbQaBankModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="pbQaBankForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-bank"></i> Tambah Rekening Sumber Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <input type="hidden" name="_via" value="pengajuan_budget">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Kode Bank <span class="text-danger">*</span></label>
                            <input type="text" name="bank_code" class="form-control" placeholder="mis. 005" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Nama Bank <span class="text-danger">*</span></label>
                            <input type="text" name="bank_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis <span class="text-danger">*</span></label>
                            <select name="jenis" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <option value="loan">Loan</option>
                                <option value="hr">HR</option>
                                <option value="kas">Kas</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. Rekening</label>
                            <input type="text" name="no_rekening" class="form-control" maxlength="60" placeholder="Opsional">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Keterangan</label>
                            <input type="text" name="keterangan" class="form-control" placeholder="Opsional">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="pbQaTujuanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="pbQaTujuanForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> Tambah Rekening Tujuan Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <input type="hidden" name="_via" value="pengajuan_budget">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama Penerima <span class="text-danger">*</span></label>
                            <input type="text" name="nama_penerima" class="form-control" maxlength="150" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Tujuan</label>
                            <input type="text" name="bank_tujuan" class="form-control" maxlength="100" placeholder="mis. BCA">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. Rekening</label>
                            <input type="text" name="no_rekening" class="form-control" maxlength="60">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Keterangan</label>
                            <input type="text" name="keterangan" class="form-control" maxlength="255" placeholder="Opsional">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="pbQaCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="pbQaCategoryForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-tags"></i> Tambah Kategori Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <input type="hidden" name="_via" value="pengajuan_budget">
                    <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                    <input type="text" name="category_name" class="form-control" maxlength="150" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const target = function () { return window.__pbQaTarget || null; };
    initQuickAdd({
        modalId: 'pbQaBankModal', formId: 'pbQaBankForm', selectEl: target,
        broadcastSelector: '.pb-bank',
        endpoint: '<?= BASE_URL ?>/index.php?module=master_bank&action=quickStore',
    });
    initQuickAdd({
        modalId: 'pbQaTujuanModal', formId: 'pbQaTujuanForm', selectEl: target,
        broadcastSelector: '.pb-tujuan', optionBeforeSelector: 'option[value="manual"]',
        endpoint: '<?= BASE_URL ?>/index.php?module=master_rekening_tujuan&action=quickStore',
    });
    initQuickAdd({
        modalId: 'pbQaCategoryModal', formId: 'pbQaCategoryForm', selectEl: target,
        broadcastSelector: '.pb-cat',
        endpoint: '<?= BASE_URL ?>/index.php?module=cash_category&action=quickStore',
    });
});
</script>
