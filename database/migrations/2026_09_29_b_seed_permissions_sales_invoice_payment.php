<?php
/**
 * Seed role_permissions untuk modul baru 'sales_invoice_payment' (Pembayaran
 * Invoice, Revisi 10 Fase 5).
 *
 * Pola identik 2026_09_24_b_seed_permissions_information.php -- baca ULANG
 * config/permissions.php (sudah berisi modul 'sales_invoice_payment') lalu
 * INSERT IGNORE ke role_permissions. Idempotent & aman dijalankan berulang;
 * baris yang sudah ada TIDAK ditimpa.
 *
 * Dijalankan otomatis oleh bin/migrate.php (self-bootstrap config sendiri).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];

$matrix = require ROOT_PATH . '/config/permissions.php';
$module = 'sales_invoice_payment';
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

echo "Seed role_permissions (sales_invoice_payment) selesai. Baris baru: {$inserted}\n";
