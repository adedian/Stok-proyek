<?php
/**
 * Konfigurasi koneksi database
 * Sesuaikan DB_USER dan DB_PASS dengan MySQL/phpMyAdmin lokal Anda.
 * Nama database HARUS sama dengan yang diimport dari database/schema.sql (Phase 2).
 */

// Kredensial di-override oleh config/local.php (lihat config/config.php yang
// mengisi $GLOBALS['__APP_LOCAL']). Kalau tidak ada -> pakai default di bawah.
// JANGAN commit password produksi ke file ini -- taruh di config/local.php.
$__local = $GLOBALS['__APP_LOCAL'] ?? [];
define('DB_HOST',    (isset($__local['db_host'])    && $__local['db_host']    !== '') ? $__local['db_host']    : 'localhost');
define('DB_NAME',    (isset($__local['db_name'])    && $__local['db_name']    !== '') ? $__local['db_name']    : 'db_stok_proyek');
define('DB_USER',    (isset($__local['db_user'])    && $__local['db_user']    !== '') ? $__local['db_user']    : 'root');
define('DB_PASS',    array_key_exists('db_pass', $__local) ? (string) $__local['db_pass'] : '');
define('DB_CHARSET', (isset($__local['db_charset']) && $__local['db_charset'] !== '') ? $__local['db_charset'] : 'utf8mb4');

function getPDO()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Samakan timezone koneksi dengan timezone aplikasi (WIB, lihat config.php)
            // secara eksplisit -- jangan bergantung pada setting SYSTEM milik server MySQL
            // (implisit, bisa beda kalau aplikasi ini suatu saat dipindah ke server lain).
            // NOW()/CURRENT_TIMESTAMP() jadi selalu WIB apa pun timezone OS server MySQL-nya.
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            error_log('DB Connection Error: ' . $e->getMessage());
            die('Koneksi database gagal. Silakan hubungi administrator.');
        }
    }

    return $pdo;
}
