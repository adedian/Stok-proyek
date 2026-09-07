<?php
/**
 * Modal quick-add Lokasi (Gudang). Include di halaman manapun yang punya
 * <select id="delivery_location_id">. Butuh permission 'warehouse'.'quick_add'.
 *
 * Kode Gudang mengikuti prefix yang dipilih (Master Kode > Gudang),
 * format PREFIX.NOMOR.MASTERCODE. Tombol "+" di samping dropdown "Prefix Kode"
 * menambah prefix baru tanpa pindah halaman (butuh 'warehouse'.'quick_add'
 * atau 'master_kode'.'edit').
 */
$quickAddWarehouseTargetId = $quickAddWarehouseTargetId ?? 'delivery_location_id';

require_once ROOT_PATH . '/app/models/CodeConfig.php';
$__qaWhCode         = new CodeConfig();
$__qaWhPrefixes     = $__qaWhCode->configsForEntity('warehouse');
$__qaWhMaster       = $__qaWhCode->masterCodeForEntity('warehouse');
$__qaWhCanAddPrefix = function_exists('can') && (can('master_kode', 'edit') || can('warehouse', 'quick_add'));
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
                        <?php
                        $codePrefixes      = $__qaWhPrefixes;
                        $codeMasterCode    = $__qaWhMaster;
                        $codeEntityType    = 'warehouse';
                        $codeEntityLabel   = 'Gudang';
                        $codePrefixFieldId = 'quickAddWarehousePrefix';
                        $codePrefixHideAdd = false;
                        $codePrefixCanAdd  = $__qaWhCanAddPrefix;
                        require ROOT_PATH . '/app/views/partials/code_preview.php';
                        ?>
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

    // Preview kode ikut ter-refresh tiap modal dibuka (form.reset() sebelumnya
    // mengembalikan dropdown prefix ke opsi pertama tanpa memicu 'change').
    var whModalEl = document.getElementById('modalQuickAddWarehouse');
    if (whModalEl) {
        whModalEl.addEventListener('shown.bs.modal', function () {
            var pf = whModalEl.querySelector('select.js-cp-prefix');
            if (pf) pf.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
});
</script>

<?php /* Tombol "+" prefix di dalam code_preview butuh modal ini ikut termuat. */ ?>
<?php if ($__qaWhCanAddPrefix): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_prefix_modal.php'; ?>
<?php endif; ?>
