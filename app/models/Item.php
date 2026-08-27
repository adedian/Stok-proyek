<?php
require_once ROOT_PATH . '/core/Model.php';

class Item extends Model
{
    protected string $table = 'items';
    protected bool $softDelete = true;

    private array $sortWhitelist = ['item_code', 'item_name', 'min_stock', 'status', 'created_at'];

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT i.*, u.unit_name, c.category_name
             FROM items i
             JOIN units u ON u.id = i.unit_id
             LEFT JOIN item_categories c ON c.id = i.category_id AND c.deleted_at IS NULL
             WHERE i.deleted_at IS NULL AND i.status = 'active'
             ORDER BY i.item_name ASC"
        );
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM items WHERE item_name = :name AND deleted_at IS NULL";
        $params = ['name' => $name];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        return $this->db->fetchOne(
            "SELECT i.*, c.category_name, u.unit_name
             FROM items i
             LEFT JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
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
        $sort = in_array($sort, $this->sortWhitelist, true) ? "i.{$sort}" : 'i.item_name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sort} {$dir} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        // Stok tersedia dijumlah lintas project dari tabel inventory, dicocokkan lewat
        // nama barang (items & inventory tidak di-FK-kan langsung -- lihat catatan
        // belowMinStockList() di bawah). LEFT JOIN supaya barang yang belum pernah
        // punya baris inventory sama sekali tetap tampil (tersedia = 0, bukan hilang).
        $select = $countOnly
            ? 'SELECT COUNT(*) AS total'
            : 'SELECT i.*, c.category_name, u.unit_name, COALESCE(SUM(inv.qty_available), 0) AS total_available';
        $sql = "{$select} FROM items i
                LEFT JOIN item_categories c ON c.id = i.category_id
                JOIN units u ON u.id = i.unit_id"
                . (!$countOnly ? " LEFT JOIN inventory inv ON inv.item_name = i.item_name AND inv.deleted_at IS NULL" : "") . "
                WHERE i.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['keyword'])) {
            [$codeSql, $codeParams] = codeSearchClause('i.item_code', $filters['keyword'], 'ikw');
            $sql .= " AND (i.item_name LIKE :kw1 OR i.item_code LIKE :kw2{$codeSql})";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params = array_merge($params, $codeParams);
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND i.category_id = :category_id";
            $params['category_id'] = $filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['stock_type'])) {
            $sql .= " AND i.stock_type = :stock_type";
            $params['stock_type'] = $filters['stock_type'];
        }

        if (!$countOnly) {
            $sql .= " GROUP BY i.id";
        }

        return [$sql, $params];
    }

    /**
     * Jumlah barang aktif yang total stoknya (dijumlah lintas project dari tabel
     * inventory, dicocokkan lewat nama barang -- items & inventory tidak
     * di-FK-kan langsung, lihat catatan phase Master Data) sudah di bawah min_stock.
     */
    public function belowMinStockCount(): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM (
                SELECT i.id
                FROM items i
                LEFT JOIN inventory inv ON inv.item_name = i.item_name AND inv.deleted_at IS NULL
                WHERE i.deleted_at IS NULL AND i.status = 'active' AND i.min_stock > 0
                GROUP BY i.id, i.min_stock
                HAVING COALESCE(SUM(inv.qty_available), 0) <= i.min_stock
            ) t"
        );
        return (int) ($result['total'] ?? 0);
    }

    public function belowMinStockList(): array
    {
        return $this->db->fetchAll(
            "SELECT i.item_name, i.min_stock, u.unit_name, COALESCE(SUM(inv.qty_available), 0) AS total_qty
             FROM items i
             JOIN units u ON u.id = i.unit_id
             LEFT JOIN inventory inv ON inv.item_name = i.item_name AND inv.deleted_at IS NULL
             WHERE i.deleted_at IS NULL AND i.status = 'active' AND i.min_stock > 0
             GROUP BY i.id, i.item_name, i.min_stock, u.unit_name
             HAVING total_qty <= i.min_stock
             ORDER BY i.item_name ASC"
        );
    }
}
