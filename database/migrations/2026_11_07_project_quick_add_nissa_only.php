<?php
/**
 * Kunci "Tambah Project Cepat" (project.quick_add) untuk semua akun kecuali
 * Super Admin dan Nissa (2026-11-03).
 *
 * - role_permissions: project.quick_add dimatikan untuk SEMUA role (Super Admin
 *   tetap boleh karena can() selalu meloloskan Super Admin).
 * - user_permissions: override 'allow' project.quick_add milik akun lain dihapus,
 *   supaya tidak ada akun yang lolos lewat izin per-user.
 * - Nissa diberi izin PER AKUN (effect 'allow'), bukan ke seluruh role Accounting.
 *   Akun dicari lewat username 'nissa'; dilewati kalau tidak ada di database ini.
 *
 * Idempotent: aman dijalankan ulang.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pqPdo = getPDO();

$pqRoles = $pqPdo->exec(
    "UPDATE role_permissions SET allowed = 0
      WHERE module = 'project' AND action = 'quick_add' AND allowed <> 0"
);

$pqFind = $pqPdo->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL");
$pqFind->execute(['u' => 'nissa']);
$pqNissa = (int) $pqFind->fetchColumn();

$pqDel = $pqPdo->prepare(
    "DELETE FROM user_permissions
      WHERE module = 'project' AND action = 'quick_add' AND effect = 'allow' AND user_id <> :uid"
);
$pqDel->execute(['uid' => $pqNissa]);
$pqRemoved = $pqDel->rowCount();

$pqGiven = 0;
if ($pqNissa) {
    $pqUpd = $pqPdo->prepare(
        "UPDATE user_permissions SET effect = 'allow'
          WHERE user_id = :uid AND module = 'project' AND action = 'quick_add'"
    );
    $pqUpd->execute(['uid' => $pqNissa]);
    $pqCheck = $pqPdo->prepare(
        "SELECT 1 FROM user_permissions WHERE user_id = :uid AND module = 'project' AND action = 'quick_add'"
    );
    $pqCheck->execute(['uid' => $pqNissa]);
    if (!$pqCheck->fetchColumn()) {
        $pqPdo->prepare(
            "INSERT INTO user_permissions (user_id, module, action, effect) VALUES (:uid, 'project', 'quick_add', 'allow')"
        )->execute(['uid' => $pqNissa]);
        $pqGiven++;
    }
} else {
    echo "  (akun 'nissa' tidak ada di database ini -- izin khusus dilewati)\n";
}

echo "Tambah Project Cepat: {$pqRoles} role dikunci, {$pqRemoved} override akun lain dihapus, {$pqGiven} izin Nissa baru.\n";
