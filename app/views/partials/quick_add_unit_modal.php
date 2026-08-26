<?php
/**
 * Modal quick-add Satuan. Dipakai berdiri sendiri ATAU nested dari dalam
 * modal quick-add Barang (Bootstrap mendukung modal bertumpuk).
 */
?>
<div class="modal fade" id="modalQuickAddUnit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="formQuickAddUnit">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-rulers"></i> Tambah Satuan Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <div class="mb-1">
                        <label class="form-label">Nama Satuan <span class="text-danger">*</span></label>
                        <input type="text" name="unit_name" class="form-control" placeholder="mis. Zak, Kubik" required>
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
        modalId: 'modalQuickAddUnit',
        formId: 'formQuickAddUnit',
        selectEl: function () { return window.__quickAddUnitTarget || document.getElementById('unit_id'); },
        broadcastSelector: '.unit-select',
        endpoint: '<?= BASE_URL ?>/index.php?module=unit&action=quickStore',
    });
});
</script>
