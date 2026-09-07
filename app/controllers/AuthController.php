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

        // Sisa waktu kunci login (untuk mem-freeze form + hitung mundur).
        // Sumber 1: percobaan barusan yang kena kunci (disimpan di sesi oleh
        // authenticate()). Sumber 2: cek ulang kunci per-IP tiap render (mis.
        // user refresh / datang langsung saat IP masih terkunci).
        $lockRemaining = 0;
        if (!empty($_SESSION['login_lock_until'])) {
            $lockRemaining = max(0, (int) $_SESSION['login_lock_until'] - time());
            if ($lockRemaining <= 0) {
                unset($_SESSION['login_lock_until']);
            }
        }
        $ipRemain = $this->activityLog->ipLockRemaining($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ipRemain > $lockRemaining) {
            $lockRemaining = $ipRemain;
        }

        $this->viewPlain('auth/login', [
            'expired'       => isset($_GET['expired']),
            'lockRemaining' => $lockRemaining,
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

        // --- Throttling brute-force per IP (jendela 15 menit, ambang 8 kegagalan) ---
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($this->activityLog->countRecentFailedLogins($ip, 15) >= 8) {
            $this->activityLog->log(null, 'auth', 'login_blocked', "IP {$ip} diblokir sementara: percobaan login berlebihan");
            $_SESSION['login_lock_until'] = time() + max(1, $this->activityLog->ipLockRemaining($ip));
            setFlash('error', 'Terlalu banyak percobaan login gagal. Silakan coba lagi dalam 15 menit.');
            $this->redirect('auth', 'login');
        }

        // --- Lockout per-AKUN (jendela 15 menit, ambang 5 kegagalan) ---
        // Pelengkap throttle per-IP: menahan brute-force satu akun dari banyak IP.
        // Trade-off: satu penyerang bisa "mengunci" akun orang lain sementara
        // (auto-lepas 15 menit) -- diterima untuk aplikasi internal; throttle
        // per-IP (8) sudah membatasi kecepatan penyerang mengumpulkan kegagalan.
        if ($this->activityLog->countRecentFailedLoginsByUser($username, 15) >= 5) {
            $this->activityLog->log(null, 'auth', 'login_blocked', "Akun '{$username}' dikunci sementara: percobaan login berlebihan");
            $_SESSION['login_lock_until'] = time() + max(1, $this->activityLog->userLockRemaining($username));
            setFlash('error', 'Akun ini dikunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.');
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
        unset($_SESSION['login_lock_until']);
        regenerateSession();

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['role_id']    = $user['role_id'];
        $_SESSION['role_name']  = $user['role_name'];
        $_SESSION['role_slug']  = $user['role_slug'];
        $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
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
