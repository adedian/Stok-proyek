<?php

/**
 * Format angka ke Rupiah. Format nominal baru: koma = pemisah ribuan,
 * titik = pemisah desimal, selalu 2 digit desimal (mis. "Rp 15,000.73").
 * SATU-SATUNYA formatter nominal di seluruh sistem -- jangan buat formatter
 * baru per modul, semua tempat yang menampilkan uang pakai fungsi ini.
 */
function formatRupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 2, '.', ',');
}

/**
 * Format persentase tanpa nol desimal berlebihan: 50.00 -> "50", 11.50 -> "11.5".
 * Dipakai untuk label "Tagihan (DP 50%)"/"PPN (11%)" di Invoice Keluar supaya
 * tidak menampilkan "50.00%" -- selalu dibangun dari ANGKA (dp_percentage/
 * ppn_percent), bukan dari nama bebas master, supaya label selalu konsisten.
 */
function formatPercent($value): string
{
    return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
}

/**
 * Format tanggal Indonesia: 2026-08-13 -> 13 Agustus 2026
 */
function formatTanggal(?string $date): string
{
    if (empty($date)) {
        return '-';
    }
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
    $ts = strtotime($date);
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Format tanggal Indonesia lengkap dengan nama hari: "Selasa, 18 Agustus 2026".
 * Dipakai untuk badge tanggal di dashboard.
 */
function formatTanggalLengkap(?string $date = null): string
{
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $ts = $date ? strtotime($date) : time();
    return $hari[(int) date('w', $ts)] . ', ' . formatTanggal(date('Y-m-d', $ts));
}

/**
 * Notes kecil "Tanggal & Jam: dd-mm-YYYY HH:MM WIB" untuk pojok kanan bawah
 * setiap hasil print/export (Revisi 7 #14) -- pakai date() biasa karena
 * date_default_timezone_set('Asia/Jakarta') sudah di-set global di config.php,
 * jadi server time = WIB, tidak perlu konversi timezone manual.
 */
function printedAtLabel(): string
{
    return date('d-m-Y H:i') . ' WIB';
}

/**
 * Notes kecil "Dicetak oleh: [nama user login]" -- SELALU dari akun yang sedang
 * login (currentUserName()), jangan pernah hardcode.
 */
function printedByLabel(): string
{
    return currentUserName();
}

/**
 * Waktu relatif ("5 menit lalu", "2 jam lalu") untuk feed aktivitas terbaru.
 * Jatuh balik ke tanggal lengkap kalau sudah lebih dari 7 hari.
 */
function waktuLalu(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'Baru saja';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    }
    if ($diff < 7 * 86400) {
        return floor($diff / 86400) . ' hari lalu';
    }
    return formatTanggal(substr($datetime, 0, 10));
}

/**
 * Escape output HTML (cegah XSS) -- selalu bungkus data dinamis dengan ini di view
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * URL asset lokal (public/assets/...) + query string versi berdasarkan filemtime().
 * Server tidak mengirim header Cache-Control untuk file statis, jadi browser bisa
 * saja terus memakai CSS/JS/gambar versi lama (heuristic caching) walau filenya
 * sudah diubah di server -- pernah menyebabkan tampilan rusak (CSS baru belum
 * dipakai) dan bug lama seolah muncul lagi (JS lama masih jalan). Query string
 * ?v=<mtime> otomatis berubah tiap file di-edit, jadi browser wajib ambil versi
 * baru tanpa perlu hard refresh manual. Dipakai untuk SEMUA <link>/<script>/<img>
 * yang menunjuk ke public/assets/ (bukan CDN eksternal, itu sudah versioned lewat URL-nya).
 */
function assetUrl(string $relativePath): string
{
    $fullPath = ROOT_PATH . '/public' . $relativePath;
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();
    return BASE_URL . $relativePath . '?v=' . $version;
}

/**
 * URL untuk menampilkan file upload. Folder SENSITIF (bukti pembayaran, invoice
 * supplier, bukti pembelian offline) dialihkan lewat FileController yang
 * mengecek login + role; folder lain (foto barang, logo, tanda tangan, foto
 * profil, surat jalan) tetap dilayani langsung oleh web server seperti sebelumnya.
 *
 * $relPath = nilai yang tersimpan di DB, mis. 'uploads/payments/ab12...jpg'.
 * Aman dipakai untuk semua path upload -- yang tidak diproteksi menghasilkan
 * URL yang identik dengan cara lama (BASE_URL . '/' . path).
 */
