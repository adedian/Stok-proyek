<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

/**
 * Reimbursement -- "LAPORAN BIAYA REIMBURSE" (acuan: Reimbursement.xlsx).
 *
 * Header : Nama, Tanggal, Divisi, Kota, Tugas, B S (kasbon / bon sementara, opsional).
 * Baris  : PENGELUARAN = Tanggal, Keterangan (dikelompokkan per Kategori, mis. "Akomodasi"), JUMLAH.
 *          JUMLAH boleh berupa penjumlahan seperti di Excel ("9500+9000+9500+3000+6000").
 * Hitung : TOTAL PENGELUARAN = SUM(JUMLAH) ; TOTAL KASBON - PENGELUARAN = Kasbon - Total Pengeluaran
 *          (kasbon kosong -> sama dengan total pengeluaran, persis seperti Excel).
 *          Nominal yang DITERUSKAN ke Pengajuan Budget = Pengeluaran - Kasbon (kurang bayar); bila <= 0 tidak ada
 *          yang diteruskan (status Disetujui saja).
 *
 * Status: draft -> menunggu_pemeriksaan -> (ditolak | diteruskan | disetujui). ditolak -> draft (revisi, histori tetap).
 * diteruskan/disetujui TERKUNCI (tidak bisa diubah siapa pun) supaya Pengajuan Budget hasil penerusan tidak berubah diam-diam.
 * Perpindahan status memakai UPDATE bersyarat (atomik) sehingga keputusan pemeriksa hanya bisa terjadi SEKALI.
 */
class Reimbursement extends Model
{
    protected string $table = 'reimbursements';
    protected bool $softDelete = true;

