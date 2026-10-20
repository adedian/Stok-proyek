<?php
/**
 * Izin "Edit Surat Jalan" (delivery_note.edit) untuk akun Nissa (2026-10-20).
 *
 * Izin baru 'delivery_note.edit' default HANYA Super Admin (config/permissions.php).
 * Nissa diberi PER AKUN lewat user_permissions -- mekanisme override yang sudah ada, bukan
 * ke seluruh role Accounting. Akun dicari lewat username 'nissa'; dilewati kalau tidak ada
 * di database ini. Kode aplikasi TIDAK meng-hardcode nama, hanya membaca can().
 *
 * Idempotent: aman dijalankan ulang; tidak menimpa hasil edit admin di UI Hak Akses
 * (kalau sudah ada baris override apa pun untuk izin ini, dibiarkan).
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$dnPdo = getPDO();
$dnFind  = $dnPdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$dnCheck = $dnPdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = :uid AND module = 'delivery_note' AND action = 'edit'");
$dnAdd   = $dnPdo->prepare("INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, 'delivery_note', 'edit', 'allow')");

$dnGiven = 0;
$dnFind->execute(['u' => 'nissa']);
$dnUid = (int) $dnFind->fetchColumn();
if ($dnUid) {
    $dnCheck->execute(['uid' => $dnUid]);
    if (!$dnCheck->fetchColumn()) {
        $dnAdd->execute(['uid' => $dnUid]);
        $dnGiven++;
    }
} else {
    echo "  (akun 'nissa' tidak ada di database ini -- izin khusus dilewati)\n";
}
echo "Edit Surat Jalan: {$dnGiven} izin per-akun baru.\n";
