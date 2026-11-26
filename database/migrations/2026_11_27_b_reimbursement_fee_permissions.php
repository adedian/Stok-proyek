<?php
/**
 * Izin modul baru Reimbursement & Pengajuan Fee.
 *
 * 1. role_permissions: seed kedua modul dari config/permissions.php untuk role yang bisa diatur
 *    (purchase, accounting, pic_project, admin_project, project_manager) -- Reimbursement: view/create/edit/submit
 *    = 1 (semua akun), view_all/review/delete = 0; Pengajuan Fee: semuanya 0. INSERT IGNORE: baris lama tidak diubah.
 * 2. user_permissions (izin PER AKUN, hanya 'allow'; tidak ada izin akun/role lain yang diubah):
 *      Reimbursement : nissa & purwati -> view_all, review   (periksa SEMUA pengajuan; salah satu cukup)
 *      Pengajuan Fee : nissa   -> view, create, edit, submit (pembuat)
 *                      purwati -> view, check                (pemeriksa)
 *                      'approve' (Disetujui) TIDAK diberikan ke siapa pun selain Super Admin -- beri ke akun
 *                      penyetuju lewat User Management > Hak Akses.
 *    Akun yang tidak ada di database ini dilewati. Idempotent.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$matrix = require ROOT_PATH . '/config/permissions.php';
$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];
$modules = ['reimbursement', 'pengajuan_fee'];

$roleIns = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed) VALUES (:r, :m, :a, :v)");
$nRole = 0;
foreach ($modules as $module) {
    foreach ($matrix[$module] ?? [] as $action => $roles) {
        foreach ($editableRoles as $role) {
            $roleIns->execute(['r' => $role, 'm' => $module, 'a' => $action, 'v' => in_array($role, $roles, true) ? 1 : 0]);
            $nRole += $roleIns->rowCount();
        }
    }
}

$grants = [
    'nissa'   => ['reimbursement' => ['view_all', 'review'], 'pengajuan_fee' => ['view', 'create', 'edit', 'submit']],
    'purwati' => ['reimbursement' => ['view_all', 'review'], 'pengajuan_fee' => ['view', 'check']],
];

$find = $pdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$hasUnique = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
      AND table_name = 'user_permissions' AND non_unique = 0 AND index_name <> 'PRIMARY' LIMIT 1"
)->fetchColumn();
$del = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid AND module = :m AND action = :a");
$up = $pdo->prepare(
    "INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, :m, :a, 'allow')
     ON DUPLICATE KEY UPDATE effect = 'allow'"
);

$nUser = 0;
foreach ($grants as $username => $byModule) {
    $find->execute(['u' => $username]);
    $uid = (int) $find->fetchColumn();
    if (!$uid) {
        echo "  (akun '{$username}' tidak ada di database ini -- izin Reimbursement/Pengajuan Fee dilewati)\n";
        continue;
    }
    foreach ($byModule as $module => $actions) {
        foreach ($actions as $action) {
            if (!$hasUnique) {
                $del->execute(['uid' => $uid, 'm' => $module, 'a' => $action]);
            }
            $up->execute(['uid' => $uid, 'm' => $module, 'a' => $action]);
            $nUser++;
        }
    }
}
echo "Reimbursement & Pengajuan Fee: {$nRole} baris role_permissions baru, {$nUser} izin per-akun diberikan.\n";
