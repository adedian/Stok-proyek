<?php
/**
 * Izin Request Budget (proses Purchase) + modul baru Laporan Request Budget.
 *
 * 1. role_permissions dibangun ULANG untuk modul 'request_budget' dan
 *    'request_budget_report' dari config/permissions.php.
 *    - Laporan Request Budget: hanya Accounting + Super Admin (lewat role).
 *    - Accounting TIDAK punya akses menu Request Budget; hanya laporannya.
 * 2. user_permissions (PER AKUN): Andy mendapat 'purchase_process' (tambah PO/Invoice/
 *    dokumen pendukung). Vicky TIDAK mendapat purchase_process/forward.
 *    Akun dicari lewat username, dilewati kalau tidak ada. Idempotent.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$rbpPdo = getPDO();
$rbpMatrix = require ROOT_PATH . '/config/permissions.php';
$rbpRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];

$rbpDelMod = $rbpPdo->prepare("DELETE FROM role_permissions WHERE module = :m");
$rbpInsRow = $rbpPdo->prepare("INSERT INTO role_permissions (role_slug, module, action, allowed) VALUES (:r, :m, :a, :v)");
$rbpCount = 0;
foreach (['request_budget', 'request_budget_report'] as $rbpModule) {
    $rbpDelMod->execute(['m' => $rbpModule]);
    foreach ($rbpMatrix[$rbpModule] ?? [] as $rbpAction => $rbpAllowed) {
        foreach ($rbpRoles as $rbpRole) {
            $rbpInsRow->execute(['r' => $rbpRole, 'm' => $rbpModule, 'a' => $rbpAction, 'v' => in_array($rbpRole, $rbpAllowed, true) ? 1 : 0]);
            $rbpCount++;
        }
    }
}

$rbpGrants = ['andy' => ['purchase_process']];
$rbpFind = $rbpPdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$rbpCheck = $rbpPdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = :uid AND module = 'request_budget' AND action = :a");
$rbpAdd = $rbpPdo->prepare("INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, 'request_budget', :a, 'allow')");
$rbpGiven = 0;
foreach ($rbpGrants as $rbpUser => $rbpActs) {
    $rbpFind->execute(['u' => $rbpUser]);
    $rbpUid = (int) $rbpFind->fetchColumn();
    if (!$rbpUid) {
        echo "  (akun '{$rbpUser}' tidak ada di database ini -- izin khusus dilewati)\n";
        continue;
    }
    foreach ($rbpActs as $rbpA) {
        $rbpCheck->execute(['uid' => $rbpUid, 'a' => $rbpA]);
        if (!$rbpCheck->fetchColumn()) {
            $rbpAdd->execute(['uid' => $rbpUid, 'a' => $rbpA]);
            $rbpGiven++;
        }
    }
}
echo "Request Budget: {$rbpCount} baris role_permissions dibangun ulang, {$rbpGiven} izin per-akun baru.\n";
