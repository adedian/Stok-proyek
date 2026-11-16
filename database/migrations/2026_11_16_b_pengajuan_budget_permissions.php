<?php
/**
 * Izin modul Pengajuan Budget (Permintaan Otorisasi) -- HANYA akun Purwati & Nissa.
 *
 * 1. role_permissions: seed modul 'pengajuan_budget' & 'pengajuan_budget_report' dari
 *    config/permissions.php. Di file itu semua aksi HANYA ROLE_SUPER_ADMIN, jadi semua
 *    role lain bernilai 0 (allowed=0) -- tidak ada role yang punya akses lewat role.
 * 2. user_permissions (izin PER AKUN): semua aksi kedua modul diberi 'allow' untuk
 *    akun 'purwati' dan 'nissa'. Akun lain (Vicky, Andy, Gusti, accounting, dst.) TIDAK
 *    dapat apa pun, sehingga menu tidak tampil dan URL langsung -> 403.
 *    Akun dicari lewat username; kalau tidak ada di database ini -> dilewati (diberi catatan).
 *
 * Idempotent. Super Admin bisa mengubah per akun lewat User Management > Hak Akses.
 * (Super Admin selalu lolos can() -- perilaku bawaan seluruh aplikasi.)
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$matrix = require ROOT_PATH . '/config/permissions.php';
$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];
$modules = ['pengajuan_budget', 'pengajuan_budget_report'];
$usernames = ['purwati', 'nissa'];

$roleIns = $pdo->prepare(
    "INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed)
     VALUES (:r, :m, :a, :v)"
);
$nRole = 0;
foreach ($modules as $module) {
    foreach ($matrix[$module] ?? [] as $action => $roles) {
        foreach ($editableRoles as $role) {
            $roleIns->execute(['r' => $role, 'm' => $module, 'a' => $action, 'v' => in_array($role, $roles, true) ? 1 : 0]);
            $nRole += $roleIns->rowCount();
        }
    }
}

$find = $pdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$hasUnique = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
      AND table_name = 'user_permissions' AND non_unique = 0 AND index_name <> 'PRIMARY' LIMIT 1"
)->fetchColumn();
$del = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid AND module = :m AND action = :a");
$up = $pdo->prepare(
    "INSERT INTO user_permissions (user_id, module, action, effect)
     VALUES (:uid, :m, :a, 'allow')
     ON DUPLICATE KEY UPDATE effect = 'allow'"
);

$nUser = 0;
foreach ($usernames as $username) {
    $find->execute(['u' => $username]);
    $uid = (int) $find->fetchColumn();
    if (!$uid) {
        echo "  (akun '{$username}' tidak ada di database ini -- izin Pengajuan Budget dilewati)\n";
        continue;
    }
    foreach ($modules as $module) {
        foreach (array_keys($matrix[$module] ?? []) as $action) {
            if (!$hasUnique) { // tabel tanpa unique key -> hindari baris ganda
                $del->execute(['uid' => $uid, 'm' => $module, 'a' => $action]);
            }
            $up->execute(['uid' => $uid, 'm' => $module, 'a' => $action]);
            $nUser++;
        }
    }
}

echo "Pengajuan Budget: {$nRole} baris role_permissions baru, {$nUser} izin per-akun (purwati/nissa) diberikan.\n";
