<?php
/**
 * Seed role_permissions untuk modul baru revisi Kas/Bank: 'bank', 'master_bank',
 * 'master_rekening', dan action baru 'project.manage_access'.
 *
 * Pola identik 2026_09_01_seed_permissions.php -- baca ULANG config/permissions.php
 * (sudah berisi modul/action baru itu) lalu INSERT IGNORE ke role_permissions.
 * Idempotent & aman dijalankan berulang; baris yang sudah ada (module+action+role)
 * TIDAK ditimpa, jadi tidak mengganggu perubahan matrix yang mungkin sudah
 * di-edit admin lewat UI Hak Akses untuk modul-modul lama.
 *
 * Dijalankan otomatis oleh bin/migrate.php (self-bootstrap config sendiri, sama
 * seperti migrasi .php lain).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$LOCKED = ['settings', 'user', 'trash', 'period_lock'];
$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];

$matrix = require ROOT_PATH . '/config/permissions.php';

$pdo = getPDO();
$stmt = $pdo->prepare(
    "INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed)
     VALUES (:role_slug, :module, :action, :allowed)"
);

$inserted = 0;
foreach ($matrix as $module => $actions) {
    if (in_array($module, $LOCKED, true)) {
        continue;
    }
    foreach ($actions as $action => $allowedRoles) {
        foreach ($editableRoles as $roleSlug) {
            $stmt->execute([
                'role_slug' => $roleSlug,
                'module'    => $module,
                'action'    => $action,
                'allowed'   => in_array($roleSlug, $allowedRoles, true) ? 1 : 0,
            ]);
            if ($stmt->rowCount() > 0) {
                $inserted++;
            }
        }
    }
}

echo "Seed role_permissions (bank/master_bank/master_rekening/project.manage_access) selesai. Baris baru: {$inserted}\n";
