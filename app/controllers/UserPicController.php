<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/UserPicAssignment.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Master Data > PIC Mapping (Revisi 9).
 * Mengaitkan user (akun login) ke satu/lebih nama PIC. Dipakai CashController
 * untuk menentukan transaksi Kas mana yang boleh dilihat/diubah user tersebut.
 * Khusus Super Admin.
 */
class UserPicController extends Controller
{
    private UserPicAssignment $picModel;
    private User $userModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        // quickStore() dipanggil dari form Kas oleh role selain Super Admin --
        // permission-nya dicek terpisah di dalam method (lebih longgar dari
        // gate 'view' yang khusus Super Admin).
        if (($_GET['action'] ?? 'index') !== 'quickStore') {
            Middleware::requirePermission('user_pic', 'view');
        }

        $this->picModel    = new UserPicAssignment();
        $this->userModel   = new User();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $this->view('user_pic/list', [
            'pageTitle'    => 'PIC Mapping',
            'assignments'  => $this->picModel->listWithUserAndCredential(),
            'users'        => $this->userModel->activeList(),
        ]);
    }

    /**
     * Set / reset kredensial login Kas (username + password/PIN + status aktif)
     * untuk satu mapping PIC. KHUSUS Super Admin (gate user_pic.edit).
     * Password disimpan ter-hash; plaintext tidak pernah disimpan/ditampilkan.
     */
    public function setCredential()
    {
        Middleware::requirePermission('user_pic', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('user_pic', 'index');
        }
        verifyCsrf();

        $id       = (int) ($_POST['id'] ?? 0);
        $row      = $this->picModel->find($id);
        $username = trim($_POST['pic_username'] ?? '');
        $pass     = (string) ($_POST['kas_password'] ?? '');
        $passConf = (string) ($_POST['kas_password_confirm'] ?? '');
        $isActive = ($_POST['is_active'] ?? '1') === '1';

        $errors = [];
        if (!$row) {
            $errors[] = 'Mapping PIC tidak ditemukan.';
        }
        // Password wajib hanya kalau PIC ini belum punya password sama sekali.
        $needPass = !$row || empty($row['pic_password']) || $pass !== '';
        if ($needPass) {
            if (mb_strlen($pass) < 6) {
                $errors[] = 'Password Kas minimal 6 karakter.';
            } elseif ($pass !== $passConf) {
                $errors[] = 'Konfirmasi Password Kas tidak cocok.';
            }
        }
        if ($username !== '' && $this->picModel->picUsernameExists($username, $id)) {
            $errors[] = 'Username PIC sudah dipakai mapping lain.';
        }

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('user_pic', 'index');
        }

        $hash = ($pass !== '') ? password_hash($pass, PASSWORD_DEFAULT) : (string) $row['pic_password'];
        $this->picModel->setCredential($id, $username ?: null, $hash, $isActive);
        $this->activityLog->log(
            currentUserId(),
            'user_pic',
            'pic_credential_set',
            "Kredensial Kas PIC '{$row['pic_name']}' (user #{$row['user_id']}) di-set/reset; status=" . ($isActive ? 'aktif' : 'nonaktif')
        );
        setFlash('success', 'Kredensial Kas PIC diperbarui.');
        $this->redirect('user_pic', 'index');
    }

    public function store()
    {
        Middleware::requirePermission('user_pic', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('user_pic', 'index');
        }
        verifyCsrf();

        $userId  = (int) ($_POST['user_id'] ?? 0);
        $picName = trim($_POST['pic_name'] ?? '');

        $errors = [];
        if ($userId <= 0 || !$this->userModel->find($userId)) {
            $errors[] = 'User wajib dipilih.';
        }
        if ($picName === '') {
            $errors[] = 'Nama PIC wajib diisi.';
        } elseif ($userId > 0 && $this->picModel->exists($userId, $picName)) {
            $errors[] = 'Mapping user + PIC ini sudah ada.';
        }

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('user_pic', 'index');
        }

        $this->picModel->create([
            'user_id'    => $userId,
            'pic_name'   => $picName,
            'created_by' => currentUserId(),
        ]);
        $this->activityLog->log(currentUserId(), 'user_pic', 'create', "PIC '{$picName}' dikaitkan ke user #{$userId}");
        setFlash('success', 'Mapping PIC berhasil ditambahkan.');
        $this->redirect('user_pic', 'index');
    }

    /**
     * AJAX quick-add dari form Kas. Menambah 1 mapping user->PIC.
     * Super Admin boleh memilih user tujuan; role lain dipaksa ke akunnya
     * sendiri (tidak percaya user_id dari POST).
     */
    public function quickStore()
    {
        Middleware::requirePermission('user_pic', 'quick_add');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();

        $picName = trim($_POST['pic_name'] ?? '');
        $userId  = currentUserRole() === ROLE_SUPER_ADMIN
            ? (int) ($_POST['user_id'] ?? 0)
            : (int) currentUserId();

        $errors = [];
        if ($userId <= 0 || !$this->userModel->find($userId)) {
            $errors[] = 'User tidak valid.';
        }
        if ($picName === '') {
            $errors[] = 'Nama PIC wajib diisi.';
        } elseif ($userId > 0 && $this->picModel->exists($userId, $picName)) {
            $errors[] = 'Mapping user + PIC ini sudah ada.';
        }
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
        }

        $this->picModel->create([
            'user_id'    => $userId,
            'pic_name'   => $picName,
            'created_by' => currentUserId(),
        ]);
        $this->activityLog->log(currentUserId(), 'user_pic', 'quick_add', "PIC '{$picName}' dikaitkan ke user #{$userId} (cepat dari form Kas)");

        // value & label dropdown PIC di form Kas = nama PIC itu sendiri.
        $this->json(['id' => $picName, 'label' => $picName]);
    }

    public function delete()
    {
        Middleware::requirePermission('user_pic', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('user_pic', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->picModel->find($id);
        if ($row) {
            $this->picModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'user_pic', 'delete', "Mapping PIC '{$row['pic_name']}' (user #{$row['user_id']}) dihapus");
            setFlash('success', 'Mapping PIC dihapus.');
        } else {
            setFlash('error', 'Mapping tidak ditemukan.');
        }
        $this->redirect('user_pic', 'index');
    }
}
