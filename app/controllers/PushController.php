<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PushSubscription.php';

/**
 * PushController -- kelola langganan Web Push milik user yang SEDANG LOGIN
 * (per PERANGKAT, bukan per akun -- lihat public/assets/js/push.js).
 *
 * Sama seperti AccountController: identitas SELALU dari currentUserId()
 * (session), TIDAK PERNAH dari input, supaya tidak ada IDOR (user A tidak
 * bisa daftar/hapus langganan atas nama user B).
 */
class PushController extends Controller
{
    private PushSubscription $subModel;

    public function __construct()
    {
        Middleware::requirePermission('push', 'view');
        $this->subModel = new PushSubscription();
    }

    public function subscribe(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method tidak diizinkan.'], 405);
        }
        verifyCsrf();

        $endpoint = trim($_POST['endpoint'] ?? '');
        $p256dh   = trim($_POST['p256dh'] ?? '');
        $auth     = trim($_POST['auth'] ?? '');

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            $this->json(['error' => 'Data langganan tidak lengkap.'], 422);
        }

        $this->subModel->upsert(
            currentUserId(),
            $endpoint,
            $p256dh,
            $auth,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
        );

        $this->json(['ok' => true]);
    }

    public function unsubscribe(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method tidak diizinkan.'], 405);
        }
        verifyCsrf();

        $endpoint = trim($_POST['endpoint'] ?? '');
        if ($endpoint !== '') {
            $this->subModel->deleteByEndpoint(currentUserId(), $endpoint);
        }

        $this->json(['ok' => true]);
    }

    /** Kirim 1 notifikasi uji coba ke SEMUA perangkat user ini -- tombol "Kirim Uji Coba" di Pengaturan. */
    public function test(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method tidak diizinkan.'], 405);
        }
        verifyCsrf();

        if (!$this->subModel->hasAnyForUser(currentUserId())) {
            $this->json(['error' => 'Belum ada langganan push di perangkat ini. Klik "Aktifkan" dulu.'], 422);
        }

        try {
            sendPushToUser(
                currentUserId(),
                'Notifikasi Uji Coba',
                'Kalau ini muncul, notifikasi push di perangkat ini sudah aktif.',
                route('dashboard')
            );
        } catch (Throwable $e) {
            error_log('Push test gagal: ' . $e->getMessage());
        }

        $this->json(['ok' => true]);
    }
}
