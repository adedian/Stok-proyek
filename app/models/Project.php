<?php
require_once ROOT_PATH . '/core/Model.php';

class Project extends Model
{
    protected string $table = 'projects';
    protected bool $softDelete = true;

    private array $sortWhitelist = ['project_code', 'project_name', 'location', 'status', 'created_at'];

    public array $statusLabels = [
        'planning' => 'Planning',
        'ongoing'  => 'Ongoing',
        'closed'   => 'Closed',
    ];

    public array $statusBadgeClass = [
        'planning' => 'secondary',
        'ongoing'  => 'success',
        'closed'   => 'dark',
    ];

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM projects WHERE deleted_at IS NULL AND status != 'closed' ORDER BY project_name ASC"
        );
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM projects WHERE project_name = :name AND deleted_at IS NULL";
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
            "SELECT p.* FROM projects p WHERE p.id = :id AND p.deleted_at IS NULL",
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
        $sort = in_array($sort, $this->sortWhitelist, true) ? "p.{$sort}" : 'p.project_name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sort} {$dir} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $select = $countOnly ? 'SELECT COUNT(*) AS total' : 'SELECT p.*';
        $sql = "{$select} FROM projects p WHERE p.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['keyword'])) {
            [$codeSql, $codeParams] = codeSearchClause('p.project_code', $filters['keyword'], 'pkw');
            $sql .= " AND (p.project_name LIKE :kw1 OR p.project_code LIKE :kw2 OR p.location LIKE :kw3{$codeSql})";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
            $params = array_merge($params, $codeParams);
        }
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params['status'] = $filters['status'];
        }

        return [$sql, $params];
    }
}
