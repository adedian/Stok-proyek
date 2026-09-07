<?php
/**
 * Modal quick-add Project. Include di halaman manapun yang punya
 * <select id="project_id">. Butuh permission 'project'.'quick_add'.
 *
 * Mode dinamis ($quickAddProjectDynamic = true): target <select> di-resolve saat
 * submit lewat window.__quickAddProjectTarget (dipakai form Kas yang punya banyak
 * baris Project) + broadcast <option> baru ke semua .project-select.
 *
 * Kode Project mengikuti prefix yang dipilih (Master Kode > Project),
 * format PREFIX.NOMOR.MASTERCODE. Tombol "+" di samping dropdown "Prefix Kode"
 * menambah prefix baru tanpa pindah halaman (butuh 'project'.'quick_add'
 * atau 'master_kode'.'edit').
 */
$quickAddProjectTargetId = $quickAddProjectTargetId ?? 'project_id';
$quickAddProjectDynamic  = !empty($quickAddProjectDynamic);

require_once ROOT_PATH . '/app/models/CodeConfig.php';
$__qaPrjCode         = new CodeConfig();
$__qaPrjPrefixes     = $__qaPrjCode->configsForEntity('project');
$__qaPrjMaster       = $__qaPrjCode->masterCodeForEntity('project');
$__qaPrjCanAddPrefix = function_exists('can') && (can('master_kode', 'edit') || can('project', 'quick_add'));
?>
<div class="modal fade" id="modalQuickAddProject" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formQuickAddProject">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-kanban"></i> Tambah Project Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama Project <span class="text-danger">*</span></label>
                            <input type="text" name="project_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <?php
                            $codePrefixes      = $__qaPrjPrefixes;
                            $codeMasterCode    = $__qaPrjMaster;
                            $codeEntityType    = 'project';
                            $codeEntityLabel   = 'Project';
                            $codePrefixFieldId = 'quickAddProjectPrefix';
                            $codePrefixHideAdd = false;
                            $codePrefixCanAdd  = $__qaPrjCanAddPrefix;
                            require ROOT_PATH . '/app/views/partials/code_preview.php';
                            ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="planning">Planning</option>
                                <option value="ongoing" selected>Ongoing</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lokasi</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama</label>
                            <input type="text" name="pic_name" class="form-control" placeholder="Nama PIC">
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
        modalId: 'modalQuickAddProject',
        formId: 'formQuickAddProject',
<?php if ($quickAddProjectDynamic): ?>
        selectEl: function () { return window.__quickAddProjectTarget || null; },
        broadcastSelector: '.project-select',
<?php else: ?>
        selectEl: '<?= e($quickAddProjectTargetId) ?>',
<?php endif; ?>
        endpoint: '<?= BASE_URL ?>/index.php?module=project&action=quickStore',
    });

    // Preview kode ikut ter-refresh tiap modal dibuka (form.reset() sebelumnya
    // mengembalikan dropdown prefix ke opsi pertama tanpa memicu 'change').
    var prjModalEl = document.getElementById('modalQuickAddProject');
    if (prjModalEl) {
        prjModalEl.addEventListener('shown.bs.modal', function () {
            var pf = prjModalEl.querySelector('select.js-cp-prefix');
            if (pf) pf.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
});
</script>

<?php /* Tombol "+" prefix di dalam code_preview butuh modal ini ikut termuat. */ ?>
<?php if ($__qaPrjCanAddPrefix): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_prefix_modal.php'; ?>
<?php endif; ?>
