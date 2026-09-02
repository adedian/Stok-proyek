<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/Role.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/UserPermission.php';

class UserController extends Controller
{
    private User $userModel;
    private Role $roleModel;
    private ActivityLog $activityLog;
    private UserPermission $userPermModel;

    public function __construct()
    {
        Middleware::requirePermission('user', 'view');

        $this->userModel     = new User();
        $this->roleModel     = new Role();
        $this->activityLog   = new ActivityLog();
        $this->userPermModel = new UserPermission();
    }

    public function index()
    {
        $this->view('user/list', [
            'pageTitle' => 'User Management',
            'users'     => $this->userModel->listWithRole(),
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('user', 'create');

        $this->view('user/form', array_merge([
            'pageTitle' => 'Tambah User',
            'mode'      => 'create',
            'user'      => null,
            'roles'     => $this->roleModel->assignableList(),
        ], $this->permissionPanelData(null)));
    }

    public function store()
    {
        Middleware::requirePermission('user', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('user', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data, true);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('user', 'create');
        }

        $newId = $this->userModel->create([
            'role_id'    => $data['role_id'],
            'full_name'  => $data['full_name'],
            'username'   => $data['username'],
            'email'      => $data['email'],
            'password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            'status'     => 'active',
            'created_by' => currentUserId(),
        ]);

        $this->savePermissionOverrides($newId, (int) $data['role_id']);

        $this->activityLog->log(currentUserId(), 'user', 'create', "User '{$data['username']}' dibuat");

        setFlash('success', 'User berhasil dibuat.');
        $this->redirect('user', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('user', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $user = $this->userModel->findWithRole($id);

        if (!$user) {
            setFlash('error', 'User tidak ditemukan.');
            $this->redirect('user', 'index');
        }

        $this->view('user/form', array_merge([
            'pageTitle' => 'Edit User',
            'mode'      => 'edit',
            'user'      => $user,
            'roles'     => $this->roleModel->assignableList(),
        ], $this->permissionPanelData((int) $user['id'])));
    }

    public function update()
    {
        Middleware::requirePermission('user', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('user', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->userModel->find($id);

        if (!$existing) {
            setFlash('error', 'User tidak ditemukan.');
            $this->redirect('user', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data, false, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('user', 'edit', ['id' => $id]);
        }

        $updateData = [
            'role_id'   => $data['role_id'],
            'full_name' => $data['full_name'],
            'username'  => $data['username'],
            'email'     => $data['email'],
        ];
        if ($data['password'] !== '') {
            $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $this->userModel->updateById($id, $updateData);

        $this->savePermissionOverrides($id, (int) $data['role_id']);

        $this->activityLog->log(currentUserId(), 'user', 'update', "User '{$data['username']}' diperbarui");

        setFlash('success', 'User berhasil diperbarui.');
        $this->redirect('user', 'index');
    }

    /**
     * Aktifkan/nonaktifkan user. TIDAK PERNAH delete -- banyak tabel lain
     * (activity_logs, purchase_orders, dst) FK ke created_by/user_id.
     */
    public function toggleStatus()
    {
        Middleware::requirePermission('user', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('user', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $user = $this->userModel->findWithRole($id);

        if (!$user) {
            setFlash('error', 'User tidak ditemukan.');
            $this->redirect('user', 'index');
        }

        if ($id === currentUserId()) {
            setFlash('error', 'Anda tidak bisa menonaktifkan akun sendiri.');
            $this->redirect('user', 'index');
        }

        $isLastActiveSuperAdmin = $user['role_slug'] === ROLE_SUPER_ADMIN
            && $user['status'] === 'active'
            && $this->userModel->countActiveByRoleSlug(ROLE_SUPER_ADMIN) <= 1;

        if ($isLastActiveSuperAdmin) {
            setFlash('error', 'Tidak bisa menonaktifkan Super Admin aktif terakhir.');
            $this->redirect('user', 'index');
        }

        $newStatus = $this->userModel->toggleStatus($id);

        $this->activityLog->log(
            currentUserId(),
            'user',
            'toggle_status',
            "User '{$user['username']}' diubah statusnya menjadi {$newStatus}"
        );

        setFlash('success', "Status user berhasil diubah menjadi {$newStatus}.");
        $this->redirect('user', 'index');
    }

    /**
     * Hapus akun user (soft delete -- KHUSUS Super Admin).
     * Akun jadi tidak bisa login & hilang dari semua daftar; referensi historis
     * (created_by, validated_by, activity log, dst) tetap utuh. Pemulihan hanya
     * lewat DB. Tidak bisa hapus diri sendiri atau Super Admin terakhir.
     */
    public function delete()
    {
        Middleware::requirePermission('user', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('user', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $user = $this->userModel->findWithRole($id);

        if (!$user) {
            setFlash('error', 'User tidak ditemukan.');
            $this->redirect('user', 'index');
        }

        if ($id === currentUserId()) {
            setFlash('error', 'Anda tidak bisa menghapus akun sendiri.');
            $this->redirect('user', 'index');
        }

        if ($user['role_slug'] === ROLE_SUPER_ADMIN
            && $this->userModel->countByRoleSlug(ROLE_SUPER_ADMIN) <= 1) {
            setFlash('error', 'Tidak bisa menghapus Super Admin terakhir.');
            $this->redirect('user', 'index');
        }

        $this->userModel->deleteById($id);

        $this->activityLog->log(
            currentUserId(),
            'user',
            'delete',
            "Akun '{$user['username']}' ({$user['full_name']}) dihapus"
        );

        setFlash('success', "Akun '{$user['username']}' berhasil dihapus.");
        $this->redirect('user', 'index');
    }

    // ================= Helper privat =================

    private function collectInput(): array
    {
        return [
            'role_id'   => (int) ($_POST['role_id'] ?? 0),
            'full_name' => trim($_POST['full_name'] ?? ''),
            'username'  => trim($_POST['username'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
            'password'  => $_POST['password'] ?? '',
        ];
    }

    private function validateInput(array $data, bool $isCreate = false, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['role_id'] <= 0) {
            $errors[] = 'Role wajib dipilih.';
        } else {
            // Revisi 9: cegah assign ke role yang sudah dinonaktifkan
            // (finance/gudang) walau id-nya diselundupkan lewat POST.
            $assignableIds = array_map(static fn($r) => (int) $r['id'], $this->roleModel->assignableList());
            if (!in_array((int) $data['role_id'], $assignableIds, true)) {
                $errors[] = 'Role tidak valid / sudah tidak aktif.';
            }
        }
        if ($data['full_name'] === '') {
            $errors[] = 'Nama lengkap wajib diisi.';
        }
        if ($data['username'] === '') {
            $errors[] = 'Username wajib diisi.';
        } elseif ($this->userModel->usernameExists($data['username'], $excludeId)) {
            $errors[] = 'Username sudah dipakai.';
        }
        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email tidak valid.';
        } elseif ($this->userModel->emailExists($data['email'], $excludeId)) {
            $errors[] = 'Email sudah dipakai.';
        }

        if ($isCreate && strlen($data['password']) < 6) {
            $errors[] = 'Password minimal 6 karakter.';
        } elseif (!$isCreate && $data['password'] !== '' && strlen($data['password']) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        }

        return $errors;
    }

    // ================= Panel Hak Akses (override per-user) =================

    /**
     * Data untuk panel "Hak Akses" di form. Katalog TANPA modul terkunci
     * (settings/user/trash tidak bisa di-override), default izin tiap role
     * (untuk JS pre-fill), dan override user yang sedang diedit.
     */
    private function permissionPanelData(?int $userId): array
    {
        $labels = permissionLabelMaps();

        $catalog = [];
        foreach (permissionActionCatalog() as $module => $actions) {
            if (!permissionIsLockedModule($module)) {
                $catalog[$module] = $actions;
            }
        }

        // Default izin per-role: [role_slug => [ "module.action" => bool ]].
        // super_admin diikutkan (semua true) supaya JS bisa menonaktifkan panel
        // kalau role user = Super Admin.
        $roleMatrix = [];
        $roleSlugs = array_merge([ROLE_SUPER_ADMIN], permissionEditableRoleSlugs());
        foreach ($roleSlugs as $slug) {
            foreach ($catalog as $module => $actions) {
                foreach ($actions as $action) {
                    $roleMatrix[$slug]["{$module}.{$action}"] = roleAllows($slug, $module, $action);
                }
            }
        }

        $roleSlugById = [];
        foreach ($this->roleModel->assignableList() as $r) {
            $roleSlugById[(int) $r['id']] = $r['role_slug'];
        }

        return [
            'permCatalog'    => $catalog,
            'permLabels'     => $labels,
            'permRoleMatrix' => $roleMatrix,
            'permRoleSlugById' => $roleSlugById,
            'permOverrides'  => $userId ? $this->userPermModel->mapForUser($userId) : [],
        ];
    }

    /**
     * Simpan override hak akses user dari POST form.
     *   customize_permissions kosong  -> hapus semua override (user murni ikut role).
     *   customize_permissions = 1     -> simpan SELISIH antara centang admin dan
     *                                    default role yang dipilih.
     * Modul terkunci & role Super Admin diabaikan (tidak perlu override).
     */
    private function savePermissionOverrides(int $userId, int $roleId): void
    {
        $roleSlug = $this->roleModel->slugById($roleId);

        if (!$roleSlug || $roleSlug === ROLE_SUPER_ADMIN || empty($_POST['customize_permissions'])) {
            $this->userPermModel->clearForUser($userId);
            return;
        }

        $posted = $_POST['uperm'] ?? [];
        $overrides = [];

        foreach (permissionActionCatalog() as $module => $actions) {
            if (permissionIsLockedModule($module)) {
                continue;
            }
            foreach ($actions as $action) {
                $key = "{$module}.{$action}";
                $effective = !empty($posted[$key]);
                $base = roleAllows($roleSlug, $module, $action);
                if ($effective !== $base) {
                    $overrides[$key] = $effective ? 'allow' : 'deny';
                }
            }
        }

        try {
            $this->userPermModel->replaceForUser($userId, $overrides, currentUserId());
        } catch (Throwable $e) {
            error_log('savePermissionOverrides gagal: ' . $e->getMessage());
        }
    }
}
