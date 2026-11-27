<?php
/**
 * Akses Rekap PPN: HANYA Super Admin, Nissa, dan Purwati (izin per akun).
 *
 * 1. role_permissions: semua aksi 'rekap_ppn' untuk SEMUA role (termasuk Accounting & Direktur) di-set allowed=0
 *    (sebelumnya Accounting penuh & Direktur baca saja -- migrasi 2026_11_28_c). Aksi baru 'quick_add' ikut disemai = 0.
 * 2. user_permissions (allow, per akun): nissa & purwati -> view, create, edit, delete, export, quick_add.
 *    Akun yang tidak ada di database ini dilewati. Idempotent.
 * Catatan: variabel diberi awalan rp* karena berkas ini di-require di scope bin/migrate.php (jangan pakai $ins/$f/$ok/$path).
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$rpPdo = getPDO();
$rpMatrix = require ROOT_PATH . '/config/permissions.php';
$rpRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager', 'direktur'];
$rpActions = array_keys($rpMatrix['rekap_ppn'] ?? []);

$rpSeed = $rpPdo->prepare("INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed) VALUES (:r, 'rekap_ppn', :a, 0)");
$rpOff = $rpPdo->prepare("UPDATE role_permissions SET allowed = 0 WHERE module = 'rekap_ppn' AND role_slug = :r AND action = :a");
foreach ($rpActions as $rpAction) {
    foreach ($rpRoles as $rpRole) {
        $rpSeed->execute(['r' => $rpRole, 'a' => $rpAction]);
        $rpOff->execute(['r' => $rpRole, 'a' => $rpAction]);
    }
}

$rpGrants = ['view', 'create', 'edit', 'delete', 'export', 'quick_add'];
$rpFind = $rpPdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$rpHasUnique = (bool) $rpPdo->query(
    "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
      AND table_name = 'user_permissions' AND non_unique = 0 AND index_name <> 'PRIMARY' LIMIT 1"
)->fetchColumn();
$rpDel = $rpPdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid AND module = 'rekap_ppn' AND action = :a");
$rpUp = $rpPdo->prepare(
    "INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, 'rekap_ppn', :a, 'allow')
     ON DUPLICATE KEY UPDATE effect = 'allow'"
);
$rpCount = 0;
foreach (['nissa', 'purwati'] as $rpUser) {
    $rpFind->execute(['u' => $rpUser]);
    $rpUid = (int) $rpFind->fetchColumn();
    if (!$rpUid) {
        echo "  (akun '{$rpUser}' tidak ada di database ini -- dilewati)\n";
        continue;
    }
    foreach ($rpGrants as $rpAction) {
        if (!$rpHasUnique) {
            $rpDel->execute(['uid' => $rpUid, 'a' => $rpAction]);
        }
        $rpUp->execute(['uid' => $rpUid, 'a' => $rpAction]);
        $rpCount++;
    }
}
echo "Rekap PPN: semua role dikunci (0); {$rpCount} izin per-akun diberikan ke nissa & purwati.\n";
