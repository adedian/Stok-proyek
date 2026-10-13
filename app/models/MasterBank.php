<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/CodeConfig.php';

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
        'kas'  => 'Kas',
    ];

    /**
     * Prefix No Bukti transaksi Bank. Dasarnya per Mutasi (Master Kode 'bank_masuk' / 'bank_keluar',
     * default BM / BK). Bank berjenis LOAN mendapat huruf "L" di belakangnya: Masuk -> BML, Keluar -> BKL
     * (urutan nomor terpisah dari BM/BK). Bank HR tetap BM / BK. Jenis dibaca dari database.
     */
    public function noBuktiPrefix(string $mutasi, int $bankId): string
    {
        $entityType = $mutasi === 'masuk' ? 'bank_masuk' : 'bank_keluar';
        $base = $mutasi === 'masuk' ? 'BM' : 'BK';
        $cfg = (new CodeConfig())->getConfig($entityType);
        if ($cfg && $cfg['prefix'] !== '') {
            $base = $cfg['prefix'];
        }
        $bank = $bankId > 0 ? $this->find($bankId) : null;
        return ($bank && ($bank['jenis'] ?? '') === 'loan') ? $base . 'L' : $base;
    }

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM master_banks WHERE deleted_at IS NULL AND is_active = 1 ORDER BY bank_name ASC"
        );
    }

    /**
     * Pilihan Bank di form: rekening AKTIF + (saat edit) bank yang sudah tersimpan di transaksi itu
     * walau kini nonaktif, supaya membuka/menyimpan transaksi lama tidak mengganti bank diam-diam.
     */
    public function activeListWith(?int $includeId): array
    {
        if (!$includeId) {
            return $this->activeList();
        }
        return $this->db->fetchAll(
            "SELECT * FROM master_banks
              WHERE (deleted_at IS NULL AND is_active = 1) OR id = :inc
              ORDER BY bank_name ASC",
            ['inc' => $includeId]
        );
    }

    /**
     * Pilihan Bank di FILTER daftar/laporan: rekening aktif + rekening nonaktif/terhapus yang masih
     * punya transaksi (histori tetap bisa difilter). Baris nonaktif diberi 'inactive' = 1.
     */
    public function filterList(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT mb.*, (mb.is_active = 0 OR mb.deleted_at IS NOT NULL) AS inactive
               FROM master_banks mb
              WHERE (mb.deleted_at IS NULL AND mb.is_active = 1)
                 OR EXISTS (SELECT 1 FROM bank_transactions bt WHERE bt.bank_id = mb.id AND bt.deleted_at IS NULL)
              ORDER BY mb.bank_name ASC"
        );
        return $rows;
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
