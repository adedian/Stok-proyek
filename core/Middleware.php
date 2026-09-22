<?php
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/User.php';

/**
 * Middleware
 * Dipanggil di awal method controller yang butuh proteksi.
 */
class Middleware
{
    /**
     * Pastikan user sudah login, kalau belum redirect ke halaman login
     */
    public static function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . route('auth', 'login'));
            exit;
        }
        checkSessionTimeout();
        self::requireActiveAccount();
    }

    /**
     * Verifikasi user di sesi MASIH ADA & status aktif di database -- supaya
     * akun yang baru dihapus/dinonaktifkan Super Admin langsung ter-tolak di
     * request berikutnya, tidak menunggu sesi lama habis sendiri (idle
     * timeout, bisa sampai puluhan menit). Sebelum ada ini, sesi lama hanya
     * dipercaya dari isi $_SESSION tanpa dicek ulang ke DB.
     *
     * Dicek sekali per request (bukan tiap panggilan Middleware dalam satu
     * request yang sama) lewat static flag -- query-nya ringan (lookup PK),
     * tapi tetap sayang diulang kalau satu controller memanggil lebih dari
     * satu method Middleware.
     */
    private static function requireActiveAccount(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $user = (new User())->find((int) $_SESSION['user_id']);
        if (!$user || $user['status'] !== 'active') {
            session_unset();
            session_destroy();
            header('Location: ' . route('auth', 'login') . '?invalid=1');
            exit;
        }
    }

    /**
     * Alias eksplisit untuk requireAuth() -- dipakai di controller yang
     * cuma butuh "wajib login", tanpa syarat role tertentu.
     */
    public static function requireLogin(): void
    {
        self::requireAuth();
    }

    /**
     * Pastikan role user termasuk dalam daftar role yang diizinkan.
     * Contoh: Middleware::requireRole(['super_admin', 'finance']);
     */
    public static function requireRole(array $allowedRoles): void
    {
        self::requireAuth();

        $userRole = $_SESSION['role_slug'] ?? null;

        if (!$userRole || !in_array($userRole, $allowedRoles, true)) {
            $module = $_GET['module'] ?? '-';
            $action = $_GET['action'] ?? '-';
            (new ActivityLog())->log(
                currentUserId(),
                $module,
                'access_denied',
                "Akses ditolak: role '{$userRole}' mencoba module={$module}&action={$action}"
            );

            http_response_code(403);
            require ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }
    }

    /**
     * Alias eksplisit untuk requireRole() -- nama yang lebih jelas maksudnya
     * "boleh lolos kalau role user termasuk salah satu dari daftar ini".
     * Contoh: Middleware::requireAnyRole(['finance', 'super_admin']);
     */
    public static function requireAnyRole(array $allowedRoles): void
    {
        self::requireRole($allowedRoles);
    }

    /**
     * Cara utama proteksi module+action. Memakai can() supaya SEMUA lapisan
     * ikut dipertimbangkan: Super Admin, modul terkunci, matrix role yang
     * bisa diedit admin (role_permissions), DAN override per-user
     * (user_permissions). Gerbang server-side ini identik dengan cek can()
     * yang menyembunyikan tombol di view.
     * Contoh: Middleware::requirePermission('purchase_order', 'create');
     */
    public static function requirePermission(string $module, string $action): void
    {
        self::requireAuth();

        if (!can($module, $action)) {
            $userRole = $_SESSION['role_slug'] ?? '-';
            $reqModule = $_GET['module'] ?? $module;
            $reqAction = $_GET['action'] ?? $action;
            (new ActivityLog())->log(
                currentUserId(),
                $reqModule,
                'access_denied',
                "Akses ditolak: role '{$userRole}' mencoba {$module}.{$action} (module={$reqModule}&action={$reqAction})"
            );

            http_response_code(403);
            require ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }
    }
}
