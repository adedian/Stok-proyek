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

            <?php
                $lockRemaining = (int) ($lockRemaining ?? 0);
                $isLocked = $lockRemaining > 0;
                $lockUntilMs = $isLocked ? (time() + $lockRemaining) * 1000 : 0;
                $flash = getFlash(); // selalu konsumsi flash
            ?>

            <?php if (!empty($expired) && !$isLocked): ?>
                <div class="alert alert-warning py-2">Sesi Anda telah berakhir, silakan login kembali.</div>
            <?php endif; ?>

            <?php if ($isLocked): ?>
                <div class="alert alert-danger py-2" id="lockBox">
                    Akun/perangkat ini dikunci sementara karena terlalu banyak percobaan gagal.
                    Coba lagi dalam <strong id="lockCountdown">&hellip;</strong>.
                </div>
            <?php elseif ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : e($flash['type']) ?> py-2">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/index.php?module=auth&action=authenticate"
                  id="loginForm" data-lock-until="<?= $lockUntilMs ?>">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required
                           <?= $isLocked ? '' : 'autofocus' ?> autocomplete="username" <?= $isLocked ? 'disabled' : '' ?>>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required
                           autocomplete="current-password" <?= $isLocked ? 'disabled' : '' ?>>
                </div>
                <button type="submit" class="btn btn-primary w-100" <?= $isLocked ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
        </div>
    </div>
    <footer class="login-footer">
        &copy; PT. Hexa Multi Energi. All rights reserved. Designed by Ade Dian Sukmana
    </footer>

    <script>
    // Freeze form login selama masih terkunci + hitung mundur. Kalau JS mati,
    // atribut disabled dari server tetap membekukan form (perlu refresh setelah
    // waktunya habis). Server tetap penegak kunci yang sebenarnya.
    (function () {
        var form = document.getElementById('loginForm');
        if (!form) return;
        var until = parseInt(form.getAttribute('data-lock-until') || '0', 10);
        var controls = form.querySelectorAll('input:not([type="hidden"]), button');
        var cd = document.getElementById('lockCountdown');
        var box = document.getElementById('lockBox');

        function setLocked(on) {
            controls.forEach(function (el) { el.disabled = on; });
        }
        function fmt(s) {
            var m = Math.floor(s / 60), ss = s % 60;
            return m + ':' + (ss < 10 ? '0' : '') + ss;
        }
        function tick() {
            var left = Math.ceil((until - Date.now()) / 1000);
            if (left > 0) {
                if (cd) cd.textContent = fmt(left);
                setTimeout(tick, 1000);
            } else {
                setLocked(false);
                if (box) {
                    box.className = 'alert alert-success py-2';
                    box.textContent = 'Kunci sudah berakhir. Silakan login kembali.';
                }
                var u = form.querySelector('input[name="username"]');
                if (u) u.focus();
            }
        }

        form.addEventListener('submit', function (e) {
            if (Date.now() < until) e.preventDefault();
        });

        if (Date.now() < until) {
            setLocked(true);
            tick();
        }
    })();
    </script>
</body>
</html>
