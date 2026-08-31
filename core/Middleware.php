<?php
require_once ROOT_PATH . '/app/models/ActivityLog.php';

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
            header('Location: ' . BASE_URL . '/index.php?module=auth&action=login');
            exit;
        }
        checkSessionTimeout();
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
