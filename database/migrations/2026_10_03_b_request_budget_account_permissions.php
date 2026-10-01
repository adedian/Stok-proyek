<?php
/**
 * Izin Request Budget setelah revisi 2026-10-03.
 *
 * 1. role_permissions: bangun ULANG baris modul 'request_budget' dari
 *    config/permissions.php -- Purchase / Accounting / Project Manager TIDAK lagi
 *    punya akses lewat role (akses khusus diberikan per akun di langkah 2).
 * 2. user_permissions (izin PER AKUN, bukan per role -- Vicky tetap PM, Andy tetap Purchase):
 *      Vicky : lihat, lihat semua, approve, tolak, cetak          (TIDAK forward)
 *      Andy  : lihat, buat, edit, hapus, submit, approve, tolak,
 *              TERUSKAN ke Purwati/Nissa, selesai, lihat semua, cetak
 *    Akun dicari lewat username; kalau tidak ada di database ini -> dilewati (diberi catatan).
 *    Idempotent. Super Admin bisa mengubah per akun lewat User Management > Hak Akses.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$rbModule = 'request_budget';
$rbMatrix = require ROOT_PATH . '/config/permissions.php';
$rbRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];

$pdo->prepare("DELETE FROM role_permissions WHERE module = :m")->execute(['m' => $rbModule]);
$rbIns = $pdo->prepare("INSERT INTO role_permissions (role_slug, module, action, allowed) VALUES (:r, :m, :a, :v)");
$rbN = 0;
foreach ($rbMatrix[$rbModule] ?? [] as $action => $roles) {
    foreach ($rbRoles as $role) {
        $rbIns->execute(['r' => $role, 'm' => $rbModule, 'a' => $action, 'v' => in_array($role, $roles, true) ? 1 : 0]);
        $rbN++;
    }
}

$rbGrants = [
    'vicky' => ['view', 'view_all', 'approve', 'reject', 'print'],
    'andy'  => ['view', 'create', 'edit', 'delete', 'submit', 'approve', 'reject', 'forward', 'complete', 'view_all', 'print'],
];
$rbFind = $pdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$rbUp = $pdo->prepare(
    "INSERT INTO user_permissions (user_id, module, action, effect)
     VALUES (:uid, :m, :a, 'allow')
     ON DUPLICATE KEY UPDATE effect = 'allow'"
);
$rbHasUnique = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
      AND table_name = 'user_permissions' AND non_unique = 0 AND index_name <> 'PRIMARY' LIMIT 1"
)->fetchColumn();
$rbDel = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid AND module = :m AND action = :a");
$rbG = 0;
foreach ($rbGrants as $username => $actions) {
    $rbFind->execute(['u' => $username]);
    $uid = (int) $rbFind->fetchColumn();
    if (!$uid) {
        echo "  (akun '{$username}' tidak ada di database ini -- izin khusus dilewati)\n";
        continue;
    }
    foreach ($actions as $a) {
        if (!$rbHasUnique) { // tabel tanpa unique key -> hindari baris ganda
            $rbDel->execute(['uid' => $uid, 'm' => $rbModule, 'a' => $a]);
        }
        $rbUp->execute(['uid' => $uid, 'm' => $rbModule, 'a' => $a]);
        $rbG++;
    }
}
echo "Request Budget: {$rbN} baris role_permissions dibangun ulang, {$rbG} izin per-akun diberikan.\n";