    public const DRAFT = 'draft';
    public const MENUNGGU = 'menunggu_pemeriksaan';
    public const DITOLAK = 'ditolak';
    public const DISETUJUI = 'disetujui';
    public const DITERUSKAN = 'diteruskan';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draft',
        self::MENUNGGU => 'Menunggu Pemeriksaan',
        self::DITOLAK => 'Ditolak',
        self::DISETUJUI => 'Disetujui (tanpa kurang bayar)',
        self::DITERUSKAN => 'Disetujui & Diteruskan ke Pengajuan Budget',
    ];

    public const STATUS_BADGES = [
        self::DRAFT => 'secondary',
        self::MENUNGGU => 'warning',
        self::DITOLAK => 'danger',
        self::DISETUJUI => 'success',
        self::DITERUSKAN => 'success',
    ];

    public const MAX_AMOUNT = 999999999999.0;
    public const SOURCE_TYPE = 'reimbursement';

    public static function statusLabel(?string $s): string
    {
        return self::STATUS_LABELS[$s] ?? (string) $s;
    }

    public static function statusBadge(?string $s): string
    {
        return self::STATUS_BADGES[$s] ?? 'secondary';
    }

    // ------------------------------------------------------------------
    // Hitung (sama persis dipakai form, detail, cetak, dan penerusan)
    // ------------------------------------------------------------------

    /**
     * Jumlah gaya Excel: angka atau penjumlahan ("9500+9000+3000"; koma = ribuan, titik = desimal seperti
     * parseCurrencyInput). Mengembalikan [jumlah, ekspresi|null] atau null bila tidak valid / <= 0.
     * Ekspresi hanya disimpan bila memang berisi penjumlahan (>1 suku).
     */
    public static function parseAmount(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '' || !preg_match('/^[0-9][0-9.,]*(\s*\+\s*[0-9][0-9.,]*)*$/', $raw)) {
            return null;
        }
        $terms = array_map('trim', explode('+', $raw));
        $sum = 0.0;
        foreach ($terms as $t) {
            $sum += parseCurrencyInput($t);
        }
        $sum = round($sum, 2);
        if ($sum <= 0 || $sum > self::MAX_AMOUNT) {
            return null;
        }
        return [$sum, count($terms) > 1 ? implode('+', $terms) : null];
    }

    /** @return array{total:float, kasbon:float, net:float} net = pengeluaran - kasbon (>0 = kurang bayar). */
    public static function totals(array $amounts, $kasbon): array
    {
        $total = round(array_sum(array_map('floatval', $amounts)), 2);
        $k = round((float) $kasbon, 2);
        return ['total' => $total, 'kasbon' => $k, 'net' => round($total - $k, 2)];
    }

    /**
     * Nilai baris "TOTAL KASBON - PENGELUARAN" pada Excel. Rumus Excel saat B S kosong = SUM(pengeluaran) (jadi sama dengan
     * Total Pengeluaran); bila B S diisi = Kasbon - Total Pengeluaran (negatif = kurang bayar).
     */
    public static function kasbonMinusExpense(float $total, float $kasbon): float
    {
        return $kasbon > 0 ? round($kasbon - $total, 2) : round($total, 2);
    }

    // ------------------------------------------------------------------
    // Nomor
    // ------------------------------------------------------------------

    public function previewNumber(?string $date = null): string
    {
        return (new DocumentNumber())->preview('reimbursement', 'prefix_reimb', $date, 'RMB.HME');
    }

    /** Dipanggil HANYA dari store(), di dalam transaksi pemanggil. */
    public function nextNumber(?string $date = null): string
    {
        return (new DocumentNumber())->next('reimbursement', 'prefix_reimb', $date, 'RMB.HME');
    }

    // ------------------------------------------------------------------
    // Baca
    // ------------------------------------------------------------------

    public function findWithRelations(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT r.*, cu.full_name AS created_by_name, ru.full_name AS reviewed_by_name,
                    pb.pengajuan_number, pb.status AS pb_status
               FROM reimbursements r
               LEFT JOIN users cu ON cu.id = r.created_by
               LEFT JOIN users ru ON ru.id = r.reviewed_by
               LEFT JOIN pengajuan_budgets pb ON pb.id = r.pengajuan_budget_id AND pb.deleted_at IS NULL
              WHERE r.id = :id AND r.deleted_at IS NULL",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function items(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT i.*, cc.category_name
               FROM reimbursement_items i
               LEFT JOIN cash_categories cc ON cc.id = i.category_id
              WHERE i.reimbursement_id = :id
           ORDER BY i.line_no ASC, i.id ASC",
            ['id' => $id]
        );
    }

    public function history(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT h.*, u.full_name AS user_name
               FROM reimbursement_history h
               LEFT JOIN users u ON u.id = h.user_id
              WHERE h.reimbursement_id = :id
           ORDER BY h.id ASC",
            ['id' => $id]
        );
    }

    public function addHistory(int $id, ?int $userId, string $action, ?string $old, ?string $new, ?string $notes = null): void
    {
        $this->db->insert('reimbursement_history', [
            'reimbursement_id' => $id,
            'user_id'          => $userId,
            'action'           => $action,
            'old_status'       => $old,
            'new_status'       => $new,
            'notes'            => $notes,
        ]);
    }

    /** Pemilik file bukti (untuk kontrol akses FileController). */
    public function reimbursementIdByFilePath(string $relPath): ?int
    {
        $r = $this->db->fetchOne(
            "SELECT i.reimbursement_id FROM reimbursement_items i
               JOIN reimbursements r ON r.id = i.reimbursement_id AND r.deleted_at IS NULL
              WHERE i.proof_file = :p LIMIT 1",
            ['p' => $relPath]
        );
        return $r ? (int) $r['reimbursement_id'] : null;
    }

    /** Pengaju hanya melihat miliknya; pemegang 'view_all' (Nissa, Purwati, Super Admin) melihat semua. */
    public static function canViewRow(array $row): bool
    {
        if (can('reimbursement', 'view_all')) {
            return true;
        }
        return can('reimbursement', 'view') && (int) $row['created_by'] === (int) currentUserId();
    }

    private function listWhere(array $f, ?int $onlyUserId): array
    {
        $sql = " WHERE r.deleted_at IS NULL";
        $p = [];
        if ($onlyUserId !== null) {
            $sql .= " AND r.created_by = :me";
            $p['me'] = $onlyUserId;
        }
        if (($f['number'] ?? '') !== '') {
            $sql .= " AND (r.reimb_number LIKE :num OR r.employee_name LIKE :num2)";
            $p['num'] = '%' . $f['number'] . '%';
            $p['num2'] = '%' . $f['number'] . '%';
        }
        if (($f['status'] ?? '') !== '' && isset(self::STATUS_LABELS[$f['status']])) {
            $sql .= " AND r.status = :st";
            $p['st'] = $f['status'];
        }
        if (($f['date_from'] ?? '') !== '') {
            $sql .= " AND r.reimb_date >= :df";
            $p['df'] = $f['date_from'];
        }
        if (($f['date_to'] ?? '') !== '') {
            $sql .= " AND r.reimb_date <= :dt";
            $p['dt'] = $f['date_to'];
        }
        return [$sql, $p];
    }

    public function countFiltered(array $f, ?int $onlyUserId): int
    {
        [$w, $p] = $this->listWhere($f, $onlyUserId);
        $r = $this->db->fetchOne("SELECT COUNT(*) AS c FROM reimbursements r {$w}", $p);
        return (int) ($r['c'] ?? 0);
    }

    public function listPaginated(array $f, ?int $onlyUserId, int $limit, int $offset): array
    {
        [$w, $p] = $this->listWhere($f, $onlyUserId);
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            "SELECT r.*, cu.full_name AS created_by_name, pb.pengajuan_number,
                    (SELECT COUNT(*) FROM reimbursement_items i WHERE i.reimbursement_id = r.id) AS item_count
               FROM reimbursements r
               LEFT JOIN users cu ON cu.id = r.created_by
               LEFT JOIN pengajuan_budgets pb ON pb.id = r.pengajuan_budget_id AND pb.deleted_at IS NULL
              {$w}
           ORDER BY r.reimb_date DESC, r.id DESC
              LIMIT {$limit} OFFSET {$offset}",
            $p
        );
    }

    /** Nilai yang pernah dipakai (saran isian) untuk Divisi / Kota. */
    public function distinctValues(string $column): array
    {
        if (!in_array($column, ['division', 'city'], true)) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT {$column} AS v FROM reimbursements
              WHERE deleted_at IS NULL AND {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} ASC LIMIT 50"
        );
        return array_column($rows, 'v');
    }

    public function categoryOptions(): array
    {
        return $this->db->fetchAll("SELECT id, category_name FROM cash_categories WHERE deleted_at IS NULL ORDER BY category_name ASC");
    }

    // ------------------------------------------------------------------
    // Tulis
    // ------------------------------------------------------------------

    /** Ganti seluruh baris (dalam transaksi). $items sudah tervalidasi controller (termasuk proof_file final). */
    public function replaceItems(int $id, array $items): void
    {
        $this->db->query("DELETE FROM reimbursement_items WHERE reimbursement_id = :id", ['id' => $id]);
        $n = 0;
        foreach ($items as $it) {
            $n++;
            $this->db->insert('reimbursement_items', [
                'reimbursement_id' => $id,
                'line_no'          => $n,
                'expense_date'     => $it['expense_date'],
                'category_id'      => $it['category_id'] ?: null,
                'description'      => $it['description'],
                'amount'           => $it['amount'],
                'amount_expr'      => $it['amount_expr'],
                'proof_file'       => $it['proof_file'] ?: null,
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
            "UPDATE reimbursements SET {$set} WHERE id = :id AND deleted_at IS NULL AND status IN (" . implode(',', $ph) . ")",
            $params
        );
        return $stmt->rowCount() === 1;
    }

    /** Hapus (soft) HANYA yang belum diproses (draft/ditolak). Yang sudah diperiksa/diteruskan tidak pernah terhapus. */
    public function softDeleteGuarded(int $id, ?int $userId): bool
    {
        $stmt = $this->db->query(
            "UPDATE reimbursements SET deleted_at = NOW(), deleted_by = :u
              WHERE id = :id AND deleted_at IS NULL AND status IN ('draft','ditolak')",
            ['id' => $id, 'u' => $userId]
        );
        return $stmt->rowCount() === 1;
    }
}
