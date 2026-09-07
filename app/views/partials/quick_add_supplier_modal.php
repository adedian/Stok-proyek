<?php
/**
 * Modal quick-add Supplier. Include di halaman manapun yang punya
 * <select id="supplier_id">. Butuh permission 'supplier'.'quick_add'.
 *
 * Kode Supplier mengikuti prefix yang dipilih (Master Kode > Supplier),
 * format PREFIX.NOMOR.MASTERCODE. Tombol "+" di samping dropdown "Prefix Kode"
 * menambah prefix baru tanpa pindah halaman (butuh 'supplier'.'quick_add'
 * atau 'master_kode'.'edit').
 */
require_once ROOT_PATH . '/app/models/CodeConfig.php';
$__qaSupCode          = new CodeConfig();
$__qaSupPrefixes      = $__qaSupCode->configsForEntity('supplier');
$__qaSupMaster        = $__qaSupCode->masterCodeForEntity('supplier');
$__qaSupCanAddPrefix  = function_exists('can') && (can('master_kode', 'edit') || can('supplier', 'quick_add'));
?>
<div class="modal fade" id="modalQuickAddSupplier" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formQuickAddSupplier">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-truck"></i> Tambah Supplier Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <?php
                        $codePrefixes      = $__qaSupPrefixes;
                        $codeMasterCode    = $__qaSupMaster;
                        $codeEntityType    = 'supplier';
                        $codeEntityLabel   = 'Supplier';
                        $codePrefixFieldId = 'quickAddSupplierPrefix';
                        $codePrefixHideAdd = false;
                        $codePrefixCanAdd  = $__qaSupCanAddPrefix;
                        require ROOT_PATH . '/app/views/partials/code_preview.php';
                        ?>
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
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NPWP <span class="text-muted small">(opsional)</span></label>
                            <input type="text" name="npwp" class="form-control">
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
        modalId: 'modalQuickAddSupplier',
        formId: 'formQuickAddSupplier',
        selectEl: 'supplier_id',
        endpoint: '<?= BASE_URL ?>/index.php?module=supplier&action=quickStore',
    });

    // Preview kode ikut ter-refresh tiap modal dibuka (form.reset() sebelumnya
    // mengembalikan dropdown prefix ke opsi pertama tanpa memicu 'change').
    var supModalEl = document.getElementById('modalQuickAddSupplier');
    if (supModalEl) {
        supModalEl.addEventListener('shown.bs.modal', function () {
            var pf = supModalEl.querySelector('select.js-cp-prefix');
            if (pf) pf.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
});
</script>

<?php /* Tombol "+" prefix di dalam code_preview butuh modal ini ikut termuat. */ ?>
<?php if ($__qaSupCanAddPrefix): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_prefix_modal.php'; ?>
<?php endif; ?>
