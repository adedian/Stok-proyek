<?php
/**
 * Modal quick-add Master Bank (Loan/HR). Include di halaman manapun yang
 * punya <select id="bank_id">. Butuh permission 'master_bank'.'quick_add'.
 * Dipakai dari form Transaksi Bank (Kas > Bank).
 *
 * bank_code diketik manual -- Master Bank TIDAK ikut sistem prefix CodeConfig
 * (beda dari Barang/Supplier/Project), jadi tidak ada komponen code_preview.
 */
$quickAddBankTargetId = $quickAddBankTargetId ?? 'bank_id';
?>
<div class="modal fade" id="modalQuickAddBank" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formQuickAddBank">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-bank"></i> Tambah Bank Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Kode Bank <span class="text-danger">*</span></label>
                            <input type="text" name="bank_code" class="form-control" placeholder="mis. BCA-01" required>
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
                            </select>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    initQuickAdd({
        modalId: 'modalQuickAddBank',
        formId: 'formQuickAddBank',
        selectEl: '<?= e($quickAddBankTargetId) ?>',
        endpoint: '<?= BASE_URL ?>/index.php?module=master_bank&action=quickStore',
    });
});
</script>
