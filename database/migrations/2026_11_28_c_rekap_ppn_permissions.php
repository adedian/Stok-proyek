<?php
/**
 * Izin modul baru Rekap PPN (PPN Masukan + PPN Keluaran).
 *
 * role_permissions: seed 'rekap_ppn' dari config/permissions.php untuk role yang izinnya bisa diatur
 * (purchase, accounting, pic_project, admin_project, project_manager, direktur). Accounting: semua aksi = 1;
 * Direktur: hanya aksi baca (view, export) = 1; role lain = 0 (bisa dibuka lewat Pengaturan > Hak Akses).
 * INSERT IGNORE: baris yang sudah ada tidak diubah. Idempotent.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$matrix = require ROOT_PATH . '/config/permissions.php';
$roles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager', 'direktur'];
$read = ['view', 'view_all', 'view_all_purchase', 'view_balance', 'print', 'export', 'print_voucher', 'stock_price', 'stock_report'];

$permIns = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed) VALUES (:r, 'rekap_ppn', :a, :v)");
$n = 0;
foreach ($matrix['rekap_ppn'] ?? [] as $action => $allowedRoles) {
    foreach ($roles as $role) {
        $v = $role === 'direktur'
            ? (in_array($action, $read, true) ? 1 : 0)
            : (in_array($role, $allowedRoles, true) ? 1 : 0);
        $permIns->execute(['r' => $role, 'a' => $action, 'v' => $v]);
        $n += $permIns->rowCount();
    }
}
echo "Rekap PPN: {$n} baris role_permissions baru.\n";
