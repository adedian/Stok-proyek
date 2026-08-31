<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * PeriodLock -- Tutup Bulan per-modul.
 * Satu baris = satu penutupan (module + period_end). Bisa reopen & re-close
 * (baris yang sama di-update). "Locked atau tidak" = ada baris status='closed'
 * untuk modul tsb yang period_end >= tanggal transaksi.
 *
 * Modul yang dikenal (dipakai untuk validasi input & label):
 */
class PeriodLock extends Model
{
    protected string $table = 'accounting_period_locks';

    public const MODULES = [
        'purchase_order'   => 'Purchase Order',
        'payment'          => 'Pembayaran',
        'goods_receipt'    => 'Penerimaan Barang',
        'validation'       => 'Validasi Barang',
        'stock_out'        => 'Pengeluaran Barang',
        'cash'             => 'Kas',
        'offline_purchase' => 'Pembelian Offline',
        'sales_invoice'    => 'Invoice Keluar',
        'stock_opname'     => 'Stok Opname',
    ];

    /** Batas tanggal terkunci untuk satu modul ('YYYY-MM-DD') atau null bila belum ada. */
    public function maxClosedEnd(string $module): ?string
    {
        $row = $this->db->fetchOne(
            "SELECT MAX(period_end) AS max_end
               FROM accounting_period_locks
              WHERE module = :m AND status = 'closed'",
            ['m' => $module]
        );
        return $row && $row['max_end'] !== null ? (string) $row['max_end'] : null;
    }

    /** Semua batas terkunci sekaligus: [module => 'YYYY-MM-DD']. */
    public function allClosedEnds(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT module, MAX(period_end) AS max_end
               FROM accounting_period_locks
              WHERE status = 'closed'
           GROUP BY module"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['module']] = (string) $r['max_end'];
        }
        return $out;
    }

    /** History untuk halaman Tutup Bulan (terbaru dulu). */
    public function history(): array
    {
        return $this->db->fetchAll(
            "SELECT pl.*,
                    uc.full_name AS closed_by_name,
                    ur.full_name AS reopened_by_name
               FROM accounting_period_locks pl
               LEFT JOIN users uc ON uc.id = pl.closed_by
               LEFT JOIN users ur ON ur.id = pl.reopened_by
           ORDER BY pl.period_end DESC, pl.module ASC, pl.updated_at DESC"
        );
    }

    /**
     * Tutup satu modul untuk periode s/d $periodEnd. Idempotent lewat
     * UNIQUE(module, period_end): kalau baris sudah ada -> set kembali 'closed'.
     * Return: 'closed' (baru ditutup), 'already' (sudah closed sebelumnya).
     */
    public function closePeriod(string $module, string $periodStart, string $periodEnd, ?int $userId, ?string $note = null): string
    {
        $existing = $this->db->fetchOne(
            "SELECT id, status FROM accounting_period_locks WHERE module = :m AND period_end = :pe",
            ['m' => $module, 'pe' => $periodEnd]
        );

        if ($existing) {
            if ($existing['status'] === 'closed') {
                return 'already';
            }
            $this->db->update(
                'accounting_period_locks',
                [
                    'status'      => 'closed',
                    'period_start' => $periodStart,
                    'closed_at'   => date('Y-m-d H:i:s'),
                    'closed_by'   => $userId,
                    'reopened_at' => null,
                    'reopened_by' => null,
                    'note'        => $note,
                ],
                'id = :id',
                ['id' => (int) $existing['id']]
            );
            return 'closed';
        }

        $this->db->insert('accounting_period_locks', [
            'module'       => $module,
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
            'status'       => 'closed',
            'closed_at'    => date('Y-m-d H:i:s'),
            'closed_by'    => $userId,
            'note'         => $note,
        ]);
        return 'closed';
    }

    /** Buka kembali satu baris penutupan. */
    public function reopen(int $id, ?int $userId): bool
    {
        $row = $this->find($id);
        if (!$row || $row['status'] !== 'closed') {
            return false;
        }
        $this->db->update(
            'accounting_period_locks',
            [
                'status'      => 'open',
                'reopened_at' => date('Y-m-d H:i:s'),
                'reopened_by' => $userId,
            ],
            'id = :id',
            ['id' => $id]
        );
        return true;
    }
}