function fileUrl(?string $relPath): string
{
    $relPath = ltrim((string) $relPath, '/');
    if ($relPath === '') {
        return '';
    }

    static $gated = ['payments', 'bukti_pembelian', 'invoice_penerimaan', 'invoice'];
    if (preg_match('#^uploads/([a-z0-9_]+)/#', $relPath, $m) && in_array($m[1], $gated, true)) {
        return route('file', 'show', ['path' => $relPath]);
    }
    return BASE_URL . '/' . $relPath;
}

/**
 * Susun URL bersih ke sebuah module/action.
 *   route('dashboard')                          -> {BASE_URL}/dashboard
 *   route('purchase_order')                     -> {BASE_URL}/purchase_order
 *   route('purchase_order', 'create')           -> {BASE_URL}/purchase_order/create
 *   route('purchase_order', 'edit', ['id'=>5])  -> {BASE_URL}/purchase_order/edit/5
 *   route('settings', 'index', ['tab'=>'bank']) -> {BASE_URL}/settings?tab=bank
 *   route('report', 'export', ['type'=>'po','format'=>'pdf'])
 *                                               -> {BASE_URL}/report/export?type=po&format=pdf
 *
 * `id` numerik jadi segmen path; param lain jadi query string. Router tetap
 * menerima format lama (index.php?module=..&action=..) sebagai fallback.
 */
function route(string $module, string $action = 'index', array $params = []): string
{
    $path = '/' . rawurlencode($module);

    $idSegment = '';
    if (isset($params['id']) && ctype_digit((string) $params['id'])) {
        $idSegment = '/' . $params['id'];
        unset($params['id']);
    }

    if ($action !== 'index' || $idSegment !== '') {
        $path .= '/' . rawurlencode($action);
    }
    $path .= $idSegment;

    $query = $params ? '?' . http_build_query($params) : '';

    return BASE_URL . $path . $query;
}

/**
 * Kirim header keamanan HTTP ke SETIAP response. Dipanggil sekali di
 * public/index.php sebelum routing. CSP sengaja mengizinkan 'unsafe-inline'
 * dan cdn.jsdelivr.net karena aplikasi memakai <script>/style inline serta
 * Bootstrap/SweetAlert2/Chart.js dari CDN itu -- selebihnya dikunci.
 */
function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header_remove('X-Powered-By');

    $csp = "default-src 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "img-src 'self' data: blob:; "
        . "media-src 'self'; "
        . "font-src 'self' data: https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "connect-src 'self'";
    header('Content-Security-Policy: ' . $csp);

    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Generate & simpan CSRF token ke session
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Cetak hidden input CSRF -- dipakai di dalam <form>
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Validasi CSRF token dari request POST. Panggil di awal setiap action store/update/delete.
 */
function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        // 403: kode standar untuk request yang ditolak validasi keamanan.
        // (419 non-standar dan disulap jadi "500" oleh sebagian SAPI/Apache.)
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        die('Sesi form tidak valid atau kadaluarsa. Silakan muat ulang halaman lalu coba lagi.');
    }
}

/**
 * Simpan flash message ke session untuk ditampilkan sekali di halaman berikutnya
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Hitung info pagination server-side (halaman valid, offset, dst) -- dipakai
 * seragam di semua list Master Data. $page di-clamp ke rentang yang valid.
 */
function paginationInfo(int $totalRows, int $page, int $perPage = 15): array
{
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = max(1, min($page, $totalPages));
    return [
        'currentPage' => $page,
        'totalPages'  => $totalPages,
        'totalRows'   => $totalRows,
        'perPage'     => $perPage,
        'offset'      => ($page - 1) * $perPage,
    ];
}

/**
 * Panah kecil penanda kolom yang sedang di-sort, dipakai di header tabel
 * Master Data yang bisa di-sort. Kembalikan string kosong kalau bukan kolom aktif.
 */
function sortIndicator(string $column, string $activeSort, string $activeDir): string
{
    if ($column !== $activeSort) {
        return '';
    }
    return $activeDir === 'asc' ? ' ▲' : ' ▼';
}

