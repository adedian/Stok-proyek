<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= assetUrl('/assets/img/logo-hme.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/variables.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/layout.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/cards.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/buttons.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/forms.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/badges.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/alerts.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/utilities.css') ?>" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at top left, var(--brand-500), var(--brand-900) 70%);
            min-height: 100vh;
        }
        .login-card {
            max-width: 400px;
            margin: 9vh auto;
            border-radius: var(--radius-lg) !important;
            overflow: hidden;
        }
        .login-card .card-body {
            padding: 2.25rem 2rem !important;
        }
        .login-brand-icon {
            width: 64px;
            height: 64px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .login-brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        /* Footer copyright -- teks & aturan sama seperti app/views/layouts/footer.php
           (halaman login pakai Controller::viewPlain(), di luar layout utama, jadi
           butuh footer terpisah di sini, bukan position:fixed supaya tidak menutupi
           kartu login di layar pendek). */
        .login-footer {
            text-align: center;
            font-size: 12px;
            color: rgba(255, 255, 255, .75);
            padding: 16px 20px 24px;
        }
    </style>
</head>
<body>
    <div class="login-card card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <span class="login-brand-icon mb-2"><img src="<?= assetUrl('/assets/img/logo-hme.png') ?>" alt="Logo HME"></span>
                <h4 class="mt-2 mb-0"><?= e(APP_NAME) ?></h4>
                <small class="text-muted">Silakan login untuk melanjutkan</small>
            </div>

            <?php if (!empty($expired)): ?>
                <div class="alert alert-warning py-2">Sesi Anda telah berakhir, silakan login kembali.</div>
            <?php endif; ?>

            <?php $flash = getFlash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : e($flash['type']) ?> py-2">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/index.php?module=auth&action=authenticate">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autofocus autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
        </div>
    </div>
    <footer class="login-footer">
        &copy; PT. Hexa Multi Energi. All rights reserved. Designed by Ade Dian Sukmana
    </footer>
</body>
</html>
