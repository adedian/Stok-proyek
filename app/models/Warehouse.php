<?php
require_once ROOT_PATH . '/core/Model.php';

class Warehouse extends Model
{
    protected string $table = 'warehouses';
    protected bool $softDelete = true;

    private array $sortWhitelist = ['warehouse_code', 'warehouse_name', 'status', 'created_at'];

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM warehouses WHERE deleted_at IS NULL AND status = 'active' ORDER BY warehouse_name ASC"
        );
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM warehouses WHERE warehouse_name = :name AND deleted_at IS NULL";
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
        $sort = in_array($sort, $this->sortWhitelist, true) ? $sort : 'warehouse_name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sort} {$dir} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $select = $countOnly ? 'SELECT COUNT(*) AS total' : 'SELECT *';
        $sql = "{$select} FROM warehouses WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($filters['keyword'])) {
            $sql .= " AND (warehouse_name LIKE :kw1 OR warehouse_code LIKE :kw2)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }

        return [$sql, $params];
    }
}