/**
 * Parse keyword pencarian kode master (SUP-0001, ITM-00001, dst) supaya toleran
 * terhadap spasi/strip/huruf besar-kecil/padding angka. Contoh: "sup 01", "SUP01",
 * "SUP-01", "sup-0001" semua harus bisa menemukan "SUP-0001".
 * Return null kalau keyword tidak berbentuk [huruf][angka] (berarti bukan pencarian
 * kode -- pencarian nama biasa tetap jalan lewat LIKE terpisah).
 */
function parseCodeSearchTerm(string $keyword): ?array
{
    $trimmed = trim($keyword);
    if (!preg_match('/^([A-Za-z]+)[\s\-]*0*(\d+)$/', $trimmed, $m)) {
        return null;
    }
    return ['prefix' => strtoupper($m[1]), 'number' => (int) $m[2]];
}

/**
 * Bangun fragment SQL + parameter untuk pencarian kode fleksibel di atas, dipakai
 * seragam oleh semua model master data (Item/Supplier/Project/Client). $column
 * harus nama kolom kode yang sudah aman (bukan input user), format "PREFIX-00001".
 */
function codeSearchClause(string $column, string $keyword, string $paramPrefix): array
{
    $parsed = parseCodeSearchTerm($keyword);
    if ($parsed === null) {
        return ['', []];
    }
    $sql = " OR ({$column} LIKE :{$paramPrefix}_prefix AND "
        . "CAST(SUBSTRING_INDEX({$column}, '-', -1) AS UNSIGNED) = :{$paramPrefix}_number)";
    return [$sql, [
        "{$paramPrefix}_prefix" => $parsed['prefix'] . '-%',
        "{$paramPrefix}_number" => $parsed['number'],
    ]];
}

/**
 * Bersihkan input harga/nominal format baru (mis. "15,000.73") jadi angka murni
 * (15000.73) sebelum disimpan ke database. Dipakai di setiap controller yang
 * menerima input rupiah dari field .currency-input (lihat
 * public/assets/js/currency-input.js).
 *
 * ATURAN PARSING (jangan diubah tanpa audit ulang -- lihat requirement #13 revisi
 * timezone/nominal): KOMA SELALU pemisah ribuan (dibuang), TITIK SELALU pemisah
 * desimal (dipertahankan, maks 2 digit). Ini beda dari parser lama yang membuang
 * SEMUA karakter non-digit (termasuk titik dan koma) -- sengaja tidak dipakai lagi
 * karena tidak bisa membedakan "15.50" (lima belas koma lima puluh) dari "1550".
 */
function parseCurrencyInput($raw): float
{
    $str = trim((string) $raw);
    if ($str === '') {
        return 0.0;
    }

    // Koma = pemisah ribuan -> buang semua.
    $str = str_replace(',', '', $str);

    // Titik = pemisah desimal. Kalau ternyata ada lebih dari satu titik (input
    // lama/tidak rapi), anggap titik-titik sebelumnya sebagai pemisah ribuan juga
    // dan pertahankan hanya titik TERAKHIR sebagai desimal -- supaya tidak pernah
    // menghasilkan angka acak dari input yang membingungkan.
    if (substr_count($str, '.') > 1) {
        $lastDot = strrpos($str, '.');
        $str = str_replace('.', '', substr($str, 0, $lastDot)) . substr($str, $lastDot);
    }

    if (!is_numeric($str)) {
        $str = preg_replace('/[^0-9.\-]/', '', $str);
    }

    return ($str === '' || !is_numeric($str)) ? 0.0 : round((float) $str, 2);
}

/**
 * Format satu nilai kolom laporan sesuai tipenya -- dipakai bareng oleh
 * tampilan tabel, export CSV, dan export PDF di modul Laporan supaya konsisten.
 */
function formatReportValue($value, string $format = 'text'): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    switch ($format) {
        case 'rupiah':
            return formatRupiah($value);
        case 'date':
            return formatTanggal($value);
        case 'number':
            return number_format((float) $value, 2, ',', '.');
        case 'datetime':
            return formatTanggal(substr((string) $value, 0, 10)) . ' ' . substr((string) $value, 11, 5);
        case 'percent':
            return formatPercent($value) . '%';
        default:
            return (string) $value;
    }
}
