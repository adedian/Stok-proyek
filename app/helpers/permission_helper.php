<?php

/**
 * Helper hak akses (RBAC).
 *
 * SUMBER ATURAN (sejak 2026-09-01, izin bisa diedit lewat UI):
 *   1. config/permissions.php  -- DEFAULT / seed / fallback, dan SATU-SATUNYA
 *      sumber untuk modul terkunci (settings, user, trash).
 *   2. tabel role_permissions  -- matrix per-role yang bisa dicentang admin di
 *      Pengaturan Sistem > Hak Akses. Menang atas file untuk modul tak terkunci.
 *   3. tabel user_permissions  -- override allow/deny PER-USER di atas role-nya
 *      (panel "Hak Akses" di form User Management).
 *
 * Dipakai controller (lewat Middleware::requirePermission), view (sembunyikan
 * tombol), dan sidebar (menu mana yang muncul) -- semuanya lewat can().
 */

/**
 * Modul yang izinnya TIDAK BISA diubah lewat UI dan TIDAK BISA di-override
 * per-user -- selalu dibaca dari config/permissions.php (Super Admin only).
 */
const PERMISSION_LOCKED_MODULES = ['settings', 'user', 'trash'];

/** Role yang izinnya bisa diedit di matrix (super_admin selalu full-access). */
function permissionEditableRoleSlugs(): array
{
    return ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];
}

/** Matrix statis config/permissions.php (di-cache per request). */
function permissionFileMatrix(): array
{
    static $matrix = null;
    if ($matrix === null) {
        $matrix = require ROOT_PATH . '/config/permissions.php';
    }
    return $matrix;
}

/**
 * Daftar semua pasangan module => [action, ...] yang dikenal aplikasi.
 * Sumbernya file (bukan DB) -- file tetap definisi kanonik "aksi apa saja yang
 * ada", DB hanya menentukan siapa boleh.
 */
function permissionActionCatalog(): array
{
    static $catalog = null;
    if ($catalog === null) {
        $catalog = [];
        foreach (permissionFileMatrix() as $module => $actions) {
            $catalog[$module] = array_keys($actions);
        }
    }
    return $catalog;
}

function permissionIsLockedModule(string $module): bool
{
    return in_array($module, PERMISSION_LOCKED_MODULES, true);
}

/**
 * Label tampilan module & action untuk halaman Hak Akses (Pengaturan Sistem)
 * dan panel Hak Akses di form User Management. Satu sumber supaya konsisten.
 * return: ['modules' => [slug => label], 'actions' => [slug => label]]
 */
function permissionLabelMaps(): array
{
    return [
        'modules' => [
            'dashboard' => 'Dashboard', 'account' => 'Akun Saya', 'purchase_order' => 'Purchase Order',
            'payment' => 'Pembayaran', 'goods_receipt' => 'Penerimaan Barang', 'validation' => 'Validasi Barang',
            'stock_out' => 'Pengeluaran Barang', 'inventory' => 'Stok & Opname', 'offline_purchase' => 'Pembelian Offline',
            'sales_invoice' => 'Invoice Keluar', 'delivery_note' => 'Surat Jalan', 'collection_receipt' => 'Tanda Terima',
            'cash' => 'Kas', 'cash_category' => 'Kategori Kas', 'user_pic' => 'PIC Kas',
            'report' => 'Laporan', 'user' => 'User Management', 'master_data' => 'Master Data', 'master_kode' => 'Master Kode',
            'supplier' => 'Supplier', 'client' => 'Client', 'project' => 'Project', 'item' => 'Barang',
            'item_category' => 'Kategori Barang', 'unit' => 'Satuan', 'warehouse' => 'Gudang',
            'payment_method' => 'Metode Pembayaran', 'signature' => 'Tanda Tangan', 'dp_percentage' => 'Persentase DP',
            'settings' => 'Pengaturan Sistem', 'trash' => 'Tempat Sampah',
        ],
        'actions' => [
            'view' => 'Lihat', 'create' => 'Tambah', 'edit' => 'Ubah', 'delete' => 'Hapus',
            'approve' => 'Approve', 'validate' => 'Validasi', 'quick_add' => 'Quick Add', 'complete' => 'Selesaikan',
            'restore' => 'Restore', 'force_delete' => 'Hapus Permanen', 'delete_stock' => 'Hapus Kartu Stok',
            'view_balance' => 'Lihat Saldo',
        ],
    ];
}

/**
 * Matrix hasil baca tabel role_permissions.
 * return: ['managed' => bool, 'roles' => [module][action] => [role_slug,...]]
 * managed=false kalau tabel belum ada / kosong / error -> pemanggil fallback ke file.
 */
