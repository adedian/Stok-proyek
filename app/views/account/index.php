<?php
$initials = '';
foreach (explode(' ', trim($user['full_name'])) as $part) {
    $initials .= mb_substr($part, 0, 1);
}
$initials = mb_strtoupper(mb_substr($initials, 0, 2));
?>
<div class="mb-3">
    <h4 class="mb-0">Pengaturan Akun</h4>
    <small class="text-muted">Kelola profil dan keamanan akun Anda sendiri</small>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <?php if (!empty($user['profile_photo'])): ?>
                    <img src="<?= BASE_URL ?>/<?= e($user['profile_photo']) ?>" alt="Foto profil"
                         class="rounded-circle mb-2" style="width:88px;height:88px;object-fit:cover;">
                <?php else: ?>
                    <span class="app-user-avatar mb-2" style="width:88px;height:88px;font-size:1.75rem;display:inline-flex;">
                        <?= e($initials) ?>
                    </span>
                <?php endif; ?>
                <div class="fw-semibold fs-5 mt-2"><?= e($user['full_name']) ?></div>
                <div class="text-muted small"><?= e(roleSubtitle($user['role_slug'])) ?></div>
                <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?> mt-2">
                    <?= $user['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                </span>

                <hr>

                <table class="table table-sm table-borderless text-start mb-0 small">
                    <tr>
                        <td class="text-muted">Username</td>
                        <td class="text-end fw-semibold"><?= e($user['username']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Role</td>
                        <td class="text-end fw-semibold"><?= e($user['role_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Terakhir Login</td>
                        <td class="text-end">
                            <?= !empty($user['last_login']) ? waktuLalu($user['last_login']) : '-' ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Password Diperbarui</td>
                        <td class="text-end">
                            <?= !empty($user['password_changed_at']) ? waktuLalu($user['password_changed_at']) : 'Belum pernah' ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="mb-3"><i class="bi bi-person-lines-fill"></i> Edit Profil</h6>
                <form method="POST" action="<?= BASE_URL ?>/index.php?module=account&action=updateProfile" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="mis. 081234567890">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Foto Profil</label>
                            <input type="file" name="profile_photo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <div class="form-text">
                                    Foto saat ini: <a href="<?= BASE_URL ?>/<?= e($user['profile_photo']) ?>" target="_blank">lihat foto</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> Simpan Profil</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3"><i class="bi bi-shield-lock"></i> Ganti Password</h6>
                <form method="POST" action="<?= BASE_URL ?>/index.php?module=account&action=changePassword" id="changePasswordForm">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Password Saat Ini <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control" autocomplete="new-password" minlength="6" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" minlength="6" required>
                        </div>
                    </div>
                    <div class="form-text">Minimal 6 karakter, tidak boleh sama dengan password lama.</div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-key"></i> Ganti Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('changePasswordForm');
    if (!form) { return; }
    form.addEventListener('submit', function (e) {
        var newPass = form.querySelector('[name="new_password"]').value;
        var confirmPass = form.querySelector('[name="confirm_password"]').value;
        if (newPass !== confirmPass) {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: 'Konfirmasi password baru tidak cocok.' });
            } else {
                alert('Konfirmasi password baru tidak cocok.');
            }
        }
    });
});
</script>
