<?php
/**
 * Modal quick-add Project. Include di halaman manapun yang punya
 * <select id="project_id">. Butuh permission 'project'.'quick_add'.
 *
 * Mode dinamis ($quickAddProjectDynamic = true): target <select> di-resolve saat
 * submit lewat window.__quickAddProjectTarget (dipakai form Kas yang punya banyak
 * baris Project) + broadcast <option> baru ke semua .project-select.
 */
$quickAddProjectTargetId = $quickAddProjectTargetId ?? 'project_id';
$quickAddProjectDynamic  = !empty($quickAddProjectDynamic);
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
});
</script>
