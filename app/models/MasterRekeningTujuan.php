<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Master Rekening Tujuan -- daftar penerima pembayaran (nama + bank + no. rekening).
 * Sumber dropdown "Rekening Tujuan" di Pengajuan Budget (baris tetap boleh diketik manual).
 * Soft-delete; pola identik MasterRekening.
 */
class MasterRekeningTujuan extends Model
{
    protected string $table = 'master_rekening_tujuan';
    protected bool $softDelete = true;

    /** Label tampilan/snapshot: "Nama - Bank No.Rek" (bagian kosong dilewati). Sama dengan format spreadsheet referensi. */
    public static function label(array $r): string
    {
        $tail = trim(trim((string) ($r['bank_tujuan'] ?? '')) . ' ' . trim((string) ($r['no_rekening'] ?? '')));
        $name = trim((string) ($r['nama_penerima'] ?? ''));
        return $tail !== '' ? $name . ' - ' . $tail : $name;
    }

    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM master_rekening_tujuan WHERE deleted_at IS NULL AND is_active = 1 ORDER BY nama_penerima ASC, id ASC"
        );
    }

    /** Aktif + id yang sedang dipakai baris (saat edit) walau kini nonaktif/terhapus, supaya tidak hilang diam-diam. */
    public function activeListWith(array $includeIds): array
    {
        $includeIds = array_values(array_unique(array_filter(array_map('intval', $includeIds))));
        if (!$includeIds) {
            return $this->activeList();
        }
        $in = [];
        $p = [];
        foreach ($includeIds as $i => $id) {
            $in[] = ":i{$i}";
            $p["i{$i}"] = $id;
        }
        return $this->db->fetchAll(
            "SELECT * FROM master_rekening_tujuan
              WHERE (deleted_at IS NULL AND is_active = 1) OR id IN (" . implode(',', $in) . ")
              ORDER BY nama_penerima ASC, id ASC",
            $p
        );
    }

    /** Penerima dengan bank+no.rekening yang sama (hanya bila no. rekening diisi) sudah ada? */
    public function duplicateExists(string $bank, string $noRek, ?int $excludeId = null): bool
    {
        if (trim($noRek) === '') {
            return false;
        }
        $sql = "SELECT id FROM master_rekening_tujuan WHERE deleted_at IS NULL AND no_rekening = :no AND COALESCE(bank_tujuan,'') = :bk";
        $params = ['no' => $noRek, 'bk' => $bank];
        if ($excludeId) {
            $sql .= " AND id != :ex";
            $params['ex'] = $excludeId;
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
        $sort = in_array($sort, ['nama_penerima', 'bank_tujuan', 'no_rekening', 'created_at'], true) ? $sort : 'nama_penerima';
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$sort} {$dir} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $select = $countOnly ? 'SELECT COUNT(*) AS total' : 'SELECT *';
        $sql = "{$select} FROM master_rekening_tujuan WHERE deleted_at IS NULL";
        $params = [];
        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['nama_penerima', 'bank_tujuan', 'keterangan'],
                ['no_rekening'],
                'mrtkw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        return [$sql, $params];
    }
}
