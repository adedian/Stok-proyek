<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

/**
 * PengajuanFee -- lembar "PENGAJUAN FEE" (acuan: sheet "Pengajuan Fee" pada Pengajuan fee.xlsx).
 *
 * Header : Invoice no., Customer, Sales, Penerima Fee, Status Tagihan, NPWP / NIK, tanggal & kota dokumen.
 * Baris  : BARANG, QTY, SATUAN, HARGA, TOTAL, FEE.
 * RUMUS (persis Excel):
 *   TOTAL (baris) = HARGA x QTY                                  (Excel: =G12*E12)
 *   FEE   (baris) = (TOTAL / 1,11) x 10%                         (Excel: =(H12/1.11)*10%  -> "Fee 10% dari DPP")
 *   TOTAL         = SUM(TOTAL), SUM(FEE)                         (Excel: =+SUM(H12:H13), =+SUM(I12:I13))
 *   Potong Pph    = - SUM(FEE) x 2,5%                            (Excel: =+-I18*H19)
 *   Total Fee diterima = SUM(FEE) + Potong Pph                   (Excel: =+SUM(I18:I19))
 * Angka 10% / 11% / 2,5% di Excel tertanam di rumus; di sistem menjadi isian (default tepat 10 / 11 / 2,5) supaya
 * hasil default identik dengan Excel. Pembulatan per sen (2 desimal) di tiap baris & total; Excel tidak membulatkan
 * tetapi hanya menampilkan tanpa desimal, selisihnya < Rp 0,01 per baris.
 * Kolom lain di sheet itu (K, N, O, Q, T, V ...) adalah coretan hitung margin, BUKAN bagian form -- tidak ikut.
 *
 * Status: draft -> diajukan -> diperiksa -> disetujui | ditolak (ditolak di tahap Diajukan oleh pemeriksa atau di tahap
 * Diperiksa oleh penyetuju). ditolak -> draft (revisi, histori tetap). disetujui terkunci. Modul MANDIRI: tidak terhubung
 * ke Pengajuan Budget maupun Kas/Bank.
 */
class PengajuanFee extends Model
{
    protected string $table = 'pengajuan_fees';
    protected bool $softDelete = true;

