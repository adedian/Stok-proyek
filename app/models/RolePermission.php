<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * RolePermission
 * Matrix RBAC yang bisa diedit lewat UI (Pengaturan Sistem > Hak Akses).
 * Menggantikan config/permissions.php sebagai SUMBER RUNTIME untuk modul yang
 * tidak terkunci -- file tetap dipertahankan sebagai default/seed & fallback
 * (lihat permission_helper.php).
 *
 * Satu baris = izin satu (role_slug, module, action). allowed 1/0.
 * Modul 'settings', 'user', 'trash' TIDAK pernah disimpan di sini.
 */
class RolePermission extends Model
{
    protected string $table = 'role_permissions';

    /** Semua baris matrix (dipakai permission_helper untuk membangun cache). */
    public function allRows(): array
    {
        return $this->db->fetchAll("SELECT role_slug, module, action, allowed FROM role_permissions");
    }

    /**
     * Simpan ulang matrix untuk sekumpulan role sekaligus.
     * $grid: [role_slug => [ "module.action" => bool ]]
     * Hanya pasangan module/action yang ADA di $catalog yang ditulis, jadi
     * input POST yang aneh-aneh tidak bisa menyuntik modul baru.
     *
     * $catalog: [module => [action, ...]]  (dari permissionActionCatalog())
     */
    public function replaceForRoles(array $grid, array $catalog, ?int $updatedBy = null): void
    {
        $pdo = getPDO();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        try {
            $upsert = $pdo->prepare(
                "INSERT INTO role_permissions (role_slug, module, action, allowed, updated_by)
                 VALUES (:role_slug, :module, :action, :allowed, :updated_by)
                 ON DUPLICATE KEY UPDATE allowed = VALUES(allowed), updated_by = VALUES(updated_by)"
            );

            foreach ($grid as $roleSlug => $pairs) {
                foreach ($catalog as $module => $actions) {
                    foreach ($actions as $action) {
                        $key = "{$module}.{$action}";
                        $upsert->execute([
                            'role_slug'  => $roleSlug,
                            'module'     => $module,
                            'action'     => $action,
                            'allowed'    => !empty($pairs[$key]) ? 1 : 0,
                            'updated_by' => $updatedBy,
                        ]);
                    }
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
