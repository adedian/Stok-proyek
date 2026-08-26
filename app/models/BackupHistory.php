<?php
require_once ROOT_PATH . '/core/Model.php';

class BackupHistory extends Model
{
    protected string $table = 'backup_history';

    public function recent(int $limit = 20): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT bh.*, u.full_name
             FROM backup_history bh
             LEFT JOIN users u ON u.id = bh.created_by
             ORDER BY bh.created_at DESC
             LIMIT {$limit}"
        );
    }
}
