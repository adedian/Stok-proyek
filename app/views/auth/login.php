<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <script>
        (function () {
            try {
                if ((window.matchMedia && (matchMedia('(display-mode: standalone)').matches
                        || matchMedia('(display-mode: minimal-ui)').matches))
                    || navigator.standalone === true) {
                    document.documentElement.classList.add('pwa-standalone');
                }
            } catch (e) { /* abaikan */ }
        })();
    </script>
    <title>Login - <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= assetUrl('/assets/img/logo-hme.png') ?>">

    <?php /* ---- PWA (sama seperti layout utama, supaya bisa di-install dari halaman login) ---- */ ?>
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.webmanifest">
    <meta name="theme-color" content="#1E3C72">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HEXA STOK">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/pwa/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/pwa/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/assets/img/pwa/favicon-16.png">

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
    <link href="<?= assetUrl('/assets/css/pwa.css') ?>" rel="stylesheet">
    <style>
        /* =========================================================
           LOGIN -- corporate, compact, mobile-first.
           Semua di-scope ke .login-* supaya tidak bocor ke halaman lain
           (login pakai Controller::viewPlain(), di luar layout utama).
           ========================================================= */
        html, body { overflow-x: hidden; }
        html { background: var(--brand-900); }
        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            background:
                radial-gradient(130% 52% at 50% 0%, rgba(59, 105, 176, .30) 0%, transparent 55%),
                linear-gradient(180deg, #1B3358 0%, #14243B 72%);
            background-repeat: no-repeat;
        }

        .login-viewport {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            /* +env(safe-area-inset-top): di PWA iOS (status bar translucent) isi
               tidak ketimpa jam/notch. Fallback 0px -> tanpa efek di device biasa. */
            padding: calc(clamp(16px, 5vh, 44px) + env(safe-area-inset-top, 0px))
                     max(16px, env(safe-area-inset-right, 0px))
                     0
                     max(16px, env(safe-area-inset-left, 0px));
        }
        .login-center {
            flex: 1 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--surface);
            border: 1px solid rgba(226, 232, 240, .9);
            border-radius: clamp(16px, 4.5vw, 20px);
            box-shadow:
                0 18px 50px -18px rgba(9, 20, 38, .55),
                0 6px 16px -8px rgba(9, 20, 38, .22);
            padding: clamp(24px, 6vw, 34px) clamp(20px, 6vw, 32px);
        }

        .login-head {
            text-align: center;
            margin-bottom: clamp(16px, 4vw, 22px);
        }
        .login-logo {
            display: inline-flex;
            width: clamp(52px, 15vw, 64px);
            height: clamp(52px, 15vw, 64px);
            margin-bottom: 12px;
        }
        .login-logo img { width: 100%; height: 100%; object-fit: contain; }
        .login-title {
            margin: 0;
            font-size: clamp(1.15rem, 5vw, 1.4rem);
            font-weight: 700;
            letter-spacing: -.01em;
            line-height: 1.25;
            color: var(--ink);
        }
        .login-subtitle {
            margin: 4px 0 0;
            font-size: clamp(.8rem, 3.4vw, .9rem);
            color: var(--ink-faint);
        }

        .login-alert {
            font-size: clamp(.8rem, 3.4vw, .875rem);
            line-height: 1.5;
            padding: .625rem .8rem;
            border-radius: 10px;
            margin-bottom: clamp(14px, 3.5vw, 18px);
        }
        .login-alert strong { font-weight: 700; white-space: nowrap; }

        .login-form { margin: 0; }
        .login-field { margin-bottom: 14px; }
        .login-field > label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: .82rem;
            color: var(--ink-soft);
        }
        .login-card .form-control {
            min-height: 46px;
            font-size: 16px;            /* >=16px: cegah auto-zoom iOS saat fokus */
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        .login-card .form-control:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px rgba(42, 82, 152, .16);
        }

        .login-password { position: relative; }
        .login-password .form-control { padding-right: 46px; }
        .login-eye {
            position: absolute;
            top: 0;
            right: 0;
            width: 44px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0;
            background: transparent;
            color: var(--ink-faint);
            font-size: 1.05rem;
            cursor: pointer;
            border-radius: 0 10px 10px 0;
        }
        .login-eye:hover { color: var(--ink-soft); }
        .login-eye:disabled { opacity: .45; cursor: not-allowed; }

        .login-submit {
            width: 100%;
            min-height: 46px;
            margin-top: 6px;
            font-weight: 600;
            font-size: .95rem;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
        }

        .login-foot {
            flex-shrink: 0;
            text-align: center;
            color: rgba(255, 255, 255, .58);
            font-size: clamp(10px, 2.8vw, 11.5px);
            line-height: 1.5;
            padding: clamp(14px, 3.5vh, 22px) 16px
                     calc(clamp(14px, 3.5vh, 22px) + env(safe-area-inset-bottom, 0px));
        }

        /* Layar pendek (mis. iPhone SE, split-screen) -- rapatkan vertikal. */
        @media (max-height: 720px) {
            .login-viewport { padding-top: calc(clamp(12px, 3vh, 24px) + env(safe-area-inset-top, 0px)); }
            .login-card { padding: 22px 20px; }
            .login-head { margin-bottom: 14px; }
            .login-logo { width: 48px; height: 48px; margin-bottom: 9px; }
            .login-field { margin-bottom: 11px; }
        }
        /* Landscape HP -- biarkan menggulir natural, jangan paksa center. */
        @media (max-height: 520px) and (orientation: landscape) {
            .login-center { align-items: flex-start; }
            .login-logo { width: 40px; height: 40px; }
            .login-head { margin-bottom: 12px; }
        }
        /* Layar besar -- kartu sedikit lebih lega, tetap terkendali. */
        @media (min-width: 576px) {
            .login-card { padding: 36px 34px; }
        }
    </style>
