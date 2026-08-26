<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

class AuthController extends Controller
{
    private User $userModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        $this->userModel = new User();
        $this->activityLog = new ActivityLog();
    }

    /**
     * Tampilkan form login. Kalau sudah login, langsung ke dashboard.
     */
    public function login()
    {
        if (isLoggedIn()) {
            $this->redirect('dashboard', 'index');
        }

        $this->viewPlain('auth/login', [
            'expired' => isset($_GET['expired']),
        ]);
    }

    /**
     * Proses autentikasi. Dipanggil dari form login (method POST).
     */
    public function authenticate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('auth', 'login');
        }

        verifyCsrf();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            setFlash('error', 'Username dan password wajib diisi.');
            $this->redirect('auth', 'login');
        }

        $user = $this->userModel->findByUsername($username);

        // Pesan error digeneralisir (tidak bocorkan "username salah" vs "password salah")
        // untuk mencegah user enumeration
        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password'])) {
            $this->activityLog->log(null, 'auth', 'login_failed', "Percobaan login gagal: {$username}");
            setFlash('error', 'Username atau password salah.');
            $this->redirect('auth', 'login');
        }

        // Sukses login -> regenerate session ID (cegah session fixation)
        regenerateSession();

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['role_id']    = $user['role_id'];
        $_SESSION['role_name']  = $user['role_name'];
        $_SESSION['role_slug']  = $user['role_slug'];
        $_SESSION['last_activity'] = time();

        $this->userModel->updateLastLogin($user['id']);
        $this->activityLog->log($user['id'], 'auth', 'login', 'User login berhasil');

        $this->redirect('dashboard', 'index');
    }

    public function logout()
    {
        if (isLoggedIn()) {
            $this->activityLog->log(currentUserId(), 'auth', 'logout', 'User logout');
        }

        $_SESSION = [];
        session_unset();
        session_destroy();

        // Mulai session baru hanya untuk menampung flash message di halaman login
        session_start();
        setFlash('success', 'Anda berhasil logout.');

        $this->redirect('auth', 'login');
    }
}
