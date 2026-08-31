<?php
/**
 * Seed tabel role_permissions dari config/permissions.php.
 *
 * Menyalin matrix statis yang selama ini dipakai ke DB supaya bisa diedit lewat
 * UI (Pengaturan Sistem > Hak Akses) TANPA mengubah perilaku hari pertama --
 * setiap (module, action, role) yang tadinya diizinkan di file akan allowed=1,
 * sisanya allowed=0.
 *
 * Idempotent: pakai INSERT IGNORE, jadi aman dijalankan berulang. Baris yang
 * sudah ada (mis. hasil edit admin) TIDAK ditimpa; run ulang hanya mengisi
 * pasangan module/action/role baru yang belum ada.
 *
 * Modul terkunci (settings, user, trash) sengaja TIDAK di-seed -- selamanya
 * dibaca dari config/permissions.php.
 *
 * Jalankan: php database/migrations/2026_09_01_seed_permissions.php
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$LOCKED = ['settings', 'user', 'trash'];

// Role yang izinnya bisa diedit (semua role aktif kecuali super_admin yang
// selalu full-access lewat kode). Slug lama finance/gudang tidak diikutkan.
$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];

$matrix = require ROOT_PATH . '/config/permissions.php';

$pdo = getPDO();
$stmt = $pdo->prepare(
    "INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed)
     VALUES (:role_slug, :module, :action, :allowed)"
);

$inserted = 0;
$skipped = 0;

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
            } else {
                $skipped++;
            }
        }
    }
}

echo "Seed role_permissions selesai.\n";
echo "  Baris baru dimasukkan : {$inserted}\n";
echo "  Sudah ada (dilewati)  : {$skipped}\n";
echo "\nModul terkunci (tidak di-seed, tetap dari config/permissions.php): "
    . implode(', ', $LOCKED) . "\n";
