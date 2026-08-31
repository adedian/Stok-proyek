<?php
/** @var array $existingNames */
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <i class="bi bi-person-badge fs-1 text-warning"></i>
                    <h4 class="mt-2 mb-1">PIC Kas Belum Terdaftar</h4>
                    <p class="text-muted small mb-0">
                        Akun Anda belum memiliki PIC Kas dengan kredensial. Buat PIC Kas
                        terlebih dahulu untuk dapat menggunakan modul Kas.
                    </p>
                </div>

                <?php if (!empty($existingNames)): ?>
                    <div class="alert alert-light border small">
                        <i class="bi bi-info-circle"></i>
                        Anda sudah punya nama PIC tanpa password:
                        <strong><?= e(implode(', ', $existingNames)) ?></strong>.
                        Isi <em>Nama PIC</em> yang sama di bawah untuk menautkan password ke PIC tersebut.
                    </div>
                <?php endif; ?>

                <div class="text-center mb-3">
                    <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#formTambahPic">
                        <i class="bi bi-plus-circle"></i> Tambah PIC Kas
                    </button>
                </div>

                <div class="collapse" id="formTambahPic">
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
                </div>
            </div>
        </div>
    </div>
</div>
