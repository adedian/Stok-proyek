<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Master Kategori Kas (Revisi 9). Pola identik ItemCategory:
 * soft-delete + list terfilter + cek nama unik.
 */
class CashCategory extends Model
{
    protected string $table = 'cash_categories';
    protected bool $softDelete = true;

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM cash_categories WHERE deleted_at IS NULL ORDER BY category_name ASC"
        );
    }

    /**
     * Kategori aktif dipetakan per id -- dipakai CashController untuk memvalidasi
     * kategori tiap baris rincian & menentukan apakah baris itu masuk stok
     * (affects_stock) dan bucket mana (stock_scope: 'kantor' / 'proyek').
     *
     * @return array<int,array{id:int,category_name:string,affects_stock:int,stock_scope:?string}>
     */
    public function mapById(): array
    {
        $out = [];
        foreach ($this->activeList() as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM cash_categories WHERE category_name = :name AND deleted_at IS NULL";
        $params = ['name' => $name];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
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
        $sort = $sort === 'created_at' ? 'created_at' : 'category_name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sort} {$dir} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $select = $countOnly ? 'SELECT COUNT(*) AS total' : 'SELECT *';
        $sql = "{$select} FROM cash_categories WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($filters['keyword'])) {
            $sql .= " AND category_name LIKE :kw";
            $params['kw'] = '%' . $filters['keyword'] . '%';
        }
        return [$sql, $params];
    }
}
