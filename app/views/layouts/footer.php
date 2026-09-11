        </div> <!-- /.main-content -->
    </div> <!-- /.d-flex -->

    <footer class="app-footer no-print">
        &copy; PT. Hexa Multi Energi. All rights reserved. Designed by Ade Dian Sukmana
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= assetUrl('/assets/js/custom.js') ?>"></script>
    <script src="<?= assetUrl('/assets/js/quick-add.js') ?>"></script>
    <script src="<?= assetUrl('/assets/js/layout.js') ?>"></script>
    <script src="<?= assetUrl('/assets/js/currency-input.js') ?>"></script>
    <script src="<?= assetUrl('/assets/js/checkbox-select-all.js') ?>"></script>
    <script src="<?= assetUrl('/assets/js/responsive-tables.js') ?>"></script>
    <script src="<?= assetUrl('/assets/js/pwa.js') ?>"></script>

    <?php if (isLoggedIn() && VAPID_PUBLIC_KEY !== ''): ?>
        <script>
            window.PUSH_CONFIG = {
                vapidPublicKey: <?= json_encode(VAPID_PUBLIC_KEY) ?>,
                subscribeUrl: <?= json_encode(BASE_URL . '/index.php?module=push&action=subscribe') ?>,
                unsubscribeUrl: <?= json_encode(BASE_URL . '/index.php?module=push&action=unsubscribe') ?>,
                testUrl: <?= json_encode(BASE_URL . '/index.php?module=push&action=test') ?>,
            };
        </script>
        <script src="<?= assetUrl('/assets/js/push.js') ?>"></script>
    <?php endif; ?>

    <?php /* ---- PWA: daftarkan Service Worker (hanya di HTTPS / localhost) ---- */ ?>
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
</body>
</html>