<?php
/**
 * Izin baru 'pengajuan_budget_report'.'edit_saldo' (edit saldo bank manual di Laporan Pengajuan Budget).
 * Diberikan HANYA ke akun purwati & nissa (izin per akun) -- permission akun/role lain TIDAK diubah.
 * Role lain hanya mendapat baris allowed=0 di role_permissions (supaya tampil di Hak Akses). Idempotent.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$module = 'pengajuan_budget_report';
$action = 'edit_saldo';
$roles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];

$esRoleIns = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed) VALUES (:r, :m, :a, 0)");
$nRole = 0;
foreach ($roles as $r) {
    $esRoleIns->execute(['r' => $r, 'm' => $module, 'a' => $action]);
    $nRole += $esRoleIns->rowCount();
}

$find = $pdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$hasUnique = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
      AND table_name = 'user_permissions' AND non_unique = 0 AND index_name <> 'PRIMARY' LIMIT 1"
)->fetchColumn();
$del = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid AND module = :m AND action = :a");
$up = $pdo->prepare("INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, :m, :a, 'allow') ON DUPLICATE KEY UPDATE effect = 'allow'");

$nUser = 0;
foreach (['purwati', 'nissa'] as $username) {
    $find->execute(['u' => $username]);
    $uid = (int) $find->fetchColumn();
    if (!$uid) {
        echo "  (akun '{$username}' tidak ada di database ini -- dilewati)\n";
        continue;
    }
    if (!$hasUnique) {
        $del->execute(['uid' => $uid, 'm' => $module, 'a' => $action]);
    }
    $up->execute(['uid' => $uid, 'm' => $module, 'a' => $action]);
    $nUser++;
}
echo "edit_saldo: {$nRole} baris role_permissions baru, {$nUser} izin per-akun diberikan.\n";
