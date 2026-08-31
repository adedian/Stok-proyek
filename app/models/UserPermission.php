<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * UserPermission
 * Override hak akses PER-USER di atas matrix role-nya (Pengaturan lewat panel
 * "Hak Akses" di form User Management). Hanya menyimpan SELISIH dari default
 * role: effect 'allow' memberi izin yang role-nya tidak punya, 'deny' mencabut
 * izin yang role-nya punya. User tanpa baris di sini = murni ikut role.
 *
 * Modul terkunci (settings, user, trash) tidak pernah di-override.
 */
class UserPermission extends Model
{
    protected string $table = 'user_permissions';

    /** [ "module.action" => 'allow'|'deny' ] untuk satu user. */
    public function mapForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT module, action, effect FROM user_permissions WHERE user_id = :uid",
            ['uid' => $userId]
        );
        $map = [];
        foreach ($rows as $r) {
            $map["{$r['module']}.{$r['action']}"] = $r['effect'];
        }
        return $map;
    }

    public function clearForUser(int $userId): void
    {
        $this->db->query("DELETE FROM user_permissions WHERE user_id = :uid", ['uid' => $userId]);
    }

    /**
     * Ganti total override milik user dengan $overrides.
     * $overrides: [ "module.action" => 'allow'|'deny' ] -- pasangan yang sudah
     * disaring controller (hanya yang beda dari default role & bukan modul
     * terkunci).
     */
    public function replaceForUser(int $userId, array $overrides, ?int $updatedBy = null): void
    {
        $pdo = getPDO();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid")
                ->execute(['uid' => $userId]);

            if ($overrides) {
                $ins = $pdo->prepare(
                    "INSERT INTO user_permissions (user_id, module, action, effect, updated_by)
                     VALUES (:uid, :module, :action, :effect, :updated_by)"
                );
                foreach ($overrides as $key => $effect) {
                    if ($effect !== 'allow' && $effect !== 'deny') {
                        continue;
                    }
                    [$module, $action] = array_pad(explode('.', $key, 2), 2, '');
                    if ($module === '' || $action === '') {
                        continue;
                    }
                    $ins->execute([
                        'uid'        => $userId,
                        'module'     => $module,
                        'action'     => $action,
                        'effect'     => $effect,
                        'updated_by' => $updatedBy,
                    ]);
                }
            }

            if ($ownTx) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
