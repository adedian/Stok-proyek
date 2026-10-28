<?php
/**
 * Izin "Terima Barang dari Semua PO" (goods_receipt.receive_any) untuk akun Nissa (2026-10-28).
 *
 * Penerimaan dari PO dibatasi ke Penerima Barang yang ditunjuk di PO (role Purchase/Project),
 * sedangkan Nissa (Accounting) tidak termasuk kandidat penerima. Izin baru 'receive_any'
 * default HANYA Super Admin (config/permissions.php); Nissa diberi PER AKUN lewat
 * user_permissions -- mekanisme override yang sudah ada, bukan ke seluruh role Accounting.
 * Akun dicari lewat username 'nissa'; dilewati kalau tidak ada di database ini. Kode aplikasi
 * TIDAK meng-hardcode nama, hanya membaca can().
 *
 * Idempotent: aman dijalankan ulang; tidak menimpa hasil edit admin di UI Hak Akses
 * (kalau sudah ada baris override apa pun untuk izin ini, dibiarkan).
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$raPdo = getPDO();
$raFind  = $raPdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$raCheck = $raPdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = :uid AND module = 'goods_receipt' AND action = 'receive_any'");
$raAdd   = $raPdo->prepare("INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, 'goods_receipt', 'receive_any', 'allow')");

$raGiven = 0;
$raFind->execute(['u' => 'nissa']);
$raUid = (int) $raFind->fetchColumn();
if ($raUid) {
    $raCheck->execute(['uid' => $raUid]);
    if (!$raCheck->fetchColumn()) {
        $raAdd->execute(['uid' => $raUid]);
        $raGiven++;
    }
} else {
    echo "  (akun 'nissa' tidak ada di database ini -- izin khusus dilewati)\n";
}
echo "Terima Barang dari Semua PO: {$raGiven} izin per-akun baru.\n";
