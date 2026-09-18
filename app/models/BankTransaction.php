<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Transaksi Bank (Revisi Kas/Bank) -- khusus Accounting/Super Admin. Lebih
 * flat dari Kas (tanpa baris rincian/kategori/integrasi stok).
 */
class BankTransaction extends Model
{
    protected string $table = 'bank_transactions';
    protected bool $softDelete = true;

    public function noBuktiExists(string $noBukti, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM bank_transactions WHERE no_bukti = :nb AND deleted_at IS NULL";
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
            "SELECT b.*, mb.bank_name, mb.jenis AS bank_jenis, p.project_name, usr.full_name AS created_by_name
               FROM bank_transactions b
               JOIN master_banks mb ON mb.id = b.bank_id
               LEFT JOIN projects p ON p.id = b.project_id
               LEFT JOIN users usr ON usr.id = b.created_by
              WHERE b.id = :id AND b.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public function listFiltered(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT b.*, mb.bank_name, mb.jenis AS bank_jenis, p.project_name, usr.full_name AS created_by_name
                  FROM bank_transactions b
                  JOIN master_banks mb ON mb.id = b.bank_id
                  LEFT JOIN projects p ON p.id = b.project_id
                  LEFT JOIN users usr ON usr.id = b.created_by
                 {$where}
              ORDER BY b.trx_date DESC, b.id DESC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Saldo Bank SEBELUM date_from -- pola sama CashTransaction::saldoAwal(),
     * dipakai saat Laporan Kas menggabungkan Bank ke buku gabungan.
     */
    public function saldoAwal(array $filters): float
    {
        if (empty($filters['date_from'])) {
            return 0.0;
        }
        $f = $filters;
        unset($f['date_from'], $f['date_to']);
        [$where, $params] = $this->buildWhere($f);
        $params['df'] = $filters['date_from'];
        $row = $this->db->fetchOne(
            "SELECT
               COALESCE(SUM(CASE WHEN b.mutasi='masuk'  THEN b.amount ELSE 0 END),0) AS masuk,
               COALESCE(SUM(CASE WHEN b.mutasi='keluar' THEN b.amount ELSE 0 END),0) AS keluar
             FROM bank_transactions b
             {$where} AND b.trx_date < :df",
            $params
        );
        return (float) ($row['masuk'] ?? 0) - (float) ($row['keluar'] ?? 0);
    }

    /** Baris terurut kronologis + saldo berjalan, untuk Laporan Bank (pola sama Laporan Kas). */
    public function reportLedger(array $filters, float $saldoAwal = 0.0): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $rows = $this->db->fetchAll(
            "SELECT b.*, mb.bank_name, mb.jenis AS bank_jenis, p.project_name
               FROM bank_transactions b
               JOIN master_banks mb ON mb.id = b.bank_id
               LEFT JOIN projects p ON p.id = b.project_id
              {$where}
           ORDER BY b.trx_date ASC, b.id ASC",
            $params
        );
        $saldo = $saldoAwal;
        $out = [];
        foreach ($rows as $r) {
            $masuk  = $r['mutasi'] === 'masuk' ? (float) $r['amount'] : 0.0;
            $keluar = $r['mutasi'] === 'keluar' ? (float) $r['amount'] : 0.0;
            $saldo += $masuk - $keluar;
            $r['masuk']  = $masuk;
            $r['keluar'] = $keluar;
            $r['saldo']  = $saldo;
            $out[] = $r;
        }
        return ['saldo_awal' => $saldoAwal, 'saldo_akhir' => $saldo, 'rows' => $out];
    }

    private function buildWhere(array $filters): array
    {
        $sql = "WHERE b.deleted_at IS NULL";
        $params = [];
        if (!empty($filters['date_from'])) {
            $sql .= " AND b.trx_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND b.trx_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        // project_ids[] (checklist, baru) menang atas project_id tunggal (lama,
        // dipertahankan untuk kompatibilitas link lama).
        if (!empty($filters['project_ids']) && is_array($filters['project_ids'])) {
            $in = [];
            foreach (array_values($filters['project_ids']) as $i => $pid) {
                $in[] = ":pp{$i}";
                $params["pp{$i}"] = (int) $pid;
            }
            $sql .= " AND b.project_id IN (" . implode(',', $in) . ")";
        } elseif (!empty($filters['project_id'])) {
            $sql .= " AND b.project_id = :project_id";
            $params['project_id'] = (int) $filters['project_id'];
        }
        if (!empty($filters['bank_ids']) && is_array($filters['bank_ids'])) {
            $in = [];
            foreach (array_values($filters['bank_ids']) as $i => $bid) {
                $in[] = ":b{$i}";
                $params["b{$i}"] = (int) $bid;
            }
            $sql .= " AND b.bank_id IN (" . implode(',', $in) . ")";
        }
        if (!empty($filters['mutasi']) && in_array($filters['mutasi'], ['masuk', 'keluar'], true)) {
            $sql .= " AND b.mutasi = :mutasi";
            $params['mutasi'] = $filters['mutasi'];
        }
        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['b.pic', 'b.uraian'],
                ['b.no_bukti'],
                'bkkw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        return [$sql, $params];
    }
}
