<?php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Helper Push Notification (Web Push) -- notifikasi native browser/HP,
 * MUNCUL WALAU APLIKASI SEDANG TERTUTUP (Android penuh; iOS wajib
 * "Tambah ke Layar Utama" & iOS 16.4+, batasan platform bukan bug).
 *
 * Dikirim SINKRON (di request yang sama saat event terjadi, mis. simpan
 * PO baru). Volumenya kecil (puluhan user, bukan ribuan) jadi ini aman --
 * kalau nanti user makin banyak, pindahkan ke antrean/cron tanpa mengubah
 * pemanggil (signature fungsi ini sudah dirancang fire-and-forget).
 */

/**
 * Kirim satu notifikasi push ke SEMUA user yang lolos $moduleAction (can()
 * per-role, TANPA sesi -- lihat canForUser()) DAN toggle notify_* aktif.
 * $moduleAction null -> lewati cek can() (dipakai kalau penerima sudah
 * ditentukan lewat cara lain, mis. divisi Kas).
 *
 * @param int[] $userIds     kandidat penerima (SUDAH difilter di luar kalau perlu,
 *                            mis. kasValidatableDivisions())
 * @param string $moduleAction "modul.aksi" (mis. "purchase_order.view") atau null
 */
function sendPushToUsers(array $userIds, string $title, string $body, string $url, ?string $moduleAction = null): void
{
    if (empty($userIds) || !defined('VAPID_PRIVATE_KEY') || VAPID_PRIVATE_KEY === '') {
        return; // fitur push belum dikonfigurasi (VAPID kosong) -- diam saja, jangan ganggu alur utama
    }

    if ($moduleAction !== null) {
        [$module, $action] = explode('.', $moduleAction, 2);
        require_once ROOT_PATH . '/app/models/User.php';
        $allUsers = (new User())->activeListWithRole();
        $eligible = array_column(
            array_filter($allUsers, fn($u) => in_array((int) $u['id'], $userIds, true)
                && canForUser((int) $u['id'], $u['role_slug'], $module, $action)),
            'id'
        );
        $userIds = array_map('intval', $eligible);
    }

    if (empty($userIds)) {
        return;
    }

    require_once ROOT_PATH . '/app/models/PushSubscription.php';
    $subModel = new PushSubscription();
    $subs = $subModel->forUsers($userIds);
    if (empty($subs)) {
        return;
    }

    try {
        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => VAPID_SUBJECT,
                'publicKey'  => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('Push: gagal inisialisasi WebPush -- ' . $e->getMessage());
        return;
    }

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url,
    ], JSON_UNESCAPED_UNICODE);

    // Setiap langkah di bawah SENGAJA dibungkus try/catch sendiri-sendiri --
    // 1 langganan rusak/tidak valid (mis. key ter-korup di sisi browser) TIDAK
    // BOLEH menggagalkan pengiriman ke langganan lain, apalagi menggagalkan
    // alur utama (simpan PO/Kas/dst) yang memanggil fungsi ini.
    $subsByEndpoint = [];
    foreach ($subs as $s) {
        try {
            $subsByEndpoint[$s['endpoint']] = $s;
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $s['endpoint'],
                    'keys'     => ['p256dh' => $s['p256dh'], 'auth' => $s['auth']],
                ]),
                $payload
            );
        } catch (Throwable $e) {
            error_log("Push: gagal antre notifikasi utk subscription #{$s['id']} -- " . $e->getMessage());
        }
    }

    try {
        // flush() = generator: request dikirim PARALEL (bukan 1-per-1 nunggu balasan),
        // penting supaya kirim ke 20 orang tidak bikin request user yang sedang
        // menyimpan PO/Kas jadi lambat.
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                // Browser/OS sudah cabut langganan ini (uninstall, ganti HP, dll) --
                // hosting balas 404/410, bersihkan supaya tidak dicoba lagi selamanya.
                $endpoint = $report->getEndpoint();
                if (isset($subsByEndpoint[$endpoint])) {
                    $subModel->deleteById((int) $subsByEndpoint[$endpoint]['id']);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Push: gagal mengirim batch notifikasi -- ' . $e->getMessage());
    }
}

/** Kirim ke SATU user (semua perangkatnya). Shortcut sendPushToUsers([$userId], ...). */
function sendPushToUser(int $userId, string $title, string $body, string $url, ?string $moduleAction = null): void
{
    sendPushToUsers([$userId], $title, $body, $url, $moduleAction);
}

/**
 * Kirim ke semua user AKTIF yang lolos can($module,'view') -- dipakai event
 * yang penerimanya "siapa pun yang berhak lihat modul X" (PO, Selisih Barang,
 * dst). Untuk Kas pakai kasValidatableDivisions() dulu lalu sendPushToUsers().
 */
function sendPushToModuleViewers(string $module, string $title, string $body, string $url): void
{
    require_once ROOT_PATH . '/app/models/User.php';
    $allUsers = (new User())->activeListWithRole();
    $userIds = array_map('intval', array_column($allUsers, 'id'));
    sendPushToUsers($userIds, $title, $body, $url, "{$module}.view");
}

/**
 * Kirim ke user yang berwenang memvalidasi transaksi Kas di $division
 * (lihat kasCanValidateDivision() -- persis logika yang sama dipakai
 * DashboardStat::activeAlerts() untuk kartu "Validasi Kas").
 */
function sendPushToKasValidators(string $division, string $title, string $body, string $url): void
{
    require_once ROOT_PATH . '/app/models/User.php';
    require_once ROOT_PATH . '/app/helpers/kas_auth_helper.php';
    $allUsers = (new User())->activeListWithRole();
    $userIds = array_map('intval', array_column(
        array_filter($allUsers, fn($u) => kasCanValidateDivision($u['role_slug'], $division)),
        'id'
    ));
    sendPushToUsers($userIds, $title, $body, $url, 'cash_validation.view');
}
