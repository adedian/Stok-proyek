/* =========================================================================
 * Service Worker -- HEXA STOK (PWA)
 *
 * Prinsip (aplikasi PHP ber-login, multi-halaman):
 *   - HALAMAN HTML tidak pernah di-cache (bisa bocor antar user / basi).
 *     Navigasi = network-only, kalau offline tampilkan /offline.html.
 *   - ASET STATIS (/assets/**, ikon) di-cache: stale-while-revalidate.
 *     Aman karena tiap file di-versioning dengan ?v=<mtime>.
 *   - Aset CDN (jsdelivr) di-cache cache-first + refresh di belakang.
 *   - Request non-GET & selain di atas: diteruskan apa adanya (no-op).
 *
 * Cara memaksa update SW: naikkan VERSION di bawah lalu deploy.
 * ========================================================================= */

const VERSION = 'skp-2026-09-11-2';
const RUNTIME = 'runtime-' + VERSION;
const PRECACHE = 'precache-' + VERSION;

// Path relatif terhadap scope SW (mis. "/" di produksi, "/stok-proyek/public/" di lokal).
const SCOPE = new URL(self.registration.scope).pathname;
const OFFLINE_URL = SCOPE + 'offline.html';

const PRECACHE_URLS = [
    OFFLINE_URL,
    SCOPE + 'assets/img/pwa/icon-192.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(PRECACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
            .catch(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k !== RUNTIME && k !== PRECACHE)
                    .map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

function isStaticAsset(url) {
    return url.origin === self.location.origin
        && url.pathname.startsWith(SCOPE + 'assets/');
}

function isCdnAsset(url) {
    return url.origin === 'https://cdn.jsdelivr.net';
}

// stale-while-revalidate: balikan cache dulu (kalau ada), refresh di belakang.
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then((res) => {
            if (res && (res.ok || res.type === 'opaque')) {
                cache.put(request, res.clone());
            }
            return res;
        })
        .catch(() => cached);
    return cached || network;
}

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Hanya GET yang kita sentuh. POST/PUT/DELETE (form, quick-add, dll) lewat.
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Navigasi halaman -> selalu ke jaringan; offline -> halaman fallback.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Aset statis app -> SWR.
    if (isStaticAsset(url)) {
        event.respondWith(staleWhileRevalidate(req, RUNTIME));
        return;
    }

    // Aset CDN (Bootstrap, ikon, chart.js, sweetalert2) -> cache-first + refresh.
    if (isCdnAsset(url)) {
        event.respondWith(staleWhileRevalidate(req, RUNTIME));
        return;
    }

    // Sisanya (AJAX ke index.php, dll) -> jaringan apa adanya.
});

/* =========================================================================
 * PUSH NOTIFICATION -- muncul walau aplikasi sedang tertutup. Payload dari
 * server SELALU JSON {title, body, url} (lihat app/helpers/push_helper.php).
 * ========================================================================= */
self.addEventListener('push', (event) => {
    let data = { title: 'HEXA STOK', body: 'Ada pembaruan baru.', url: SCOPE };
    try {
        if (event.data) {
            data = Object.assign(data, event.data.json());
        }
    } catch (e) { /* payload bukan JSON -- pakai default di atas */ }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: SCOPE + 'assets/img/pwa/icon-192.png',
            badge: SCOPE + 'assets/img/pwa/icon-192.png',
            data: { url: data.url || SCOPE },
            tag: data.tag || undefined, // notifikasi jenis sama (mis. banyak PO) numpuk jadi 1, tidak spam
        })
    );
});

// Tap notifikasi -> fokus tab yang sudah terbuka (kalau ada & di app ini),
// atau buka tab baru ke url tujuan.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || SCOPE;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientsArr) => {
            for (const client of clientsArr) {
                if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            return self.clients.openWindow(targetUrl);
        })
    );
});
