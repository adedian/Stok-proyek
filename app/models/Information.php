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

    /** Kategori yang otomatis jadi warning Dashboard (lihat activeWarnings()). */
    public static function warningCategories(): array
    {
        return ['maintenance', 'pengumuman'];
    }

    /** Ringkasan isi (single-line, dipotong) untuk kartu warning/preview. */
    public static function excerpt(string $content, int $length = 110): string
    {
        $flat = trim(preg_replace('/\s+/', ' ', $content));
        if (mb_strlen($flat) <= $length) {
            return $flat;
        }
        return mb_substr($flat, 0, $length - 1) . '…';
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
     * Informasi aktif terbaru untuk widget Dashboard. Selalu status=aktif,
     * publish_date <= hari ini (tidak menampilkan yang dijadwalkan maju), dan
     * belum expired (end_date kosong atau >= hari ini).
     */
    public function recentActive(int $limit = 5): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT i.id, i.title, i.category, i.publish_date
               FROM information i
              WHERE i.deleted_at IS NULL AND i.status = 'aktif' AND i.publish_date <= CURDATE()
                AND (i.end_date IS NULL OR i.end_date >= CURDATE())
              ORDER BY i.publish_date DESC, i.id DESC
              LIMIT {$limit}"
        );
    }

    /**
     * Informasi kategori Maintenance/Pengumuman yang harus tampil sebagai
     * warning di Dashboard (& lonceng topbar -- lihat DashboardStat::activeAlerts(),
     * satu-satunya pemanggil). Syarat: status aktif, sudah waktunya publish,
     * belum expired. Urutan: Maintenance dulu, baru Pengumuman, lalu tanggal
     * publikasi terbaru -- SESUAI permintaan, bukan urutan bebas.
     */
    public function activeWarnings(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $categories = self::warningCategories();
        $placeholders = [];
        $params = [];
        foreach ($categories as $i => $cat) {
            $key = "cat{$i}";
            $placeholders[] = ":{$key}";
            $params[$key] = $cat;
        }
        $in = implode(', ', $placeholders);

        return $this->db->fetchAll(
            "SELECT id, title, category, content, publish_date, end_date
               FROM information
              WHERE deleted_at IS NULL
                AND status = 'aktif'
                AND category IN ({$in})
                AND publish_date <= CURDATE()
                AND (end_date IS NULL OR end_date >= CURDATE())
              ORDER BY CASE category WHEN 'maintenance' THEN 1 WHEN 'pengumuman' THEN 2 ELSE 3 END,
                       publish_date DESC, id DESC
              LIMIT {$limit}",
            $params
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
