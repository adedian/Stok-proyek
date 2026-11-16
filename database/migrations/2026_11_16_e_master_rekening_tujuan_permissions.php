<?php
/**
 * Izin Master Rekening Tujuan -- sama dengan Pengajuan Budget: HANYA akun Purwati & Nissa
 * (izin per akun) + Super Admin. Hapus (delete) tetap khusus Super Admin.
 * Idempotent. Pola identik 2026_11_16_b_pengajuan_budget_permissions.php.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$matrix = require ROOT_PATH . '/config/permissions.php';
$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];
$module = 'master_rekening_tujuan';
$usernames = ['purwati', 'nissa'];
$perAccountActions = ['view', 'create', 'edit', 'quick_add']; // delete tetap Super Admin saja

$roleIns = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed) VALUES (:r, :m, :a, :v)");
$nRole = 0;
foreach ($matrix[$module] ?? [] as $action => $roles) {
    foreach ($editableRoles as $role) {
        $roleIns->execute(['r' => $role, 'm' => $module, 'a' => $action, 'v' => in_array($role, $roles, true) ? 1 : 0]);
        $nRole += $roleIns->rowCount();
    }
}

$find = $pdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$hasUnique = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
      AND table_name = 'user_permissions' AND non_unique = 0 AND index_name <> 'PRIMARY' LIMIT 1"
)->fetchColumn();
$del = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid AND module = :m AND action = :a");
$up = $pdo->prepare("INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, :m, :a, 'allow') ON DUPLICATE KEY UPDATE effect = 'allow'");

$nUser = 0;
foreach ($usernames as $username) {
    $find->execute(['u' => $username]);
    $uid = (int) $find->fetchColumn();
    if (!$uid) {
        echo "  (akun '{$username}' tidak ada di database ini -- dilewati)\n";
        continue;
    }
    foreach ($perAccountActions as $action) {
        if (!$hasUnique) {
            $del->execute(['uid' => $uid, 'm' => $module, 'a' => $action]);
        }
        $up->execute(['uid' => $uid, 'm' => $module, 'a' => $action]);
        $nUser++;
    }
}
echo "Master Rekening Tujuan: {$nRole} baris role_permissions baru, {$nUser} izin per-akun diberikan.\n";
