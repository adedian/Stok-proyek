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

const VERSION = 'skp-2026-09-09-5';
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
