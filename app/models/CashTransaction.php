<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/CashTransactionItem.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/Inventory.php';

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

    // ===================== INTEGRASI STOK (kategori baris ber-affects_stock) =====================

    /**
     * Kredit stok dari baris rincian Kas yang kategorinya ber-affects_stock=1
     * dan SUDAH tersimpan. Idempotent via kolom cash_transaction_items.stock_posted_at.
     * Bucket ('kantor' / 'proyek') diambil dari cash_categories.stock_scope tiap
     * baris; baris scope 'proyek' pakai project_id BARIS itu.
     * Dipanggil di dalam transaction (store / update / restore Trash).
     * @return int jumlah baris yang menambah stok
     */
    public function applyStockCredit(int $trxId): int
    {
        $header = $this->db->fetchOne(
            "SELECT trx_date FROM cash_transactions WHERE id = :id",
            ['id' => $trxId]
        );
        if (!$header) {
            return 0;
        }

        $inv = new Inventory();
        $n = 0;
        foreach ((new CashTransactionItem())->stockRowsByTransaction($trxId) as $row) {
            if (!empty($row['stock_posted_at']) || (float) $row['qty'] <= 0) {
                continue;
            }
            $scope = $row['stock_scope'] === 'kantor' ? 'kantor' : 'proyek';
            $projectId = ($scope === 'kantor' || $row['project_id'] === null) ? null : (int) $row['project_id'];
            // Stok dicatat atas NAMA BARANG MASTER (kalau tertaut) supaya cocok
            // dengan join items.item_name di Stok Barang / Laporan Stok Barang.
            $stockName = !empty($row['master_item_name']) ? $row['master_item_name'] : $row['uraian'];
            $inv->creditStock(
                $stockName,
                (string) ($row['unit'] ?? ''),
                $projectId,
                (float) $row['qty'],
                'kas',
                $trxId,
                (string) $header['trx_date'],
                currentUserId(),
                $scope
            );
            $this->db->query(
                "UPDATE cash_transaction_items SET stock_posted_at = NOW() WHERE id = :id",
                ['id' => (int) $row['id']]
            );
            $n++;
        }
        return $n;
    }

    /**
     * Balikkan semua kredit stok transaksi Kas ini (baris yang stock_posted_at
     * terisi). Dipanggil sebelum update / soft-delete. No-op kalau tidak ada
     * baris ber-kategori stok.
     */
    public function applyStockReverse(int $trxId): void
    {
        $inv = new Inventory();
        foreach ((new CashTransactionItem())->stockRowsByTransaction($trxId) as $row) {
            if (empty($row['stock_posted_at']) || (float) $row['qty'] <= 0) {
                continue;
            }
            $scope = $row['stock_scope'] === 'kantor' ? 'kantor' : 'proyek';
            $projectId = ($scope === 'kantor' || $row['project_id'] === null) ? null : (int) $row['project_id'];
            $stockName = !empty($row['master_item_name']) ? $row['master_item_name'] : $row['uraian'];
            $inv->reverseCredit(
                $stockName,
                (string) ($row['unit'] ?? ''),
                $projectId,
                (float) $row['qty'],
                'kas',
                $trxId,
                currentUserId(),
                $scope
            );
            $this->db->query(
                "UPDATE cash_transaction_items SET stock_posted_at = NULL WHERE id = :id",
                ['id' => (int) $row['id']]
            );
        }
    }

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
            "SELECT c.*, usr.full_name AS created_by_name
               FROM cash_transactions c
               LEFT JOIN users usr ON usr.id = c.created_by
              WHERE c.id = :id AND c.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public function listFiltered(array $filters, ?array $scopePics, ?array $divisionScope = null): array
    {
        [$where, $params] = $this->buildWhere($filters, $scopePics, $divisionScope);
        // Kategori sekarang per baris rincian -> tampilkan gabungan kategori
        // unik transaksi ini di kolom "Kategori" daftar Kas.
        $sql = "SELECT c.*, usr.full_name AS created_by_name,
                       (SELECT GROUP_CONCAT(DISTINCT cc.category_name ORDER BY cc.category_name SEPARATOR ', ')
                          FROM cash_transaction_items cti
                          JOIN cash_categories cc ON cc.id = cti.cash_category_id
                         WHERE cti.cash_transaction_id = c.id) AS category_name
                  FROM cash_transactions c
                  LEFT JOIN users usr ON usr.id = c.created_by
                 {$where}
              ORDER BY c.trx_date DESC, c.id DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function distinctPics(?array $scopePics, ?array $divisionScope = null): array
    {
        if ($scopePics !== null && count($scopePics) === 0) {
            return [];
        }
        if ($divisionScope !== null && count($divisionScope) === 0) {
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
        if ($divisionScope !== null) {
            $in = [];
            foreach (array_values($divisionScope) as $i => $d) {
                $in[] = ":sd{$i}";
                $params["sd{$i}"] = $d;
            }
            $sql .= " AND division IN (" . implode(',', $in) . ")";
        }
        $sql .= " ORDER BY pic ASC";
        return array_column($this->db->fetchAll($sql, $params), 'pic');
    }

    /**
     * Saldo awal = akumulasi (masuk - keluar) SEBELUM date_from.
     * Kalau tidak ada filter date_from -> 0 (laporan buku kas dari awal).
     * Filter pic/kategori tetap dihormati supaya saldo awal konsisten dengan isi laporan.
     */
    public function saldoAwal(array $filters, ?array $scopePics, ?array $divisionScope = null): float
    {
        if (empty($filters['date_from'])) {
            return 0.0;
        }
        $f = $filters;
        unset($f['date_from'], $f['date_to']);
        [$where, $params] = $this->buildWhere($f, $scopePics, $divisionScope);
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
    public function reportLedger(array $filters, ?array $scopePics, float $saldoAwal, ?array $divisionScope = null): array
    {
        [$where, $params] = $this->buildWhere($filters, $scopePics, $divisionScope);
        $trx = $this->db->fetchAll(
            "SELECT c.id, c.trx_date, c.no_bukti, c.mutasi
               FROM cash_transactions c
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
                    'kategori'  => $it['category_name'] ?? '',
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

    /**
     * Ringkasan saldo Kas per divisi + total keseluruhan, untuk kartu saldo
     * di halaman utama Kas (hanya dirender untuk Super Admin / Accounting).
     * saldo = opening_balance divisi + SUM(masuk) - SUM(keluar).
     * $divisionScope membatasi divisi yang dihitung (null = semua).
     *
     * @return array{rows: array<int,array{division:string,label:string,masuk:float,keluar:float,saldo:float}>, total: float}
     */
    public function balanceByDivision(?array $scopePics, ?array $divisionScope = null): array
    {
        [$where, $params] = $this->buildWhere([], $scopePics, $divisionScope);
        $agg = $this->db->fetchAll(
            "SELECT c.division,
                    COALESCE(SUM(CASE WHEN c.mutasi='masuk'  THEN c.total_amount ELSE 0 END),0) AS masuk,
                    COALESCE(SUM(CASE WHEN c.mutasi='keluar' THEN c.total_amount ELSE 0 END),0) AS keluar
               FROM cash_transactions c
              {$where}
           GROUP BY c.division",
            $params
        );

        $opening = [];
        foreach ($this->db->fetchAll("SELECT division, opening_balance FROM cash_opening_balances") as $o) {
            $opening[$o['division']] = (float) $o['opening_balance'];
        }

        $labels = [
            'project'    => 'Saldo Kas Project',
            'accounting' => 'Saldo Kas Accounting',
            'purchase'   => 'Saldo Kas Purchase',
            'umum'       => 'Saldo Kas Umum',
        ];

        $rows = [];
        $total = 0.0;
        foreach ($agg as $r) {
            $div = $r['division'];
            $saldo = ($opening[$div] ?? 0.0) + (float) $r['masuk'] - (float) $r['keluar'];
            $total += $saldo;
            $rows[] = [
                'division' => $div,
                'label'    => $labels[$div] ?? ('Saldo Kas ' . ucfirst($div)),
                'masuk'    => (float) $r['masuk'],
                'keluar'   => (float) $r['keluar'],
                'saldo'    => $saldo,
            ];
        }
        usort($rows, fn($a, $b) => strcmp($a['label'], $b['label']));

        return ['rows' => $rows, 'total' => $total];
    }

    private function buildWhere(array $filters, ?array $scopePics, ?array $divisionScope = null): array
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

        // Cakupan divisi (mis. Project Manager -> hanya 'project'). null = bebas.
        if ($divisionScope !== null) {
            if (count($divisionScope) === 0) {
                $sql .= " AND 1 = 0";
            } else {
                $in = [];
                foreach (array_values($divisionScope) as $i => $d) {
                    $in[] = ":d{$i}";
                    $params["d{$i}"] = $d;
                }
                $sql .= " AND c.division IN (" . implode(',', $in) . ")";
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
            $sql .= " AND EXISTS (SELECT 1 FROM cash_transaction_items cti
                                   WHERE cti.cash_transaction_id = c.id
                                     AND cti.cash_category_id = :category_id)";
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['mutasi']) && in_array($filters['mutasi'], ['masuk', 'keluar'], true)) {
            $sql .= " AND c.mutasi = :mutasi";
            $params['mutasi'] = $filters['mutasi'];
        }

        return [$sql, $params];
    }
}
