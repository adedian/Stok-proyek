<?php
require_once ROOT_PATH . '/core/Model.php';

class Signature extends Model
{
    protected string $table = 'signatures';
    protected bool $softDelete = true;

    public array $statusLabels = [
        'active'   => 'Aktif',
        'inactive' => 'Nonaktif',
    ];

    /**
     * Tanda tangan aktif untuk dipakai di dokumen cetak (PO, Penerimaan Barang).
     * Urut berdasarkan dibuat paling awal supaya urutan tampil di dokumen konsisten.
     */
    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM signatures WHERE status = 'active' AND deleted_at IS NULL ORDER BY created_at ASC"
        );
    }

    /**
     * Cari satu tanda tangan aktif berdasarkan nama persis (dipakai untuk mencocokkan
     * nama Pembuat PO / Penerima Barang dengan Master Tanda Tangan, kalau ada).
     */
    public function findActiveByName(string $name)
    {
        return $this->db->fetchOne(
            "SELECT * FROM signatures WHERE status = 'active' AND deleted_at IS NULL AND name = :name LIMIT 1",
            ['name' => $name]
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
        $sort = in_array($sort, ['name', 'position', 'created_at'], true) ? $sort : 'name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sort} {$dir} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $select = $countOnly ? 'SELECT COUNT(*) AS total' : 'SELECT *';
        $sql = "{$select} FROM signatures WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($filters['keyword'])) {
            $sql .= " AND (name LIKE :kw1 OR position LIKE :kw2)";
            $params['kw1'] = '%' . $filters['keyword'] . '%';
            $params['kw2'] = '%' . $filters['keyword'] . '%';
        }
        return [$sql, $params];
    }
}
