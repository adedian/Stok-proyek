<?php
/**
 * Izin modul Request PO (2026-10-06).
 *
 * 1. role_permissions: bangun ulang baris modul 'request_po' dari config/permissions.php
 *    -> HANYA role Purchase (view/create/edit/delete/submit). Role lain: tidak ada akses.
 * 2. user_permissions (izin PER AKUN, bukan per role -- Andy tetap Purchase):
 *      Andy : approve + reject  (Purchase lain TIDAK bisa approve)
 *    Akun dicari lewat username; kalau tidak ada di database ini -> dilewati (diberi catatan).
 *    Idempotent. Super Admin bisa mengubah per akun lewat User Management > Hak Akses.
 *
 * Super Admin selalu punya akses penuh (lewat can()), tidak perlu baris khusus.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$rpoPdo = getPDO();
$rpoModule = 'request_po';
$rpoMatrix = require ROOT_PATH . '/config/permissions.php';
$rpoRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];

$rpoPdo->prepare("DELETE FROM role_permissions WHERE module = :m")->execute(['m' => $rpoModule]);
$rpoIns = $rpoPdo->prepare("INSERT INTO role_permissions (role_slug, module, action, allowed) VALUES (:r, :m, :a, :v)");
$rpoN = 0;
foreach ($rpoMatrix[$rpoModule] ?? [] as $rpoAction => $rpoAllowed) {
    foreach ($rpoRoles as $rpoRole) {
        $rpoIns->execute(['r' => $rpoRole, 'm' => $rpoModule, 'a' => $rpoAction, 'v' => in_array($rpoRole, $rpoAllowed, true) ? 1 : 0]);
        $rpoN++;
    }
}

$rpoGrants = [
    'andy' => ['approve', 'reject'],
];
$rpoFind = $rpoPdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$rpoUp = $rpoPdo->prepare(
    "INSERT INTO user_permissions (user_id, module, action, effect)
     VALUES (:uid, :m, :a, 'allow')
     ON DUPLICATE KEY UPDATE effect = 'allow'"
);
$rpoHasUnique = (bool) $rpoPdo->query(
    "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
      AND table_name = 'user_permissions' AND non_unique = 0 AND index_name <> 'PRIMARY' LIMIT 1"
)->fetchColumn();
$rpoDel = $rpoPdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid AND module = :m AND action = :a");
$rpoG = 0;
foreach ($rpoGrants as $rpoUser => $rpoActions) {
    $rpoFind->execute(['u' => $rpoUser]);
    $rpoUid = (int) $rpoFind->fetchColumn();
    if (!$rpoUid) {
        echo "  (akun '{$rpoUser}' tidak ada di database ini -- izin approve dilewati)\n";
        continue;
    }
    foreach ($rpoActions as $rpoA) {
        if (!$rpoHasUnique) { // tabel tanpa unique key -> hindari baris ganda
            $rpoDel->execute(['uid' => $rpoUid, 'm' => $rpoModule, 'a' => $rpoA]);
        }
        $rpoUp->execute(['uid' => $rpoUid, 'm' => $rpoModule, 'a' => $rpoA]);
        $rpoG++;
    }
}
echo "Request PO: {$rpoN} baris role_permissions dibangun, {$rpoG} izin per-akun diberikan.\n";
