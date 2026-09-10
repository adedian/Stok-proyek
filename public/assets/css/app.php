<?php
/**
 * app.php -- SATU request untuk seluruh CSS aplikasi (bukan 15 <link>).
 *
 * Kenapa aman digabung:
 *   - Tidak ada @import antar-file.
 *   - Tidak ada url(...) relatif di file-file ini (dicek), jadi base path
 *     "/assets/css/" tetap benar walau digabung.
 *
 * Cache: header.php memanggil file ini dengan ?v=<mtime terbesar dari semua
 * CSS>, jadi begitu ada 1 file CSS berubah -> URL berubah -> cache batal.
 * ETag jadi lapis kedua (304 Not Modified) untuk yang memanggil tanpa ?v.
 *
 * Berdiri sendiri (tidak lewat index.php). Ringan: readfile berurutan.
 */
declare(strict_types=1);

// URUTAN WAJIB SAMA seperti dulu di header.php (cascade & override bergantung ini).
$files = [
    'variables', 'layout', 'topbar', 'sidebar', 'dashboard', 'cards',
    'tables', 'forms', 'buttons', 'badges', 'modals', 'alerts',
    'utilities', 'responsive', 'pwa',
];

$paths = [];
$mtime = 0;
foreach ($files as $name) {
    $p = __DIR__ . '/' . $name . '.css';
    if (is_file($p)) {
        $paths[] = $p;
        $m = filemtime($p);
        if ($m !== false && $m > $mtime) {
            $mtime = $m;
        }
    }
}

$etag = '"css-' . $mtime . '-' . count($paths) . '"';

header('Content-Type: text/css; charset=utf-8');
header('Vary: Accept-Encoding');
header('ETag: ' . $etag);
// ?v=<mtime> -> boleh di-cache lama & "immutable"; tanpa ?v -> revalidasi cepat.
if (isset($_GET['v']) && $_GET['v'] !== '') {
    header('Cache-Control: public, max-age=31536000, immutable');
} else {
    header('Cache-Control: public, max-age=300, must-revalidate');
}

if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

foreach ($paths as $p) {
    echo "\n/* ===================== " . basename($p) . " ===================== */\n";
    readfile($p);
}
