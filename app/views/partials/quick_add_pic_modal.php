<?php
/**
 * Modal quick-add PIC (Kas). Menambah entri ke Master Data > PIC Mapping
 * (tabel user_pic_assignments) tanpa keluar dari form Tambah/Edit Kas.
 * Super Admin bisa memilih user tujuan; role lain otomatis dikaitkan ke
 * akun sendiri (ditegakkan server-side di UserPicController::quickStore()).
 */
$__isSA = currentUserRole() === ROLE_SUPER_ADMIN;
$__activeUsers = $__isSA ? (new User())->activeList() : [];
?>
<div class="modal fade" id="modalQuickAddPic" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formQuickAddPic">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-badge"></i> Tambah PIC Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <?php if ($__isSA): ?>
                        <div class="mb-2">
                            <label class="form-label">User <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-select" required>
                                <option value="">-- Pilih User --</option>
                                <?php foreach ($__activeUsers as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === (int) currentUserId() ? 'selected' : '' ?>>
                                        <?= e($u['full_name']) ?> (<?= e($u['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="form-text mb-2">PIC baru akan dikaitkan ke akun Anda (<?= e(currentUserName()) ?>).</div>
                    <?php endif; ?>
                    <div class="mb-1">
                        <label class="form-label">Nama PIC <span class="text-danger">*</span></label>
                        <input type="text" name="pic_name" class="form-control" placeholder="mis. Andi" required>
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
        modalId: 'modalQuickAddPic',
        formId: 'formQuickAddPic',
        selectEl: function () { return window.__quickAddPicTarget || document.getElementById('cash_pic'); },
        broadcastSelector: '#cash_pic',
        endpoint: '<?= BASE_URL ?>/index.php?module=user_pic&action=quickStore',
    });
});
</script>
