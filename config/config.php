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

// BASE_URL mengikuti host yang dipakai browser untuk mengakses server ini
// (localhost ATAU alamat IP LAN, mis. http://192.168.100.125/stok-proyek/public)
// supaya aplikasi tetap jalan diakses dari komputer lain di jaringan yang sama
// tanpa perlu ubah config per-komputer.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host . '/stok-proyek/public');

// Lingkungan ditentukan otomatis dari host: akses lewat localhost/127.0.0.1
// dianggap 'development' (developer sedang menguji, error tampil penuh).
// Akses lewat alamat lain (mis. IP LAN tempat aplikasi dipakai sehari-hari)
// dianggap 'production' -- error TIDAK ditampilkan ke user, hanya dicatat ke
// logs/error.log. Untuk memaksa salah satu mode, ganti baris di bawah dengan
// define('APP_ENV', 'development'); atau 'production';.
$appHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('APP_ENV', preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $appHost) ? 'development' : 'production');

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

// Backup Database (Pengaturan Sistem) -- path default instalasi XAMPP.
// Satu-satunya tempat di sistem yang menjalankan shell command, dibatasi Super Admin saja.
define('MYSQLDUMP_PATH', 'C:\\xampp\\mysql\\bin\\mysqldump.exe');
define('BACKUP_PATH', ROOT_PATH . '/storage/backups');
