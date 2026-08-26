<?php
require_once ROOT_PATH . '/core/Model.php';

class ItemCategory extends Model
{
    protected string $table = 'item_categories';
    protected bool $softDelete = true;

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM item_categories WHERE deleted_at IS NULL ORDER BY category_name ASC"
        );
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM item_categories WHERE category_name = :name AND deleted_at IS NULL";
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
        $sql = "{$select} FROM item_categories WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($filters['keyword'])) {
            $sql .= " AND category_name LIKE :kw";
            $params['kw'] = '%' . $filters['keyword'] . '%';
        }
        return [$sql, $params];
    }
}
