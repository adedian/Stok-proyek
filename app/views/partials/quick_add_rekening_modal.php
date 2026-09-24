<?php
/**
 * Modal quick-add Master Rekening. Include di halaman manapun yang punya
 * <select id="rekening_id">. Butuh permission 'master_rekening'.'quick_add'.
 * Dipakai dari form Transaksi Bank (Kas > Bank).
 *
 * kode_rekening diketik manual -- Master Rekening TIDAK ikut sistem prefix
 * CodeConfig, jadi tidak ada komponen code_preview.
 */
$quickAddRekeningTargetId = $quickAddRekeningTargetId ?? 'rekening_id';
?>
<div class="modal fade" id="modalQuickAddRekening" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formQuickAddRekening">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-credit-card"></i> Tambah Rekening Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Kode Rekening <span class="text-danger">*</span></label>
                            <input type="text" name="kode_rekening" class="form-control" placeholder="mis. REK-01" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Nama Rekening <span class="text-danger">*</span></label>
                            <input type="text" name="nama_rekening" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis</label>
                            <input type="text" name="jenis" class="form-control" placeholder="Opsional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">PIC</label>
                            <input type="text" name="pic_name" class="form-control" placeholder="Opsional">
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
        modalId: 'modalQuickAddRekening',
        formId: 'formQuickAddRekening',
        selectEl: '<?= e($quickAddRekeningTargetId) ?>',
        endpoint: '<?= BASE_URL ?>/index.php?module=master_rekening&action=quickStore',
    });
});
</script>
