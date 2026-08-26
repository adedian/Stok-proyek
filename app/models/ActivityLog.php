<?php
require_once ROOT_PATH . '/core/Model.php';

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    /**
     * Pencatatan audit trail TIDAK BOLEH pernah menggagalkan aksi utama user
     * (login/logout/CRUD, dst). Kalau insert-nya gagal -- misalnya session
     * lama masih menunjuk ke user_id yang sudah tidak ada di tabel users --
     * cukup dicatat ke error_log(), jangan sampai melempar exception ke atas.
     */
    public function log(?int $userId, string $module, string $action, string $description = ''): void
    {
        try {
            $this->create([
                'user_id'     => $userId,
                'module'      => $module,
                'action'      => $action,
                'description' => $description,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_by'  => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('ActivityLog::log gagal: ' . $e->getMessage());
        }
    }

    /**
     * Daftar aktivitas + nama user, untuk halaman audit trail di modul Laporan.
     */
    public function listWithFilters(array $filters = []): array
    {
        $sql = "SELECT al.*, u.full_name
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $sql .= " AND al.module = :module";
            $params['module'] = $filters['module'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND al.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND al.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT 500";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Daftar module unik yang pernah tercatat -- dipakai untuk dropdown filter.
     */
    public function distinctModules(): array
    {
        $rows = $this->db->fetchAll("SELECT DISTINCT module FROM activity_logs ORDER BY module ASC");
        return array_column($rows, 'module');
    }
}
