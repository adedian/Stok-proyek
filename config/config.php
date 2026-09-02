<?php
/**
 * Konfigurasi global aplikasi
 */

// Timezone aplikasi: WIB (project dipakai di Surabaya). SATU-SATUNYA tempat
// timezone PHP diset -- semua date()/DateTime/strtotime/time() di seluruh
// codebase ikut ini secara otomatis, termasuk jam pada hasil cetak.
// php.ini server ini default ke Europe/Berlin (bukan WIB) dan tidak ada
// override lain di manapun -- dikonfirmasi lewat audit: MySQL NOW() sudah
// SAMA PERSIS dengan waktu Asia/Jakarta (server DB sudah berjalan di zona WIB),
// jadi baris ini HANYA menyamakan PHP dengan apa yang sudah benar di database,
// TIDAK mengonversi/mengubah data historis yang sudah tersimpan.
date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'Sistem Kontrol Stok Proyek');

// ---------------------------------------------------------------------------
// Konfigurasi lokal / per-server (config/local.php -- TIDAK di-commit).
// Dipakai untuk APP_ENV, kredensial DB (via config/database.php), & path
// mysqldump. Kalau file tidak ada -> array kosong -> semua pakai default aman.
// Template: config/local.example.php
// ---------------------------------------------------------------------------
$APP_LOCAL = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
if (!is_array($APP_LOCAL)) {
    $APP_LOCAL = [];
}
$GLOBALS['__APP_LOCAL'] = $APP_LOCAL;

// BASE_URL mengikuti host yang dipakai browser untuk mengakses server ini
// (localhost ATAU alamat IP LAN, mis. http://192.168.100.125/stok-proyek/public)
// supaya aplikasi tetap jalan diakses dari komputer lain di jaringan yang sama
// tanpa perlu ubah config per-komputer.
//
// APP_BASE_PATH = bagian path tempat aplikasi "duduk", dihitung otomatis dari
// lokasi front controller:
//   - diakses via sub-folder  -> "/stok-proyek/public"
//   - DocumentRoot = folder public/ (mis. stok.hexamultienergi.com) -> ""
// Dipakai Router untuk memotong prefix path, dan route()/BASE_URL untuk
// menyusun URL bersih tanpa "index.php?module=".
if (PHP_SAPI === 'cli') {
    $appBasePath = '';
} else {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $appBasePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
}
define('APP_BASE_PATH', $appBasePath);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host . APP_BASE_PATH);

// Lingkungan ditentukan SECARA EKSPLISIT, bukan ditebak dari Host header
// (Host bisa dipalsukan penyerang -> "Host: localhost" ke server produksi dulu
// bisa memaksa mode development & membocorkan stack trace/path).
// Urutan sumber: config/local.php  ->  env var APP_ENV  ->  default 'production'.
$appEnv = $APP_LOCAL['app_env'] ?? (getenv('APP_ENV') ?: 'production');
define('APP_ENV', $appEnv === 'development' ? 'development' : 'production');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    // Production: jangan pernah bocorkan warning/stack trace/SQL error/path server.
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');

    // Tangkap error/exception yang tidak tertangani -> catat ke log, tampilkan
    // pesan generik. Tanpa ini, PDOException dsb bisa muncul mentah ke layar.
    set_exception_handler(static function (\Throwable $e): void {
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo 'Terjadi kesalahan pada server. Silakan coba lagi atau hubungi administrator.';
    });
    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            error_log("Fatal: {$err['message']} @ {$err['file']}:{$err['line']}");
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo 'Terjadi kesalahan pada server. Silakan coba lagi atau hubungi administrator.';
        }
    });
}

// Path absolut penting
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/public/uploads');
define('LOG_PATH', ROOT_PATH . '/logs/error.log');

ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH);

// Rotasi log sederhana: kalau error.log sudah > 5 MB, arsipkan dengan timestamp
// lalu buang arsip yang lebih tua dari 30 hari. Aman untuk balapan antar-request
// (@rename yang kalah tinggal gagal diam-diam). Cek filesize = 1 stat, murah.
if (@is_file(LOG_PATH) && @filesize(LOG_PATH) > 5 * 1024 * 1024) {
    @rename(LOG_PATH, LOG_PATH . '.' . date('Ymd_His'));
    foreach (glob(LOG_PATH . '.*') ?: [] as $__old) {
        if (is_file($__old) && filemtime($__old) < time() - 30 * 86400) {
            @unlink($__old);
        }
    }
}

// Daftar role yang valid (harus sinkron dengan tabel `roles`)
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_PROJECT_MANAGER', 'project_manager');

// Role baru Revisi 9 (2026-08-28) -- menggantikan Finance/Gudang.
define('ROLE_PURCHASE', 'purchase');
define('ROLE_ACCOUNTING', 'accounting');
define('ROLE_PIC_PROJECT', 'pic_project');
define('ROLE_ADMIN_PROJECT', 'admin_project');

// DEPRECATED (Revisi 9): role 'finance' & 'gudang' tidak lagi ditawarkan
// sebagai pilihan (lihat Role::assignableList()). Konstanta DIPERTAHANKAN
// karena baris role-nya masih ada di DB untuk histori dan sejumlah cek
// UI lama sudah di-remap ke role baru. Jangan dipakai untuk aturan baru.
define('ROLE_FINANCE', 'finance');
define('ROLE_GUDANG', 'gudang');

// Backup Database (Pengaturan Sistem) -- satu-satunya tempat yang menjalankan
// shell command, dibatasi Super Admin. Path mysqldump:
//   1) config/local.php 'mysqldump_path'  (kalau diisi)
//   2) default sesuai OS: Windows/XAMPP -> mysqldump.exe ; Linux -> 'mysqldump'
//      (umumnya sudah ada di PATH pada hosting Linux).
$mysqldumpDefault = (DIRECTORY_SEPARATOR === '\\')
    ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe'
    : 'mysqldump';
define('MYSQLDUMP_PATH', !empty($APP_LOCAL['mysqldump_path']) ? $APP_LOCAL['mysqldump_path'] : $mysqldumpDefault);
define('BACKUP_PATH', ROOT_PATH . '/storage/backups');
