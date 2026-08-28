<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * AccountController
 * Pengaturan akun milik user yang SEDANG LOGIN saja -- bukan pengaturan sistem
 * global (itu domain SettingsController, khusus Super Admin) dan bukan
 * manajemen user lain (itu domain UserController, khusus Super Admin).
 *
 * Prinsip keamanan utama (cegah IDOR): SEMUA method di sini mengambil
 * identitas dari currentUserId() (session), TIDAK PERNAH dari $_GET/$_POST['id'].
 * Jadi tidak ada cara bagi user untuk mengubah akun user lain lewat parameter URL.
 */
class AccountController extends Controller
{
    private User $userModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        // Semua role yang sudah login boleh akses akun miliknya sendiri --
        // aturan role tetap dipusatkan di config/permissions.php seperti modul lain.
        Middleware::requirePermission('account', 'view');

        $this->userModel   = new User();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $user = $this->userModel->findWithRole(currentUserId());

        if (!$user) {
            // Tidak seharusnya terjadi (session valid tapi user hilang) -- jaga-jaga saja
            setFlash('error', 'Akun tidak ditemukan.');
            $this->redirect('dashboard', 'index');
        }

        $this->view('account/index', [
            'pageTitle' => 'Pengaturan Akun',
            'user'      => $user,
        ]);
    }

    /**
     * Update profil: nama, email, telepon, foto. Role & username TIDAK bisa
     * diubah lewat sini (role dikunci server-side, tidak dibaca dari POST sama sekali).
     */
    public function updateProfile()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('account', 'index');
        }
        verifyCsrf();

        $userId = currentUserId();

        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $errors = [];
        if ($fullName === '') {
            $errors[] = 'Nama lengkap wajib diisi.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email tidak valid.';
        } elseif ($this->userModel->emailExists($email, $userId)) {
            $errors[] = 'Email sudah dipakai akun lain.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{8,20}$/', $phone)) {
            $errors[] = 'Format nomor telepon tidak valid.';
        }

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('account', 'index');
        }

        try {
            $photoPath = handleFileUpload('profile_photo', 'profile_photos', ['jpg', 'jpeg', 'png', 'webp'], 2);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('account', 'index');
        }

        $updateData = [
            'full_name' => $fullName,
            'email'     => $email,
            'phone'     => $phone !== '' ? $phone : null,
        ];
        if ($photoPath !== null) {
            $updateData['profile_photo'] = $photoPath;
        }

        $this->userModel->updateById($userId, $updateData);

        // Sinkronkan session supaya nama baru langsung tampil di topbar tanpa logout
        $_SESSION['full_name'] = $fullName;

        $this->activityLog->log($userId, 'account', 'update_profile', 'Profil akun diperbarui');

        setFlash('success', 'Profil berhasil diperbarui.');
        $this->redirect('account', 'index');
    }

    /**
     * Ganti password. Password lama wajib diverifikasi lewat password_verify(),
     * password baru tidak boleh sama dengan yang lama, disimpan via password_hash().
     */
    public function changePassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('account', 'index');
        }
        verifyCsrf();

        $userId = currentUserId();
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $user = $this->userModel->find($userId);
        if (!$user) {
            setFlash('error', 'Akun tidak ditemukan.');
            $this->redirect('account', 'index');
        }

        $errors = [];
        if (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Password saat ini salah.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Konfirmasi password baru tidak cocok.';
        }
        if (empty($errors) && password_verify($newPassword, $user['password'])) {
            $errors[] = 'Password baru tidak boleh sama dengan password lama.';
        }

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('account', 'index');
        }

        $this->userModel->updateById($userId, [
            'password'            => password_hash($newPassword, PASSWORD_BCRYPT),
            'password_changed_at' => date('Y-m-d H:i:s'),
        ]);

        // Rotasi session ID setelah ganti password (mitigasi session fixation/hijacking) --
        // tidak memaksa logout penuh karena sistem ini tidak melacak sesi multi-device.
        regenerateSession();

        $this->activityLog->log($userId, 'account', 'change_password', 'Password akun diganti');

        setFlash('success', 'Password berhasil diganti.');
        $this->redirect('account', 'index');
    }
}
