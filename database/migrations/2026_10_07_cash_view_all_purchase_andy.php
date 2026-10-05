<?php
/**
 * Revisi akses Kas berbasis role & scope user (2026-10-05).
 *
 * Izin KHUSUS 'cash.view_all_purchase' (scope ALL PURCHASE, bukan ALL KAS) untuk
 * Kepala Purchase, lewat user_permissions (mekanisme override per-akun yang sudah
 * ada -- bukan sistem izin baru). Akun dicari lewat username 'andy' (sama seperti
 * migrasi izin per-akun sebelumnya); dilewati kalau tidak ada di database ini.
 * Kode aplikasi TIDAK meng-hardcode nama -- CashController::scopePics() hanya
 * membaca can('cash', 'view_all_purchase').
 *
 * Finance TIDAK butuh baris apa pun: role Finance di-alias persis ke Accounting
 * oleh roleAlias() (auth_helper.php), jadi otomatis ikut aturan Accounting.
 *
 * Idempotent: aman dijalankan ulang; tidak menimpa hasil edit admin di UI Hak Akses.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$vpPdo = getPDO();
$vpFind  = $vpPdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$vpCheck = $vpPdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = :uid AND module = 'cash' AND action = 'view_all_purchase'");
$vpAdd   = $vpPdo->prepare("INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, 'cash', 'view_all_purchase', 'allow')");

$vpGiven = 0;
$vpFind->execute(['u' => 'andy']);
$vpUid = (int) $vpFind->fetchColumn();
if ($vpUid) {
    $vpCheck->execute(['uid' => $vpUid]);
    if (!$vpCheck->fetchColumn()) {
        $vpAdd->execute(['uid' => $vpUid]);
        $vpGiven++;
    }
} else {
    echo "  (akun 'andy' tidak ada di database ini -- izin khusus dilewati)\n";
}
echo "Kas view_all_purchase: {$vpGiven} izin per-akun baru.\n";
