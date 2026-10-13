<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Mapping User <-> Project (BARU, Revisi Kas/Bank). Dipakai gerbang Kas
 * "pilih Project lalu password akun sendiri" khusus role purchase /
 * pic_project / admin_project. Diatur Super Admin (project.manage_access).
 */
class ProjectUserAccess extends Model
{
    protected string $table = 'project_user_access';

    /** Project yang boleh dibuka user ini lewat gerbang Kas (aktif saja). */
    public function projectsForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.project_code, p.project_name
               FROM project_user_access pua
               JOIN projects p ON p.id = pua.project_id AND p.deleted_at IS NULL
              WHERE pua.user_id = :uid AND pua.is_active = 1
           ORDER BY p.project_name ASC",
            ['uid' => $userId]
        );
    }

    /** true kalau user ini punya akses (aktif) ke project tsb -- guard IDOR server-side. */
    public function userHasAccess(int $userId, int $projectId): bool
    {
        return (bool) $this->db->fetchOne(
            "SELECT id FROM project_user_access WHERE user_id = :uid AND project_id = :pid AND is_active = 1",
            ['uid' => $userId, 'pid' => $projectId]
        );
    }

    /** Semua baris akses (termasuk nonaktif) untuk 1 project -- dipakai layar Project > Akses. */
    public function rowsForProject(int $projectId): array
    {
        return $this->db->fetchAll(
            "SELECT pua.*, u.full_name, u.username, r.role_slug
               FROM project_user_access pua
               JOIN users u ON u.id = pua.user_id AND u.deleted_at IS NULL
               JOIN roles r ON r.id = u.role_id
              WHERE pua.project_id = :pid
           ORDER BY u.full_name ASC",
            ['pid' => $projectId]
        );
    }

    /** Daftar user (role purchase/pic_project/admin_project aktif) yang bisa di-assign ke project. */
    public function assignableUsers(): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.full_name, u.username, r.role_slug
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.deleted_at IS NULL AND u.status = 'active'
                AND r.role_slug IN ('purchase', 'pic_project', 'admin_project')
           ORDER BY u.full_name ASC"
        );
    }

    /** Set (replace) akses project ini ke persis daftar user_id yang diberikan. */
    public function syncForProject(int $projectId, array $userIds, int $actorId): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $this->db->query("DELETE FROM project_user_access WHERE project_id = :pid", ['pid' => $projectId]);
        foreach ($userIds as $uid) {
            if ($uid <= 0) {
                continue;
            }
            $this->db->insert('project_user_access', [
                'project_id' => $projectId,
                'user_id'    => $uid,
                'is_active'  => 1,
                'created_by' => $actorId,
            ]);
        }
    }
}
