<?php
/**
 * Modal quick-add Lokasi (Gudang). Include di halaman manapun yang punya
 * <select id="delivery_location_id">. Butuh permission 'warehouse'.'quick_add'.
 */
$quickAddWarehouseTargetId = $quickAddWarehouseTargetId ?? 'delivery_location_id';
?>
<div class="modal fade" id="modalQuickAddWarehouse" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formQuickAddWarehouse">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-building"></i> Tambah Lokasi Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Lokasi <span class="text-danger">*</span></label>
                        <input type="text" name="warehouse_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <input type="text" name="address" class="form-control" placeholder="Opsional">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">PIC</label>
                            <input type="text" name="pic_name" class="form-control" placeholder="Opsional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telepon</label>
                            <input type="text" name="phone" class="form-control" placeholder="Opsional">
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
        modalId: 'modalQuickAddWarehouse',
        formId: 'formQuickAddWarehouse',
        selectEl: '<?= e($quickAddWarehouseTargetId) ?>',
        endpoint: '<?= BASE_URL ?>/index.php?module=warehouse&action=quickStore',
    });
});
</script>
