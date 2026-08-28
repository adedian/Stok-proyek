<?php
/**
 * ============================================================
 * REVISI 9 (2026-08-28) -- SEED / UPSERT AKUN ROLE BARU
 *
 * Membuat (atau menyesuaikan) 5 akun default untuk sistem role baru.
 * Password di-hash dengan password_hash() -- TIDAK PERNAH plain text.
 *
 *   purchase      / purchase123      -> Purchase
 *   accounting    / accounting123    -> Accounting
 *   picproject    / picproject123    -> PIC Project
 *   adminproject  / adminproject123  -> Admin Project
 *   pm            / pm123            -> Project Manager   (password di-RESET)
 *
 * Aturan:
 *   - Username sudah ada  -> update role_id + password + status active.
 *     (Akun 'pm' memang sudah ada sejak seeder lama; di sini passwordnya
 *      di-reset ke pm123 sesuai kebutuhan test Revisi 9.)
 *   - Username belum ada  -> insert baru (created_by = 1 / Super Admin).
 *   - Akun 'admin' (Super Admin) TIDAK disentuh.
 *   - TIDAK menghapus akun mana pun.
 *
 * Jalankan SETELAH 2026_08_28_revisi9_roles_cash_pic.sql (butuh baris
 * role baru sudah ada di tabel `roles`).
 *
 * PEMAKAIAN:
 *   php database/migrations/2026_08_28_revisi9_seed_users.php
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();

$seed = [
    ['username' => 'purchase',     'full_name' => 'Purchase',      'password' => 'purchase123',     'role_slug' => 'purchase'],
    ['username' => 'accounting',   'full_name' => 'Accounting',    'password' => 'accounting123',   'role_slug' => 'accounting'],
    ['username' => 'picproject',   'full_name' => 'PIC Project',   'password' => 'picproject123',   'role_slug' => 'pic_project'],
    ['username' => 'adminproject', 'full_name' => 'Admin Project', 'password' => 'adminproject123', 'role_slug' => 'admin_project'],
    ['username' => 'pm',           'full_name' => 'Project Manager','password' => 'pm123',           'role_slug' => 'project_manager'],
];

$roleId = static function (PDO $pdo, string $slug): ?int {
    $st = $pdo->prepare("SELECT id FROM roles WHERE role_slug = :s LIMIT 1");
    $st->execute(['s' => $slug]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int) $id;
};

$findUser = static function (PDO $pdo, string $username) {
    $st = $pdo->prepare("SELECT id, username, role_id FROM users WHERE username = :u LIMIT 1");
    $st->execute(['u' => $username]);
    return $st->fetch();
};

echo "=== REVISI 9 -- SEED AKUN ROLE BARU ===\n";

try {
    $pdo->beginTransaction();

    foreach ($seed as $s) {
        $rid = $roleId($pdo, $s['role_slug']);
        if ($rid === null) {
            throw new RuntimeException("Role '{$s['role_slug']}' belum ada -- jalankan dulu 2026_08_28_revisi9_roles_cash_pic.sql");
        }

        $hash = password_hash($s['password'], PASSWORD_DEFAULT);
        $existing = $findUser($pdo, $s['username']);

        if ($existing) {
            $upd = $pdo->prepare(
                "UPDATE users
                    SET role_id = :rid, password = :pw, status = 'active', updated_at = NOW()
                  WHERE id = :id"
            );
            $upd->execute(['rid' => $rid, 'pw' => $hash, 'id' => $existing['id']]);
            printf("  [update] %-13s -> role=%s, password di-reset\n", $s['username'], $s['role_slug']);
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO users (role_id, full_name, username, email, password, status, created_by)
                 VALUES (:rid, :fn, :un, :em, :pw, 'active', 1)"
            );
            $ins->execute([
                'rid' => $rid,
                'fn'  => $s['full_name'],
                'un'  => $s['username'],
                'em'  => $s['username'] . '@stokproyek.local',
                'pw'  => $hash,
            ]);
            printf("  [insert] %-13s -> role=%s\n", $s['username'], $s['role_slug']);
        }
    }

    // Contoh mapping PIC untuk akun 'purchase' (sesuai contoh di spesifikasi
    // Revisi 9: "Contoh PIC Purchase: Andi, Anggita"). Idempotent -- admin
    // bebas mengubah/menghapus lewat Master Data > PIC Mapping.
    $purchaseRow = $findUser($pdo, 'purchase');
    $purchaseId = $purchaseRow ? (int) $purchaseRow['id'] : null;
    if ($purchaseId) {
        $mapStmt = $pdo->prepare(
            "INSERT IGNORE INTO user_pic_assignments (user_id, pic_name, created_by) VALUES (:uid, :pn, 1)"
        );
        foreach (['Andi', 'Anggita'] as $pn) {
            $mapStmt->execute(['uid' => $purchaseId, 'pn' => $pn]);
        }
        echo "  [map]    purchase -> Andi, Anggita\n";
    }

    $pdo->commit();
    echo "\n>> Selesai. 5 akun siap dipakai.\n";

    echo "\n=== VERIFIKASI (username -> role_slug) ===\n";
    $rows = $pdo->query(
        "SELECT u.username, r.role_slug
           FROM users u JOIN roles r ON r.id = u.role_id
          WHERE u.deleted_at IS NULL
          ORDER BY u.id"
    )->fetchAll();
    foreach ($rows as $r) {
        printf("  %-14s %s\n", $r['username'], $r['role_slug']);
    }

    $stuck = $pdo->query(
        "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
          WHERE r.role_slug IN ('finance','gudang') AND u.deleted_at IS NULL"
    )->fetchColumn();
    echo "\n  User masih di role lama (finance/gudang): {$stuck}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "!! ERROR -- ROLLBACK. Tidak ada perubahan tersimpan.\n!! " . $e->getMessage() . "\n";
    exit(1);
}
