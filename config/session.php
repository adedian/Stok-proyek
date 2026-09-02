<?php
/**
 * Konfigurasi & inisialisasi session
 * Wajib di-include di paling atas public/index.php SEBELUM output apapun
 */

/**
 * Timeout sesi tidak aktif (detik), dibaca dari Pengaturan Sistem > Session
 * (tabel system_settings, key session_timeout_minutes). Query langsung pakai
 * getPDO() (sudah tersedia dari config/database.php yang di-load sebelum file
 * ini) supaya tidak perlu menunggu core/Model.php dimuat. Fallback 1800 detik
 * (30 menit) kalau setting belum ada / query gagal -- session TIDAK BOLEH
 * pernah gagal gara-gara ini.
 */
function resolveSessionTimeout(): int
{
    $fallback = 1800;
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'session_timeout_minutes' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && is_numeric($row['setting_value'])) {
            return max(300, (int) $row['setting_value'] * 60);
        }
    } catch (Throwable $e) {
        // Tabel/kolom belum ada atau query gagal -- diamkan, pakai fallback.
    }
    return $fallback;
}

define('SESSION_TIMEOUT', resolveSessionTimeout());

// Hardening cookie session
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

// cookie_secure otomatis: ON kalau request lewat HTTPS (langsung maupun di
// belakang proxy/CDN yang meneruskan X-Forwarded-Proto). Di dev lokal (http)
// tetap OFF supaya cookie tidak hilang. Tidak perlu diutak-atik manual.
$__https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
ini_set('session.cookie_secure', $__https ? '1' : '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek timeout: jika user idle melebihi SESSION_TIMEOUT, paksa logout
 */
function checkSessionTimeout()
{
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            session_unset();
            session_destroy();
            // route() belum ter-load di titik ini (functions.php di-include setelah
            // session.php) -- susun URL bersih manual.
            header('Location: ' . BASE_URL . '/auth/login?expired=1');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Regenerate session ID -- WAJIB dipanggil setiap kali login berhasil
 * untuk mencegah session fixation attack
 */
function regenerateSession()
{
    session_regenerate_id(true);
}
