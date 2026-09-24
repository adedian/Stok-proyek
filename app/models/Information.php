<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Information (Pusat Informasi) -- pengumuman/keterangan untuk seluruh
 * pengguna aplikasi. Kategori ENUM tetap (lihat migration), status
 * 'aktif'/'tidak_aktif' menentukan tampil-tidaknya ke user biasa.
 */
class Information extends Model
{
    protected string $table = 'information';
    protected bool $softDelete = true;

    /** Pilihan kategori tetap (slug => label tampilan). */
    public static function categoryOptions(): array
    {
        return [
            'umum'        => 'Umum',
            'pengumuman'  => 'Pengumuman',
            'sistem'      => 'Sistem',
            'prosedur'    => 'Prosedur',
            'maintenance' => 'Maintenance',
            'lainnya'     => 'Lainnya',
        ];
    }

    public static function categoryLabel(?string $slug): string
    {
        return self::categoryOptions()[$slug] ?? ($slug ?? '-');
    }

    public static function statusOptions(): array
    {
        return [
            'aktif'       => 'Aktif',
            'tidak_aktif' => 'Tidak Aktif',
        ];
    }

    public function find(int $id)
    {
        return $this->db->fetchOne(
            "SELECT i.*, u.full_name AS created_by_name
               FROM information i
               LEFT JOIN users u ON u.id = i.created_by
              WHERE i.id = :id AND i.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public function countFiltered(array $filters): int
    {
        [$sql, $params] = $this->buildListQuery($filters, true);
        $result = $this->db->fetchOne($sql, $params);
        return (int) ($result['total'] ?? 0);
    }

    public function listPaginated(array $filters, string $sort, string $dir, int $limit, int $offset): array
    {
        [$sql, $params] = $this->buildListQuery($filters);

        $sortable = ['publish_date', 'title', 'status', 'created_at'];
        $sort = in_array($sort, $sortable, true) ? $sort : 'publish_date';
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY i.{$sort} {$dir}, i.id DESC LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Informasi aktif terbaru untuk widget Dashboard. Selalu status=aktif &
     * publish_date <= hari ini (tidak menampilkan yang dijadwalkan maju).
     */
    public function recentActive(int $limit = 5): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT i.id, i.title, i.category, i.publish_date
               FROM information i
              WHERE i.deleted_at IS NULL AND i.status = 'aktif' AND i.publish_date <= CURDATE()
              ORDER BY i.publish_date DESC, i.id DESC
              LIMIT {$limit}"
        );
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $select = $countOnly ? 'SELECT COUNT(*) AS total' : 'SELECT i.*, u.full_name AS created_by_name';
        $sql = "{$select}
                  FROM information i
                  LEFT JOIN users u ON u.id = i.created_by
                 WHERE i.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['category'])) {
            $sql .= " AND i.category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND i.publish_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND i.publish_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause($filters['keyword'], ['i.title', 'i.content', 'u.full_name'], [], 'infokw');
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }

        return [$sql, $params];
    }
}
