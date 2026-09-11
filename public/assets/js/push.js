/* =========================================================================
 * push.js -- aktifkan/nonaktifkan Web Push notification DI PERANGKAT INI.
 *
 * Bukan pengaturan akun (server tidak tahu "user X mau push atau tidak"
 * secara global) -- tiap browser/HP yang klik "Aktifkan" mendaftar sendiri
 * (baris baru di push_subscriptions), jadi 1 akun login di 2 HP = 2 langganan
 * independen, wajar & didukung.
 *
 * Dipakai dari app/views/settings/_tab_notification.php lewat window.PushNotif.
 * ========================================================================= */
(function () {
    'use strict';

    function isIosSafari() {
        var ua = navigator.userAgent;
        var isIos = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
        return isIos;
    }

    function isStandalone() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
            || window.navigator.standalone === true;
    }

    function isSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    }

    // VAPID public key (base64url, dari server) -> Uint8Array yang dipahami PushManager.subscribe().
    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) {
            out[i] = raw.charCodeAt(i);
        }
        return out;
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function postForm(url, fields) {
        var body = new URLSearchParams(fields);
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        }).then(function (res) {
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            return res.json().catch(function () { return {}; });
        });
    }

    function getExistingSubscription() {
        return navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription();
        });
    }

    function subscribe(vapidPublicKey) {
        return navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });
        }).then(function (sub) {
            var json = sub.toJSON();
            return postForm(window.PUSH_CONFIG.subscribeUrl, {
                csrf_token: csrfToken(),
                endpoint: json.endpoint,
                p256dh: json.keys.p256dh,
                auth: json.keys.auth,
            }).then(function () { return sub; });
        });
    }

    function unsubscribe() {
        return getExistingSubscription().then(function (sub) {
            if (!sub) { return; }
            var endpoint = sub.endpoint;
            return sub.unsubscribe().then(function () {
                return postForm(window.PUSH_CONFIG.unsubscribeUrl, { csrf_token: csrfToken(), endpoint: endpoint });
            });
        });
    }

    function sendTest() {
        return postForm(window.PUSH_CONFIG.testUrl, { csrf_token: csrfToken() });
    }

    // Status ringkas dipakai UI Pengaturan > Notifikasi untuk menampilkan
    // tombol/pesan yang tepat (lihat _tab_notification.php).
    function getStatus() {
        if (isIosSafari() && !isStandalone()) {
            return Promise.resolve('ios_need_install');
        }
        if (!isSupported()) {
            return Promise.resolve('unsupported');
        }
        if (Notification.permission === 'denied') {
            return Promise.resolve('denied');
        }
        return getExistingSubscription().then(function (sub) {
            return sub ? 'subscribed' : 'not_subscribed';
        });
    }

    function enable() {
        if (!window.PUSH_CONFIG || !window.PUSH_CONFIG.vapidPublicKey) {
            return Promise.reject(new Error('Push belum dikonfigurasi server (VAPID kosong).'));
        }
        return Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') {
                throw new Error('Izin notifikasi ditolak.');
            }
            return subscribe(window.PUSH_CONFIG.vapidPublicKey);
        });
    }

    window.PushNotif = {
        isSupported: isSupported,
        isIosSafari: isIosSafari,
        isStandalone: isStandalone,
        getStatus: getStatus,
        enable: enable,
        disable: unsubscribe,
        sendTest: sendTest,
    };
})();
