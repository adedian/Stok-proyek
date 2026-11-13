<?php
/**
 * Seed role_permissions untuk modul baru 'shipping_label' (Label Pengiriman).
 *
 * Pola identik 2026_11_01_b_seed_permissions_sales_order.php -- baca ULANG
 * config/permissions.php lalu INSERT IGNORE ke role_permissions. Idempotent;
 * baris yang sudah ada TIDAK ditimpa.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];

$matrix = require ROOT_PATH . '/config/permissions.php';
$module = 'shipping_label';
$actions = $matrix[$module] ?? [];

$pdo = getPDO();
$stmt = $pdo->prepare(
    "INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed)
     VALUES (:role_slug, :module, :action, :allowed)"
);

$inserted = 0;
foreach ($actions as $action => $allowedRoles) {
    foreach ($editableRoles as $roleSlug) {
        $stmt->execute([
            'role_slug' => $roleSlug,
            'module'    => $module,
            'action'    => $action,
            'allowed'   => in_array($roleSlug, $allowedRoles, true) ? 1 : 0,
        ]);
        $inserted += $stmt->rowCount();
    }
}

echo "Seed role_permissions (shipping_label) selesai. Baris baru: {$inserted}\n";
