<?php
$kasPics = $kasPics ?? [];
$kasCanSetup = $kasCanSetup ?? false;
?>
<div class="mb-3">
    <h4 class="mb-0">Pengaturan Akun</h4>
    <small class="text-muted">Keamanan akun: password login &amp; Password Kas</small>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/account">Profile</a>
    </li>
    <li class="nav-item">
        <span class="nav-link active">Pengaturan Akun</span>
    </li>
</ul>

<div class="row g-3">
    <div class="col-lg-8">
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

        <?php if (!empty($kasPics)): ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h6 class="mb-1"><i class="bi bi-cash-coin"></i> Ganti Password Kas</h6>
                <p class="text-muted small mb-3">
                    Password lapisan kedua yang diminta saat membuka modul <strong>Kas</strong>
                    (verifikasi PIC + Password Kas). Terpisah dari password login akun.
                </p>
                <form method="POST" action="<?= BASE_URL ?>/index.php?module=account&action=changeKasPassword" id="changeKasPasswordForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="mode" value="change">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">PIC Kas <span class="text-danger">*</span></label>
                            <?php if (count($kasPics) === 1): ?>
                                <input type="hidden" name="pic_id" value="<?= (int) $kasPics[0]['id'] ?>">
                                <input type="text" class="form-control" value="<?= e($kasPics[0]['pic_name']) ?><?= !empty($kasPics[0]['pic_username']) ? ' (' . e($kasPics[0]['pic_username']) . ')' : '' ?>" disabled>
                            <?php else: ?>
                                <select name="pic_id" class="form-select" required>
                                    <?php foreach ($kasPics as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>">
                                            <?= e($p['pic_name']) ?><?= !empty($p['pic_username']) ? ' (' . e($p['pic_username']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password Kas Saat Ini <span class="text-danger">*</span></label>
                            <input type="password" name="current_kas_password" class="form-control" autocomplete="off" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password Kas Baru <span class="text-danger">*</span></label>
                            <input type="password" name="new_kas_password" class="form-control" autocomplete="new-password" minlength="6" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Konfirmasi Password Kas Baru <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_kas_password" class="form-control" autocomplete="new-password" minlength="6" required>
                        </div>
                    </div>
                    <div class="form-text">Minimal 6 karakter, tidak boleh sama dengan yang lama. Sesi Kas Anda akan diminta verifikasi ulang.</div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-key"></i> Ganti Password Kas</button>
                </form>
            </div>
        </div>
        <?php elseif ($kasCanSetup): ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h6 class="mb-1"><i class="bi bi-cash-coin"></i> Buat Password Kas</h6>
                <p class="text-muted small mb-3">
                    Role Anda perlu <strong>Password Kas</strong> (lapisan kedua) untuk membuka modul
                    <strong>Kas</strong>. Buat di sini &mdash; setelah itu Anda langsung bisa mengakses Kas.
                    Nama PIC Kas otomatis: <strong><?= e($user['full_name']) ?></strong>.
                </p>
                <form method="POST" action="<?= BASE_URL ?>/index.php?module=account&action=changeKasPassword" id="setKasPasswordForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="mode" value="set">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Password Kas <span class="text-danger">*</span></label>
                            <input type="password" name="new_kas_password" class="form-control" autocomplete="new-password" minlength="6" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Konfirmasi Password Kas <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_kas_password" class="form-control" autocomplete="new-password" minlength="6" required>
                        </div>
                    </div>
                    <div class="form-text">Minimal 6 karakter.</div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-plus-circle"></i> Buat Password Kas</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3"><i class="bi bi-info-circle"></i> Info Keamanan</h6>
                <table class="table table-sm table-borderless text-start mb-0 small">
                    <tr>
                        <td class="text-muted">Password Diperbarui</td>
                        <td class="text-end">
                            <?= !empty($user['password_changed_at']) ? waktuLalu($user['password_changed_at']) : 'Belum pernah' ?>
                        </td>
                    </tr>
                    <?php if (!empty($kasPics)): ?>
                    <tr>
                        <td class="text-muted">Password Kas</td>
                        <td class="text-end fw-semibold text-success">Aktif</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('changePasswordForm');
    if (form) {
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
    }

    ['changeKasPasswordForm', 'setKasPasswordForm'].forEach(function (id) {
        var kasForm = document.getElementById(id);
        if (!kasForm) { return; }
        kasForm.addEventListener('submit', function (e) {
            var a = kasForm.querySelector('[name="new_kas_password"]').value;
            var b = kasForm.querySelector('[name="confirm_kas_password"]').value;
            if (a !== b) {
                e.preventDefault();
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Konfirmasi Password Kas tidak cocok.' });
                } else {
                    alert('Konfirmasi Password Kas tidak cocok.');
                }
            }
        });
    });
});
</script>
