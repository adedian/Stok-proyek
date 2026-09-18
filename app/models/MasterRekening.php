<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Master Rekening (khusus Kas) -- pola identik CashCategory: soft-delete +
 * list terfilter + cek kode unik. Dipakai sebagai dropdown opsional
 * (rekening_id) pada transaksi Kas.
 */
class MasterRekening extends Model
{
    protected string $table = 'master_rekening';
    protected bool $softDelete = true;

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM master_rekening WHERE deleted_at IS NULL AND is_active = 1 ORDER BY nama_rekening ASC"
        );
    }

    public function kodeExists(string $kode, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM master_rekening WHERE kode_rekening = :kode AND deleted_at IS NULL";
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
        $sort = in_array($sort, ['kode_rekening', 'nama_rekening', 'jenis', 'created_at'], true) ? $sort : 'nama_rekening';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sort} {$dir} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $select = $countOnly ? 'SELECT COUNT(*) AS total' : 'SELECT *';
        $sql = "{$select} FROM master_rekening WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['nama_rekening', 'jenis', 'pic_name'],
                ['kode_rekening'],
                'mrkw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        return [$sql, $params];
    }
}
