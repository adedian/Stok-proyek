<?php
$initials = '';
foreach (explode(' ', trim($user['full_name'])) as $part) {
    $initials .= mb_substr($part, 0, 1);
}
$initials = mb_strtoupper(mb_substr($initials, 0, 2));
?>
<div class="mb-3">
    <h4 class="mb-0">Profile</h4>
    <small class="text-muted">Identitas & data diri Anda</small>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <span class="nav-link active">Profile</span>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/account/settings">Pengaturan Akun</a>
    </li>
</ul>

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
                        <td class="text-muted">Email</td>
                        <td class="text-end"><?= e($user['email']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
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
                            <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Foto Profil</label>
                            <input type="file" name="profile_photo" id="profilePhotoInput" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <div class="form-text">
                                    Foto saat ini: <a href="<?= BASE_URL ?>/<?= e($user['profile_photo']) ?>" target="_blank">lihat foto</a>
                                </div>
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input" id="removeProfilePhoto" name="remove_photo" value="1">
                                    <label class="form-check-label small text-danger" for="removeProfilePhoto">
                                        Hapus foto profil (kembali ke inisial)
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> Simpan Profil</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Foto profil: centang "Hapus" & pilih file baru saling meniadakan.
    var photoInput = document.getElementById('profilePhotoInput');
    var removePhoto = document.getElementById('removeProfilePhoto');
    if (photoInput && removePhoto) {
        photoInput.addEventListener('change', function () {
            if (photoInput.files.length) { removePhoto.checked = false; }
        });
        removePhoto.addEventListener('change', function () {
            if (removePhoto.checked) { photoInput.value = ''; }
        });
    }
});
</script>
