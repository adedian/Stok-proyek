<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Master Bank -- sumber dropdown Bank (Loan/HR) untuk modul Bank. Pola
 * identik CashCategory/MasterRekening.
 */
class MasterBank extends Model
{
    protected string $table = 'master_banks';
    protected bool $softDelete = true;

    public array $jenisLabels = [
        'loan' => 'Loan',
        'hr'   => 'HR',
    ];

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM master_banks WHERE deleted_at IS NULL AND is_active = 1 ORDER BY bank_name ASC"
        );
    }

    public function kodeExists(string $kode, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM master_banks WHERE bank_code = :kode AND deleted_at IS NULL";
        $params = ['kode' => $kode];
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
        $sort = in_array($sort, ['bank_code', 'bank_name', 'jenis', 'created_at'], true) ? $sort : 'bank_name';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sort} {$dir} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $select = $countOnly ? 'SELECT COUNT(*) AS total' : 'SELECT *';
        $sql = "{$select} FROM master_banks WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['bank_name'],
                ['bank_code'],
                'mbkw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        if (!empty($filters['jenis']) && in_array($filters['jenis'], ['loan', 'hr'], true)) {
            $sql .= " AND jenis = :jenis";
            $params['jenis'] = $filters['jenis'];
        }
        return [$sql, $params];
    }
}
