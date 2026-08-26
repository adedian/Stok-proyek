<?php
require_once ROOT_PATH . '/core/Model.php';

class Client extends Model
{
    protected string $table = 'clients';
    protected bool $softDelete = true;

    private array $sortWhitelist = ['client_code', 'client_name', 'contact_person', 'status', 'created_at'];

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM clients WHERE deleted_at IS NULL AND status = 'active' ORDER BY client_name ASC"
        );
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM clients WHERE client_name = :name AND deleted_at IS NULL";
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
        $sort = in_array($sort, $this->sortWhitelist, true) ? $sort : 'client_name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sort} {$dir} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $select = $countOnly ? 'SELECT COUNT(*) AS total' : 'SELECT *';
        $sql = "{$select} FROM clients WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($filters['keyword'])) {
            [$codeSql, $codeParams] = codeSearchClause('client_code', $filters['keyword'], 'ckw');
            $sql .= " AND (client_name LIKE :kw1 OR client_code LIKE :kw2 OR contact_person LIKE :kw3{$codeSql})";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
            $params = array_merge($params, $codeParams);
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }

        return [$sql, $params];
    }
}
