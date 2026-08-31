<?php
/** @var array $picNames @var int|null $lockedUntil @var int $failsLeft */
$locked = $lockedUntil !== null;
$mins   = $locked ? (int) ceil(($lockedUntil - time()) / 60) : 0;
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <i class="bi bi-shield-lock fs-1 text-primary"></i>
                    <h4 class="mt-2 mb-1">Verifikasi Kas</h4>
                    <p class="text-muted small mb-0">
                        Modul Kas dilindungi lapisan keamanan kedua. Masukkan PIC Kas
                        dan Password Kas Anda &mdash; berbeda dari password login aplikasi.
                    </p>
                </div>

                <?php if ($locked): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-octagon"></i>
                        Terlalu banyak percobaan gagal. Coba lagi dalam
                        <strong><?= $mins < 1 ? 1 : $mins ?> menit</strong>.
                    </div>
                    <a href="<?= BASE_URL ?>/dashboard" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                    </a>
                <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=kasAuthenticate">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label">Nama / PIC <span class="text-danger">*</span></label>
                            <?php if (!empty($picNames)): ?>
                                <select name="pic_name" class="form-select" required>
                                    <option value="">-- Pilih PIC --</option>
                                    <?php foreach ($picNames as $p): ?>
                                        <option value="<?= e($p) ?>"><?= e($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" name="pic_name" class="form-control" required autofocus>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Kas <span class="text-danger">*</span></label>
                            <input type="password" name="kas_password" class="form-control" required autocomplete="off">
                        </div>
                        <?php if ($failsLeft < 5): ?>
                            <div class="small text-danger mb-2">Sisa percobaan sebelum terkunci: <?= (int) $failsLeft ?>.</div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-unlock"></i> Masuk Kas
                        </button>
                    </form>
                    <div class="text-center small text-muted mt-3">
                        <i class="bi bi-info-circle"></i> Sesi login aplikasi Anda tetap aktif.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
