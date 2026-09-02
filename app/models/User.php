<?php
require_once ROOT_PATH . '/core/Model.php';

class User extends Model
{
    protected string $table = 'users';
    protected bool $softDelete = true;

    /**
     * Cari user aktif berdasarkan username, sertakan nama & slug role (JOIN)
     */
    public function findByUsername(string $username)
    {
        $sql = "SELECT u.*, r.role_name, r.role_slug
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE u.username = :username
                  AND u.deleted_at IS NULL
                LIMIT 1";
        return $this->db->fetchOne($sql, ['username' => $username]);
    }

    public function updateLastLogin(int $userId): void
    {
        $this->db->update(
            'users',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $userId]
        );
    }

    /**
     * Daftar user aktif -- dipakai untuk dropdown "Penerima Barang" dsb.
     */
    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT id, full_name, username FROM users WHERE deleted_at IS NULL AND status = 'active' ORDER BY full_name ASC"
        );
    }

    /**
     * Semua user (aktif & nonaktif, exclude soft-deleted) + nama role -- untuk User Management.
     */
    public function listWithRole(): array
    {
        // Urut per-role (untuk pengelompokan di User Management), lalu nama.
        return $this->db->fetchAll(
            "SELECT u.*, r.role_name, r.role_slug
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.deleted_at IS NULL
             ORDER BY
                CASE r.role_slug
                    WHEN 'super_admin'     THEN 1
                    WHEN 'project_manager' THEN 2
                    WHEN 'accounting'      THEN 3
                    WHEN 'purchase'        THEN 4
                    WHEN 'pic_project'     THEN 5
                    WHEN 'admin_project'   THEN 6
                    ELSE 99
                END,
                r.role_name ASC,
                u.full_name ASC"
        );
    }

    public function findWithRole(int $id)
    {
        return $this->db->fetchOne(
            "SELECT u.*, r.role_name, r.role_slug
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND u.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM users WHERE username = :username AND deleted_at IS NULL";
        $params = ['username' => $username];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM users WHERE email = :email AND deleted_at IS NULL";
        $params = ['email' => $email];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
    }

    /**
     * Flip status active/inactive. User TIDAK PERNAH dihapus (banyak tabel
     * lain FK ke created_by) -- nonaktifkan adalah satu-satunya cara "hapus".
     */
    public function toggleStatus(int $id): string
    {
        $user = $this->find($id);
        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $this->updateById($id, ['status' => $newStatus]);
        return $newStatus;
    }

    /**
     * Dipakai untuk mencegah Super Admin aktif terakhir dinonaktifkan.
     */
    public function countActiveByRoleSlug(string $roleSlug): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.role_slug = :slug AND u.status = 'active' AND u.deleted_at IS NULL",
            ['slug' => $roleSlug]
        );
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Jumlah akun (aktif MAUPUN nonaktif, belum dihapus) untuk sebuah role --
     * dipakai mencegah Super Admin TERAKHIR dihapus permanen.
     */
    public function countByRoleSlug(string $roleSlug): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.role_slug = :slug AND u.deleted_at IS NULL",
            ['slug' => $roleSlug]
        );
        return (int) ($result['total'] ?? 0);
    }
}
