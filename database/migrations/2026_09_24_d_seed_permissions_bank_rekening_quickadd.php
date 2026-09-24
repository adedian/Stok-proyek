<?php
/**
 * Seed role_permissions untuk action baru 'quick_add' pada modul 'master_bank'
 * dan 'master_rekening' (tombol "+" Tambah Cepat Bank/Rekening di form
 * Transaksi Bank).
 *
 * Pola identik 2026_09_24_b_seed_permissions_information.php -- baca ULANG
 * config/permissions.php lalu INSERT IGNORE ke role_permissions. Idempotent.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];
$targets = ['master_bank', 'master_rekening'];

$matrix = require ROOT_PATH . '/config/permissions.php';

$pdo = getPDO();
$stmt = $pdo->prepare(
    "INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed)
     VALUES (:role_slug, :module, :action, :allowed)"
);

$inserted = 0;
foreach ($targets as $module) {
    $allowedRoles = $matrix[$module]['quick_add'] ?? [];
    foreach ($editableRoles as $roleSlug) {
        $stmt->execute([
            'role_slug' => $roleSlug,
            'module'    => $module,
            'action'    => 'quick_add',
            'allowed'   => in_array($roleSlug, $allowedRoles, true) ? 1 : 0,
        ]);
        if ($stmt->rowCount() > 0) {
            $inserted++;
        }
    }
}

echo "Seed role_permissions (master_bank/master_rekening quick_add) selesai. Baris baru: {$inserted}\n";