function permissionDbMatrix(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = ['managed' => false, 'roles' => []];
    try {
        require_once ROOT_PATH . '/app/models/RolePermission.php';
        $rows = (new RolePermission())->allRows();
        if (!$rows) {
            return $cache;
        }
        $roles = [];
        foreach ($rows as $r) {
            if ((int) $r['allowed'] === 1) {
                $roles[$r['module']][$r['action']][] = $r['role_slug'];
            }
        }
        $cache = ['managed' => true, 'roles' => $roles];
    } catch (Throwable $e) {
        error_log('permissionDbMatrix gagal, fallback ke config/permissions.php: ' . $e->getMessage());
        $cache = ['managed' => false, 'roles' => []];
    }
    return $cache;
}

/**
 * Daftar role_slug yang diizinkan untuk satu module+action.
 * Module/action tak terdaftar => tidak ada yang boleh (fail closed).
 * Super Admin selalu ikut disertakan untuk modul tak terkunci.
 */
function permissionRoles(string $module, string $action): array
{
    if (permissionIsLockedModule($module)) {
        return permissionFileMatrix()[$module][$action] ?? [];
    }

    $db = permissionDbMatrix();
    if ($db['managed']) {
        $roles = $db['roles'][$module][$action] ?? [];
        if (!in_array(ROLE_SUPER_ADMIN, $roles, true)) {
            $roles[] = ROLE_SUPER_ADMIN;
        }
        return $roles;
    }

    return permissionFileMatrix()[$module][$action] ?? [];
}

/**
 * Apakah SEBUAH role (mengabaikan override per-user) boleh $action di $module.
 * Dipakai form/controller User Management untuk menghitung "default role".
 */
function roleAllows(string $roleSlug, string $module, string $action): bool
{
    if ($roleSlug === ROLE_SUPER_ADMIN) {
        return true;
    }
    return in_array($roleSlug, permissionRoles($module, $action), true);
}

/** [ "module.action" => 'allow'|'deny' ] override milik satu user (di-cache). */
function userPermissionMap(int $userId): array
{
    static $cache = [];
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }

    $map = [];
    try {
        require_once ROOT_PATH . '/app/models/UserPermission.php';
        $map = (new UserPermission())->mapForUser($userId);
    } catch (Throwable $e) {
        error_log('userPermissionMap gagal: ' . $e->getMessage());
        $map = [];
    }

    $cache[$userId] = $map;
    return $map;
}

/**
 * Apakah user yang sedang login boleh melakukan $action di $module.
 * Urutan: Super Admin -> modul terkunci (role dari file) -> override user
 * (deny/allow) -> matrix role (DB/file).
 */
function can(string $module, string $action): bool
{
    $role = currentUserRole();
    if (!$role) {
        return false;
    }
    if ($role === ROLE_SUPER_ADMIN) {
        return true;
    }

    if (permissionIsLockedModule($module)) {
        return in_array($role, permissionFileMatrix()[$module][$action] ?? [], true);
    }

    $userId = currentUserId();
    if ($userId) {
        $effect = userPermissionMap($userId)["{$module}.{$action}"] ?? null;
        if ($effect === 'deny') {
            return false;
        }
        if ($effect === 'allow') {
            return true;
        }
    }

    return in_array($role, permissionRoles($module, $action), true);
}

function canView(string $module): bool
{
    return can($module, 'view');
}

function canCreate(string $module): bool
{
    return can($module, 'create');
}

function canEdit(string $module): bool
{
    return can($module, 'edit');
}

function canDelete(string $module): bool
{
    return can($module, 'delete');
}

function canApprove(string $module): bool
{
    return can($module, 'approve');
}

function canValidate(string $module): bool
{
    return can($module, 'validate');
}

function canQuickAdd(string $module): bool
{
    return can($module, 'quick_add');
}

/**
 * Tolak akses server-side dengan 403 + catat ke activity log. Dipakai untuk
 * pembatasan yang LEBIH HALUS dari matrix module/action -- mis. row-level
 * (user hanya boleh baris Kas milik PIC terkaitnya) atau scope laporan.
 * Selalu berhenti (exit) setelah dipanggil.
 */
function denyAccess(string $reason = 'Akses ditolak'): void
{
    $module = $_GET['module'] ?? '-';
    $action = $_GET['action'] ?? '-';
    if (class_exists('ActivityLog')) {
        (new ActivityLog())->log(
            currentUserId(),
            $module,
            'access_denied',
            "{$reason} (module={$module}&action={$action}, role=" . (currentUserRole() ?? '-') . ')'
        );
    }
    http_response_code(403);
    require ROOT_PATH . '/app/views/errors/403.php';
    exit;
}
