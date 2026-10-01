<?php
/**
 * Seed role_permissions untuk modul baru 'request_budget'.
 * Pola identik 2026_09_24_b_seed_permissions_information.php -- baca ULANG
 * config/permissions.php lalu INSERT IGNORE (idempotent, tidak menimpa baris yang ada).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];

$matrix = require ROOT_PATH . '/config/permissions.php';
$module = 'request_budget';
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
        if ($stmt->rowCount() > 0) {
            $inserted++;
        }
    }
}

echo "Seed role_permissions (request_budget) selesai. Baris baru: {$inserted}\n";
