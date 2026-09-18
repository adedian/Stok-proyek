<?php
/** @var array $projects @var int|null $lockedUntil @var int $failsLeft */
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
                        Pilih Project yang ingin dibuka, lalu masukkan
                        <strong>password akun Anda sendiri</strong> (password login, bukan password terpisah).
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
                <?php elseif (empty($projects)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Akun Anda belum di-assign ke Project manapun. Hubungi Super Admin untuk
                        diberikan akses lewat menu <strong>Project &raquo; Akses</strong>.
                    </div>
                    <a href="<?= BASE_URL ?>/dashboard" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                    </a>
                <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=kasProjectAuthenticate">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label">Project <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-select" required autofocus>
                                <option value="">-- Pilih Project --</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= e($p['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Akun Anda <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required autocomplete="current-password">
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
