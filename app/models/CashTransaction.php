<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Transaksi Kas (Revisi 9) -- header kas masuk / kas keluar.
 * Rincian nominal ada di `cash_transaction_items` (uraian/qty/satuan/jumlah);
 * `total_amount` = SUM(jumlah) baris item.
 *
 * Soft-delete (ikut Trash). Visibilitas per-PIC via parameter $scopePics:
 *   null  -> lihat semua (Super Admin / Accounting / Project Manager)
 *   array -> hanya baris dengan pic IN (...). Array kosong -> tak ada baris.
 */
class CashTransaction extends Model
{
    protected string $table = 'cash_transactions';
    protected bool $softDelete = true;

    public function noBuktiExists(string $noBukti, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM cash_transactions WHERE no_bukti = :nb AND deleted_at IS NULL";
        $params = ['nb' => $noBukti];
        if ($excludeId) {
            $sql .= " AND id != :ex";
            $params['ex'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        return $this->db->fetchOne(
            "SELECT c.*, cat.category_name, usr.full_name AS created_by_name
               FROM cash_transactions c
               JOIN cash_categories cat ON cat.id = c.category_id
               LEFT JOIN users usr ON usr.id = c.created_by
              WHERE c.id = :id AND c.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public function listFiltered(array $filters, ?array $scopePics): array
    {
        [$where, $params] = $this->buildWhere($filters, $scopePics);
        $sql = "SELECT c.*, cat.category_name, usr.full_name AS created_by_name
                  FROM cash_transactions c
                  JOIN cash_categories cat ON cat.id = c.category_id
                  LEFT JOIN users usr ON usr.id = c.created_by
                 {$where}
              ORDER BY c.trx_date DESC, c.id DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function distinctPics(?array $scopePics): array
    {
        if ($scopePics !== null && count($scopePics) === 0) {
            return [];
        }
        $sql = "SELECT DISTINCT pic FROM cash_transactions WHERE deleted_at IS NULL AND pic <> ''";
        $params = [];
        if ($scopePics !== null) {
            $in = [];
            foreach (array_values($scopePics) as $i => $p) {
                $in[] = ":sp{$i}";
                $params["sp{$i}"] = $p;
            }
            $sql .= " AND pic IN (" . implode(',', $in) . ")";
        }
        $sql .= " ORDER BY pic ASC";
        return array_column($this->db->fetchAll($sql, $params), 'pic');
    }

    /**
     * Saldo awal = akumulasi (masuk - keluar) SEBELUM date_from.
     * Kalau tidak ada filter date_from -> 0 (laporan buku kas dari awal).
     * Filter pic/kategori tetap dihormati supaya saldo awal konsisten dengan isi laporan.
     */
    public function saldoAwal(array $filters, ?array $scopePics): float
    {
        if (empty($filters['date_from'])) {
            return 0.0;
        }
        $f = $filters;
        unset($f['date_from'], $f['date_to']);
        [$where, $params] = $this->buildWhere($f, $scopePics);
        $params['df'] = $filters['date_from'];
        $row = $this->db->fetchOne(
            "SELECT
               COALESCE(SUM(CASE WHEN c.mutasi='masuk'  THEN c.total_amount ELSE 0 END),0) AS masuk,
               COALESCE(SUM(CASE WHEN c.mutasi='keluar' THEN c.total_amount ELSE 0 END),0) AS keluar
             FROM cash_transactions c
             {$where} AND c.trx_date < :df",
            $params
        );
        return (float) ($row['masuk'] ?? 0) - (float) ($row['keluar'] ?? 0);
    }

    /**
     * Baris buku kas (satu baris per ITEM), sudah terurut & lengkap dengan
     * kolom masuk/keluar/saldo berjalan. Header (tgl/no_bukti) hanya diisi di
     * baris pertama tiap transaksi -- sisanya string kosong (mengikuti contoh
     * format laporan dari user).
     *
     * @return array{saldo_awal: float, saldo_akhir: float, rows: array}
     */
    public function reportLedger(array $filters, ?array $scopePics, float $saldoAwal): array
    {
        [$where, $params] = $this->buildWhere($filters, $scopePics);
        $trx = $this->db->fetchAll(
            "SELECT c.id, c.trx_date, c.no_bukti, c.mutasi, cat.category_name
               FROM cash_transactions c
               JOIN cash_categories cat ON cat.id = c.category_id
              {$where}
           ORDER BY c.trx_date ASC, c.id ASC",
            $params
        );

        $rows = [];
        $saldo = $saldoAwal;
        foreach ($trx as $t) {
            $items = (new CashTransactionItem())->byTransaction((int) $t['id']);
            if (empty($items)) {
                $items = [['uraian' => '-', 'qty' => 0, 'satuan' => 0, 'jumlah' => 0]];
            }
            $first = true;
            foreach ($items as $it) {
                $jumlah = (float) $it['jumlah'];
                $masuk  = $t['mutasi'] === 'masuk' ? $jumlah : 0.0;
                $keluar = $t['mutasi'] === 'keluar' ? $jumlah : 0.0;
                $saldo += $masuk - $keluar;
                $rows[] = [
                    'trx_date'  => $first ? $t['trx_date'] : '',
                    'no_bukti'  => $first ? $t['no_bukti'] : '',
                    'kategori'  => $first ? $t['category_name'] : '',
                    'uraian'    => $it['uraian'],
                    'qty'       => (float) $it['qty'],
                    'satuan'    => (float) $it['satuan'],
                    'masuk'     => $masuk,
                    'keluar'    => $keluar,
                    'saldo'     => $saldo,
                ];
                $first = false;
            }
        }

        return ['saldo_awal' => $saldoAwal, 'saldo_akhir' => $saldo, 'rows' => $rows];
    }

    private function buildWhere(array $filters, ?array $scopePics): array
    {
        $sql = "WHERE c.deleted_at IS NULL";
        $params = [];

        if ($scopePics !== null) {
            if (count($scopePics) === 0) {
                $sql .= " AND 1 = 0";
            } else {
                $in = [];
                foreach (array_values($scopePics) as $i => $p) {
                    $in[] = ":p{$i}";
                    $params["p{$i}"] = $p;
                }
                $sql .= " AND c.pic IN (" . implode(',', $in) . ")";
            }
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND c.trx_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND c.trx_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['pic'])) {
            $sql .= " AND c.pic LIKE :pic";
            $params['pic'] = '%' . $filters['pic'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND c.category_id = :category_id";
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['mutasi']) && in_array($filters['mutasi'], ['masuk', 'keluar'], true)) {
            $sql .= " AND c.mutasi = :mutasi";
            $params['mutasi'] = $filters['mutasi'];
        }

        return [$sql, $params];
    }
}
