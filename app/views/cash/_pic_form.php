<?php
/**
 * Form "Tambah PIC Kas" -- dipakai di kas_setup.php & kas_login.php.
 * Membuat / menautkan kredensial PIC Kas untuk AKUN SENDIRI (kasStorePic,
 * user_id = currentUserId() -- tidak pernah dari input).
 * @var array $existingNames  nama PIC milik user ini yang belum ber-password
 */
$existingNames = $existingNames ?? [];
?>
<?php if (!empty($existingNames)): ?>
    <div class="alert alert-light border small">
        <i class="bi bi-info-circle"></i>
        Anda sudah punya nama PIC tanpa password:
        <strong><?= e(implode(', ', $existingNames)) ?></strong>.
        Isi <em>Nama PIC</em> yang sama di bawah untuk menautkan password ke PIC tersebut.
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=kasStorePic">
    <?= csrfField() ?>
    <div class="mb-3">
        <label class="form-label">Nama PIC <span class="text-danger">*</span></label>
        <input type="text" name="pic_name" class="form-control" placeholder="mis. Tio" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Username / Nama Login PIC</label>
        <input type="text" name="pic_username" class="form-control" placeholder="opsional">
        <div class="form-text">Boleh dikosongkan &mdash; login bisa memakai Nama PIC.</div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Password Kas <span class="text-danger">*</span></label>
            <input type="password" name="kas_password" class="form-control" minlength="6" required autocomplete="new-password">
        </div>
        <div class="col-md-6">
            <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
            <input type="password" name="kas_password_confirm" class="form-control" minlength="6" required autocomplete="new-password">
        </div>
    </div>
    <div class="form-text mb-3">Minimal 6 karakter. Disimpan ter-enkripsi (hash), tidak pernah ditampilkan.</div>
    <button type="submit" class="btn btn-success w-100">
        <i class="bi bi-save"></i> Simpan PIC Kas
    </button>
</form>
