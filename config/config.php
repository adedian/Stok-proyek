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

define('APP_ENV', 'development'); // development | production

// Tampilkan error hanya saat development
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
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
