/* =========================================================================
 * pwa.js -- mode aplikasi HP
 *
 *  1. Tandai <html class="pwa-standalone"> saat dibuka dari ikon home screen
 *     (jaring pengaman; idealnya sudah diset inline di <head> tanpa kedip).
 *  2. Tampilkan tombol mengambang "Pasang Aplikasi" di Android/Chrome saat
 *     browser menawarkan install (event beforeinstallprompt). Bisa ditutup
 *     dan tidak muncul lagi (localStorage). iOS tidak punya API ini -- di
 *     sana pemasangan lewat menu Share > "Add to Home Screen".
 * ========================================================================= */
(function () {
    'use strict';

    // --- 1. Deteksi mode aplikasi (jaring pengaman) ---------------------------
    try {
        var standalone = (window.matchMedia
                && (window.matchMedia('(display-mode: standalone)').matches
                    || window.matchMedia('(display-mode: minimal-ui)').matches))
            || window.navigator.standalone === true
            || document.referrer.indexOf('android-app://') === 0;
        if (standalone) {
            document.documentElement.classList.add('pwa-standalone');
        }
    } catch (e) { /* abaikan */ }

    // --- 2. Tombol "Pasang Aplikasi" ----------------------------------------
    var DISMISS_KEY = 'pwaInstallDismissed';
    var deferredPrompt = null;
    var fab = null;

    function isDismissed() {
        try { return localStorage.getItem(DISMISS_KEY) === '1'; } catch (e) { return false; }
    }
    function remember() {
        try { localStorage.setItem(DISMISS_KEY, '1'); } catch (e) { /* abaikan */ }
    }
    function removeFab() {
        if (fab && fab.parentNode) { fab.parentNode.removeChild(fab); }
        fab = null;
    }

    function showFab() {
        if (fab || isDismissed()) { return; }
        if (document.documentElement.classList.contains('pwa-standalone')) { return; }
        if (!document.body) { return; }

        fab = document.createElement('button');
        fab.type = 'button';
        fab.className = 'pwa-install-fab no-print';
        fab.innerHTML =
            '<i class="bi bi-download"></i>'
            + '<span>Pasang Aplikasi</span>'
            + '<span class="pwa-install-x" role="button" aria-label="Tutup">&times;</span>';

        fab.addEventListener('click', function (ev) {
            // Klik pada tanda "x" -> tutup & jangan tampilkan lagi.
            if (ev.target && ev.target.classList.contains('pwa-install-x')) {
                ev.stopPropagation();
                remember();
                removeFab();
                return;
            }
            if (!deferredPrompt) { return; }
            deferredPrompt.prompt();
            var choice = deferredPrompt.userChoice || Promise.resolve();
            choice.then(function () {
                deferredPrompt = null;
                removeFab();
            });
        });

        document.body.appendChild(fab);
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        showFab();
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        removeFab();
        remember();
    });
})();