    public const DRAFT = 'draft';
    public const DIAJUKAN = 'diajukan';
    public const DIPERIKSA = 'diperiksa';
    public const DISETUJUI = 'disetujui';
    public const DITOLAK = 'ditolak';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draft',
        self::DIAJUKAN => 'Diajukan (Menunggu Pemeriksaan)',
        self::DIPERIKSA => 'Diperiksa (Menunggu Persetujuan)',
        self::DISETUJUI => 'Disetujui',
        self::DITOLAK => 'Ditolak',
    ];

    public const STATUS_BADGES = [
        self::DRAFT => 'secondary',
        self::DIAJUKAN => 'warning',
        self::DIPERIKSA => 'info',
        self::DISETUJUI => 'success',
        self::DITOLAK => 'danger',
    ];

    public const MAX_AMOUNT = 999999999999.0;
    public const DEFAULT_FEE_PERCENT = 10.0;
    public const DEFAULT_PPN_PERCENT = 11.0;
    public const DEFAULT_PPH_PERCENT = 2.5;

    public static function statusLabel(?string $s): string
    {
        return self::STATUS_LABELS[$s] ?? (string) $s;
    }

    public static function statusBadge(?string $s): string
    {
        return self::STATUS_BADGES[$s] ?? 'secondary';
    }

    // ------------------------------------------------------------------
    // Hitung (satu-satunya tempat rumus; form JS hanya meniru untuk tampilan)
    // ------------------------------------------------------------------

    /**
     * @param array<int,array{qty:float|string,price:float|string}> $rows
     * @return array{rows:array<int,array{total:float,fee:float}>,total_amount:float,total_fee:float,pph_amount:float,net_fee:float}
     */
    public static function compute(array $rows, float $feePercent, float $ppnPercent, float $pphPercent): array
    {
        $out = [];
        $sumTotal = 0.0;
        $sumFee = 0.0;
        foreach ($rows as $k => $r) {
            $total = round((float) $r['qty'] * (float) $r['price'], 2);                          // =G*E
            $fee = round(($total / (1 + $ppnPercent / 100)) * ($feePercent / 100), 2);            // =(H/1.11)*10%
            $out[$k] = ['total' => $total, 'fee' => $fee];
            $sumTotal += $total;
            $sumFee += $fee;
        }
        $sumTotal = round($sumTotal, 2);
        $sumFee = round($sumFee, 2);
        $pph = round($sumFee * ($pphPercent / 100), 2);                                           // =-I18*H19 (tampil negatif)
        return [
            'rows' => $out, 'total_amount' => $sumTotal, 'total_fee' => $sumFee,
            'pph_amount' => $pph, 'net_fee' => round($sumFee - $pph, 2),                          // =SUM(I18:I19)
        ];
    }

    // ------------------------------------------------------------------
    // Nomor
    // ------------------------------------------------------------------

    public function previewNumber(?string $date = null): string
    {
        return (new DocumentNumber())->preview('pengajuan_fee', 'prefix_fee', $date, 'FEE.HME');
    }

    /** Dipanggil HANYA dari store(), di dalam transaksi pemanggil. */
    public function nextNumber(?string $date = null): string
    {
        return (new DocumentNumber())->next('pengajuan_fee', 'prefix_fee', $date, 'FEE.HME');
    }

    // ------------------------------------------------------------------
    // Baca
    // ------------------------------------------------------------------

    public function findWithRelations(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT f.*, cu.full_name AS created_by_name, ku.full_name AS checked_by_name,
                    au.full_name AS approved_by_name, xu.full_name AS rejected_by_name
               FROM pengajuan_fees f
               LEFT JOIN users cu ON cu.id = f.created_by
               LEFT JOIN users ku ON ku.id = f.checked_by
               LEFT JOIN users au ON au.id = f.approved_by
               LEFT JOIN users xu ON xu.id = f.rejected_by
              WHERE f.id = :id AND f.deleted_at IS NULL",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function items(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM pengajuan_fee_items WHERE fee_id = :id ORDER BY line_no ASC, id ASC",
            ['id' => $id]
        );
    }

    public function history(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT h.*, u.full_name AS user_name
               FROM pengajuan_fee_history h
               LEFT JOIN users u ON u.id = h.user_id
              WHERE h.fee_id = :id
           ORDER BY h.id ASC",
            ['id' => $id]
        );
    }

    public function addHistory(int $id, ?int $userId, string $action, ?string $old, ?string $new, ?string $notes = null): void
    {
        $this->db->insert('pengajuan_fee_history', [
            'fee_id'     => $id,
            'user_id'    => $userId,
            'action'     => $action,
            'old_status' => $old,
            'new_status' => $new,
            'notes'      => $notes,
        ]);
    }

    private function listWhere(array $f): array
    {
        $sql = " WHERE f.deleted_at IS NULL";
        $p = [];
        if (($f['number'] ?? '') !== '') {
            $sql .= " AND (f.fee_number LIKE :n1 OR f.invoice_number LIKE :n2 OR f.customer_name LIKE :n3 OR f.recipient_name LIKE :n4)";
            foreach (['n1', 'n2', 'n3', 'n4'] as $k) {
                $p[$k] = '%' . $f['number'] . '%';
            }
        }
        if (($f['status'] ?? '') !== '' && isset(self::STATUS_LABELS[$f['status']])) {
            $sql .= " AND f.status = :st";
            $p['st'] = $f['status'];
        }
        if (($f['date_from'] ?? '') !== '') {
            $sql .= " AND f.fee_date >= :df";
            $p['df'] = $f['date_from'];
        }
        if (($f['date_to'] ?? '') !== '') {
            $sql .= " AND f.fee_date <= :dt";
            $p['dt'] = $f['date_to'];
        }
        return [$sql, $p];
    }

    public function countFiltered(array $f): int
    {
        [$w, $p] = $this->listWhere($f);
        $r = $this->db->fetchOne("SELECT COUNT(*) AS c FROM pengajuan_fees f {$w}", $p);
        return (int) ($r['c'] ?? 0);
    }

    public function listPaginated(array $f, int $limit, int $offset): array
    {
        [$w, $p] = $this->listWhere($f);
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            "SELECT f.*, cu.full_name AS created_by_name,
                    (SELECT COUNT(*) FROM pengajuan_fee_items i WHERE i.fee_id = f.id) AS item_count
               FROM pengajuan_fees f
               LEFT JOIN users cu ON cu.id = f.created_by
              {$w}
           ORDER BY f.fee_date DESC, f.id DESC
              LIMIT {$limit} OFFSET {$offset}",
            $p
        );
    }

    /** Nilai yang pernah dipakai (saran isian) untuk kolom teks tertentu. */
    public function distinctValues(string $column): array
    {
        if (!in_array($column, ['city', 'sales_name', 'recipient_name'], true)) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT {$column} AS v FROM pengajuan_fees
              WHERE deleted_at IS NULL AND {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} ASC LIMIT 50"
        );
        return array_column($rows, 'v');
    }

    // ------------------------------------------------------------------
    // Tulis
    // ------------------------------------------------------------------

    /** Ganti seluruh baris (dalam transaksi). $items: item_name, qty, unit, price, total, fee (sudah dihitung compute()). */
    public function replaceItems(int $id, array $items): void
    {
        $this->db->query("DELETE FROM pengajuan_fee_items WHERE fee_id = :id", ['id' => $id]);
        $n = 0;
        foreach ($items as $it) {
            $n++;
            $this->db->insert('pengajuan_fee_items', [
                'fee_id'    => $id,
                'line_no'   => $n,
                'item_name' => $it['item_name'],
                'qty'       => $it['qty'],
                'unit'      => $it['unit'] !== '' ? $it['unit'] : null,
                'price'     => $it['price'],
                'total'     => $it['total'],
                'fee'       => $it['fee'],
            ]);
        }
    }

    /** Pindah status ATOMIK (hanya berhasil bila status SAAT INI termasuk $from). */
    public function transition(int $id, array $from, string $to, array $extra = []): bool
    {
        if (!$from || !isset(self::STATUS_LABELS[$to])) {
            return false;
        }
        $ph = [];
        $params = ['id' => $id, 'to' => $to];
        foreach (array_values($from) as $i => $s) {
            $ph[] = ":f{$i}";
            $params["f{$i}"] = $s;
        }
        $set = 'status = :to';
        foreach ($extra as $col => $val) {
            if (!preg_match('/^[a-z_]+$/', (string) $col)) {
                continue;
            }
            $set .= ", {$col} = :x_{$col}";
            $params["x_{$col}"] = $val;
        }
        $stmt = $this->db->query(
            "UPDATE pengajuan_fees SET {$set} WHERE id = :id AND deleted_at IS NULL AND status IN (" . implode(',', $ph) . ")",
            $params
        );
        return $stmt->rowCount() === 1;
    }

    /** Hapus (soft) HANYA yang belum diproses (draft/ditolak). */
    public function softDeleteGuarded(int $id, ?int $userId): bool
    {
        $stmt = $this->db->query(
            "UPDATE pengajuan_fees SET deleted_at = NOW(), deleted_by = :u
              WHERE id = :id AND deleted_at IS NULL AND status IN ('draft','ditolak')",
            ['id' => $id, 'u' => $userId]
        );
        return $stmt->rowCount() === 1;
    }
}