</head>
<body>
    <div class="login-viewport">
        <div class="login-center">
            <section class="login-card">
                <div class="login-head">
                    <span class="login-logo"><img src="<?= assetUrl('/assets/img/logo-hme.png') ?>" alt="Logo HME"></span>
                    <h1 class="login-title"><?= e(APP_NAME) ?></h1>
                    <p class="login-subtitle">Silakan login untuk melanjutkan</p>
                </div>

                <?php
                    $lockRemaining = (int) ($lockRemaining ?? 0);
                    $isLocked = $lockRemaining > 0;
                    $lockUntilMs = $isLocked ? (time() + $lockRemaining) * 1000 : 0;
                    $flash = getFlash(); // selalu konsumsi flash
                ?>

                <?php if (!empty($expired) && !$isLocked): ?>
                    <div class="alert alert-warning login-alert">Sesi Anda telah berakhir, silakan login kembali.</div>
                <?php endif; ?>

                <?php if ($isLocked): ?>
                    <div class="alert alert-danger login-alert" id="lockBox">
                        Akun/perangkat ini dikunci sementara karena terlalu banyak percobaan gagal.
                        Coba lagi dalam <strong id="lockCountdown">&hellip;</strong>.
                    </div>
                <?php elseif ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : e($flash['type']) ?> login-alert">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/index.php?module=auth&action=authenticate"
                      class="login-form" id="loginForm" data-lock-until="<?= $lockUntilMs ?>">
                    <?= csrfField() ?>
                    <div class="login-field">
                        <label for="loginUsername">Username</label>
                        <input type="text" name="username" id="loginUsername" class="form-control" required
                               <?= $isLocked ? '' : 'autofocus' ?> autocomplete="username" <?= $isLocked ? 'disabled' : '' ?>>
                    </div>
                    <div class="login-field">
                        <label for="loginPassword">Password</label>
                        <div class="login-password">
                            <input type="password" name="password" id="loginPassword" class="form-control" required
                                   autocomplete="current-password" <?= $isLocked ? 'disabled' : '' ?>>
                            <button type="button" class="login-eye" id="loginEye"
                                    aria-label="Tampilkan password" <?= $isLocked ? 'disabled' : '' ?>>
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary login-submit" <?= $isLocked ? 'disabled' : '' ?>>
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </button>
                </form>
            </section>
        </div>

        <footer class="login-foot">
            &copy; PT. Hexa Multi Energi. All rights reserved. Designed by Ade Dian Sukmana
        </footer>
    </div>

    <script>
    (function () {
        var form = document.getElementById('loginForm');
        if (!form) return;

        /* -------- Show / hide password (murni UI, tidak menyentuh nilai) -------- */
        var eye = document.getElementById('loginEye');
        var pw = document.getElementById('loginPassword');
        if (eye && pw) {
            eye.addEventListener('click', function () {
                var show = pw.getAttribute('type') === 'password';
                pw.setAttribute('type', show ? 'text' : 'password');
                var i = eye.querySelector('i');
                if (i) i.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
                eye.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                pw.focus();
            });
        }

        /* -------- Freeze form selama terkunci + hitung mundur --------
           Kalau JS mati, atribut disabled dari server tetap membekukan
           (perlu refresh setelah waktunya). Server tetap penegak kunci. */
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
                    box.className = 'alert alert-success login-alert';
                    box.textContent = 'Kunci sudah berakhir. Silakan login kembali.';
                }
                var u = document.getElementById('loginUsername');
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

    <?php /* ---- PWA: daftarkan Service Worker dari halaman login juga (HTTPS/localhost) ---- */ ?>
    <script>
    (function () {
        if (!('serviceWorker' in navigator)) return;
        var secure = location.protocol === 'https:'
            || location.hostname === 'localhost'
            || location.hostname === '127.0.0.1';
        if (!secure) return;
        var swUrl = <?= json_encode(BASE_URL . '/sw.js') ?>;
        var swScope = <?= json_encode(APP_BASE_PATH === '' ? '/' : APP_BASE_PATH . '/') ?>;
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(swUrl, { scope: swScope }).catch(function () { /* abaikan */ });
        });
    })();
    </script>
    <script src="<?= assetUrl('/assets/js/pwa.js') ?>"></script>
</body>
</html>
