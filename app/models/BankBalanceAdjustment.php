<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/BankTransaction.php';

/**
 * Saldo Bank MANUAL (khusus Laporan Pengajuan Budget) -- append-only, TERPISAH dari ledger bank_transactions.
 * Tidak pernah membuat/mengubah/menghapus transaksi Bank, jadi saldo aktual Kas/Bank tidak terpengaruh.
 *
 * Rumus saldo bank B pada tanggal D:
 *   - ada entri manual dengan effective_date <= D  -> saldo_baru entri TERBARU (effective_date lalu id)
 *       + (Bank Masuk - Bank Keluar) dengan trx_date > effective_date dan <= D
 *   - tidak ada                                     -> saldo dari ledger (BankTransaction::balancesByBankAsOf)
 */
class BankBalanceAdjustment extends Model
{
    protected string $table = 'bank_balance_adjustments';

    public const MAX_ABS = 999999999999.0;

    /**
     * Saldo SEMUA rekening pada tanggal $date.
     * @return array<int,array{saldo:float,manual:?array}> bank_id => saldo + entri manual dasar (null bila dari ledger)
     */
    public function balancesAsOf(string $date): array
    {
        $ledger = (new BankTransaction())->balancesByBankAsOf($date);
        $out = [];
        foreach ($ledger as $bid => $saldo) {
            $out[(int) $bid] = ['saldo' => (float) $saldo, 'manual' => null];
        }
        // entri manual terbaru per rekening dengan effective_date <= $date
        $rows = $this->db->fetchAll(
            "SELECT a.* FROM bank_balance_adjustments a
              WHERE a.effective_date <= :d
                AND NOT EXISTS (
                    SELECT 1 FROM bank_balance_adjustments b
                     WHERE b.bank_id = a.bank_id AND b.effective_date <= :d2
                       AND (b.effective_date > a.effective_date OR (b.effective_date = a.effective_date AND b.id > a.id))
                )",
            ['d' => $date, 'd2' => $date]
        );
        foreach ($rows as $a) {
            $bid = (int) $a['bank_id'];
            $out[$bid] = [
                'saldo'  => (float) $a['new_balance'] + $this->ledgerDelta($bid, $a['effective_date'], $date),
                'manual' => $a,
            ];
        }
        return $out;
    }

    /** Saldo satu rekening pada tanggal tertentu (sama rumusnya dengan balancesAsOf). */
    public function balanceAt(int $bankId, string $date): array
    {
        $all = $this->balancesAsOf($date);
        return $all[$bankId] ?? ['saldo' => 0.0, 'manual' => null];
    }

    /** (Masuk - Keluar) transaksi Bank rekening itu dengan  $afterDate < trx_date <= $toDate. */
    private function ledgerDelta(int $bankId, string $afterDate, string $toDate): float
    {
        $r = $this->db->fetchOne(
            "SELECT COALESCE(SUM(CASE WHEN mutasi='masuk' THEN amount ELSE -amount END),0) AS d
               FROM bank_transactions
              WHERE bank_id = :b AND deleted_at IS NULL AND trx_date > :a AND trx_date <= :t",
            ['b' => $bankId, 'a' => $afterDate, 't' => $toDate]
        );
        return (float) ($r['d'] ?? 0);
    }

    /**
     * Catat saldo manual baru (append-only). Saldo lama dihitung ULANG di server pada tanggal berlaku
     * (bukan dipercaya dari form). @return int id baris histori
     */
    public function record(int $bankId, string $effectiveDate, float $newBalance, string $note, ?int $userId): int
    {
        $old = $this->balanceAt($bankId, $effectiveDate)['saldo'];
        return $this->db->insert('bank_balance_adjustments', [
            'bank_id'        => $bankId,
            'effective_date' => $effectiveDate,
            'old_balance'    => round($old, 2),
            'new_balance'    => round($newBalance, 2),
            'diff_amount'    => round($newBalance - $old, 2),
            'note'           => $note,
            'created_by'     => $userId,
        ]);
    }

    /** Histori perubahan (terbaru dulu), opsional per rekening. */
    public function history(?int $bankId, int $limit = 300): array
    {
        $limit = max(1, min(1000, $limit));
        $sql = "SELECT a.*, mb.bank_name, mb.no_rekening, mb.jenis, u.full_name AS user_name
                  FROM bank_balance_adjustments a
                  JOIN master_banks mb ON mb.id = a.bank_id
                  LEFT JOIN users u ON u.id = a.created_by";
        $p = [];
        if ($bankId) {
            $sql .= " WHERE a.bank_id = :b";
            $p['b'] = $bankId;
        }
        return $this->db->fetchAll($sql . " ORDER BY a.id DESC LIMIT {$limit}", $p);
    }
}
