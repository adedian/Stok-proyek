<?php
/**
 * Modal quick-add Client. Include di halaman manapun yang punya
 * <select id="client_id">. Butuh permission 'client'.'quick_add'.
 */
?>
<div class="modal fade" id="modalQuickAddClient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formQuickAddClient">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-people"></i> Tambah Client Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Client <span class="text-danger">*</span></label>
                        <input type="text" name="client_name" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">PIC</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alamat</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
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
        modalId: 'modalQuickAddClient',
        formId: 'formQuickAddClient',
        selectEl: <?= json_encode($quickAddClientTargetId ?? 'client_id') ?>,
        endpoint: '<?= BASE_URL ?>/index.php?module=client&action=quickStore',
    });
});
</script>
