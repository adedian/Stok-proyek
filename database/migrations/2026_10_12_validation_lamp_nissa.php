<?php
/**
 * Validasi Penerimaan Barang MANDIRI khusus barang Lampu untuk akun Nissa (2026-10-06).
 *
 * Izin baru 'validation.validate_lamp' (default HANYA Super Admin di config/permissions.php)
 * diberikan PER AKUN lewat user_permissions -- mekanisme override yang sudah ada, bukan
 * sistem izin baru, dan BUKAN ke seluruh role Accounting. Akun dicari lewat username
 * 'nissa' (sama seperti migrasi izin per-akun sebelumnya); dilewati kalau tidak ada di
 * database ini. Kode aplikasi TIDAK meng-hardcode nama: GoodsReceiptItem::canSelfValidateLamp()
 * hanya membaca can('validation', 'validate_lamp').
 *
 * Syarat lain (Jenis Stok Lampu, penerimaan milik user itu sendiri, belum divalidasi) dicek
 * di backend -- lihat ValidationController::validateItem().
 *
 * Idempotent: aman dijalankan ulang; tidak menimpa hasil edit admin di UI Hak Akses.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$vlPdo = getPDO();
$vlFind  = $vlPdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$vlCheck = $vlPdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = :uid AND module = 'validation' AND action = 'validate_lamp'");
$vlAdd   = $vlPdo->prepare("INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, 'validation', 'validate_lamp', 'allow')");

$vlGiven = 0;
$vlFind->execute(['u' => 'nissa']);
$vlUid = (int) $vlFind->fetchColumn();
if ($vlUid) {
    $vlCheck->execute(['uid' => $vlUid]);
    if (!$vlCheck->fetchColumn()) {
        $vlAdd->execute(['uid' => $vlUid]);
        $vlGiven++;
    }
} else {
    echo "  (akun 'nissa' tidak ada di database ini -- izin khusus dilewati)\n";
}
echo "Validasi mandiri Lampu: {$vlGiven} izin per-akun baru.\n";
