<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/Role.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

class UserController extends Controller
{
    private User $userModel;
    private Role $roleModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('user', 'view');

        $this->userModel   = new User();
        $this->roleModel   = new Role();
        $this->activityLog = new ActivityLog();
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

        $this->view('user/form', [
            'pageTitle' => 'Tambah User',
            'mode'      => 'create',
            'user'      => null,
            'roles'     => $this->roleModel->all('role_name ASC'),
        ]);
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

        $this->userModel->create([
            'role_id'    => $data['role_id'],
            'full_name'  => $data['full_name'],
            'username'   => $data['username'],
            'email'      => $data['email'],
            'password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            'status'     => 'active',
            'created_by' => currentUserId(),
        ]);

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

        $this->view('user/form', [
            'pageTitle' => 'Edit User',
            'mode'      => 'edit',
            'user'      => $user,
            'roles'     => $this->roleModel->all('role_name ASC'),
        ]);
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
}
