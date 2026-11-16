<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';
require_once ROOT_PATH . '/app/models/MasterBank.php';
require_once ROOT_PATH . '/app/models/BankTransaction.php';

/**
 * PengajuanBudget -- "Permintaan Otorisasi": daftar rencana pembayaran lintas rekening sumber
 * (Master Bank). Modul MANDIRI: TIDAK membuat/mengubah transaksi Bank/Kas, saldo hanya DIBACA
 * dari bank_transactions (BankTransaction::balancesByBankAsOf()).
 *
 * Status: draft -> diajukan -> direalisasikan | dibatalkan (draft/diajukan boleh dibatalkan;
 * diajukan boleh dikembalikan ke draft untuk diedit). Aturan approval (Disetujui/Ditolak)
 * SENGAJA tidak ada: belum ada aturan approval untuk dokumen ini di aplikasi.
 * direalisasikan = terkunci (tidak bisa diedit/dibatalkan/dihapus).
 *
 * Perpindahan status & hapus memakai UPDATE bersyarat (WHERE status IN (...)) sehingga validasi
 * status dilakukan atomik di DB, bukan dipercaya dari form.
 */
class PengajuanBudget extends Model
{
    protected string $table = 'pengajuan_budgets';
    protected bool $softDelete = true;

    public const DRAFT = 'draft';
    public const DIAJUKAN = 'diajukan';
    public const DIREALISASIKAN = 'direalisasikan';
    public const DIBATALKAN = 'dibatalkan';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draft',
        self::DIAJUKAN => 'Diajukan',
        self::DIREALISASIKAN => 'Direalisasikan',
        self::DIBATALKAN => 'Dibatalkan',
    ];

    public const STATUS_BADGES = [
        self::DRAFT => 'secondary',
        self::DIAJUKAN => 'primary',
        self::DIREALISASIKAN => 'success',
        self::DIBATALKAN => 'danger',
    ];

    /** Batas nominal per baris (kolom decimal(18,2); batas kewajaran supaya salah ketik ditolak). */
    public const MAX_AMOUNT = 999999999999.0;

    public static function statusLabel(?string $s): string
    {
        return self::STATUS_LABELS[$s] ?? (string) $s;
    }

    public static function statusBadge(?string $s): string
    {
        return self::STATUS_BADGES[$s] ?? 'secondary';
    }

    // ------------------------------------------------------------------
    // Nomor otomatis (001/PB.HME/XI/2026) -- atomik lewat DocumentNumber
    // ------------------------------------------------------------------

    public function previewNumber(?string $date = null): string
    {
        return (new DocumentNumber())->preview('pengajuan_budget', 'prefix_pb', $date, 'PB.HME');
    }

    /** Dipanggil HANYA dari store(), di dalam transaksi pemanggil. */
    public function nextNumber(?string $date = null): string
    {
        return (new DocumentNumber())->next('pengajuan_budget', 'prefix_pb', $date, 'PB.HME');
    }

    // ------------------------------------------------------------------
    // Baca
    // ------------------------------------------------------------------

    public function findWithRelations(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT pb.*, cu.full_name AS created_by_name, ru.full_name AS realized_by_name, xu.full_name AS cancelled_by_name
               FROM pengajuan_budgets pb
               LEFT JOIN users cu ON cu.id = pb.created_by
               LEFT JOIN users ru ON ru.id = pb.realized_by
               LEFT JOIN users xu ON xu.id = pb.cancelled_by
              WHERE pb.id = :id AND pb.deleted_at IS NULL",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function items(int $pengajuanId): array
    {
        return $this->db->fetchAll(
            "SELECT i.*, mb.bank_name, mb.no_rekening, mb.warna, mb.catatan_saldo, mb.is_active AS bank_active,
                    cc.category_name, pr.project_name, mrt.is_active AS tujuan_active
               FROM pengajuan_budget_items i
               JOIN master_banks mb ON mb.id = i.source_bank_id
               LEFT JOIN master_rekening_tujuan mrt ON mrt.id = i.tujuan_id
               LEFT JOIN cash_categories cc ON cc.id = i.category_id
               LEFT JOIN projects pr ON pr.id = i.project_id
              WHERE i.pengajuan_id = :id
           ORDER BY i.line_no ASC, i.id ASC",
            ['id' => $pengajuanId]
        );
    }

    public function history(int $pengajuanId): array
    {
        return $this->db->fetchAll(
            "SELECT h.*, u.full_name AS user_name
               FROM pengajuan_budget_history h
               LEFT JOIN users u ON u.id = h.user_id
              WHERE h.pengajuan_id = :id
           ORDER BY h.id ASC",
            ['id' => $pengajuanId]
        );
    }

    public function addHistory(int $pengajuanId, ?int $userId, string $action, ?string $old, ?string $new, ?string $notes = null): void
    {
        $this->db->insert('pengajuan_budget_history', [
            'pengajuan_id' => $pengajuanId,
            'user_id'      => $userId,
            'action'       => $action,
            'old_status'   => $old,
            'new_status'   => $new,
            'notes'        => $notes,
        ]);
    }

    /** Total nominal seluruh baris sebuah pengajuan. */
    public function totalOf(int $pengajuanId): float
    {
        $r = $this->db->fetchOne("SELECT COALESCE(SUM(amount),0) AS t FROM pengajuan_budget_items WHERE pengajuan_id = :id", ['id' => $pengajuanId]);
        return (float) ($r['t'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Daftar (halaman Form Pengajuan)
    // ------------------------------------------------------------------

    private function listWhere(array $f): array
    {
        $sql = " WHERE pb.deleted_at IS NULL";
        $p = [];
        if (($f['number'] ?? '') !== '') {
            $sql .= " AND pb.pengajuan_number LIKE :num";
            $p['num'] = '%' . $f['number'] . '%';
        }
        if (($f['status'] ?? '') !== '' && isset(self::STATUS_LABELS[$f['status']])) {
            $sql .= " AND pb.status = :st";
            $p['st'] = $f['status'];
        }
        if (($f['date_from'] ?? '') !== '') {
            $sql .= " AND pb.pengajuan_date >= :df";
            $p['df'] = $f['date_from'];
        }
        if (($f['date_to'] ?? '') !== '') {
            $sql .= " AND pb.pengajuan_date <= :dt";
            $p['dt'] = $f['date_to'];
        }
        return [$sql, $p];
    }

    public function countFiltered(array $f): int
    {
        [$w, $p] = $this->listWhere($f);
        $r = $this->db->fetchOne("SELECT COUNT(*) AS c FROM pengajuan_budgets pb {$w}", $p);
        return (int) ($r['c'] ?? 0);
    }

    public function listPaginated(array $f, int $limit, int $offset): array
    {
        [$w, $p] = $this->listWhere($f);
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            "SELECT pb.*, cu.full_name AS created_by_name,
                    (SELECT COUNT(*) FROM pengajuan_budget_items i WHERE i.pengajuan_id = pb.id) AS item_count,
                    (SELECT COALESCE(SUM(i.amount),0) FROM pengajuan_budget_items i WHERE i.pengajuan_id = pb.id) AS total_amount
               FROM pengajuan_budgets pb
               LEFT JOIN users cu ON cu.id = pb.created_by
              {$w}
           ORDER BY pb.pengajuan_date DESC, pb.id DESC
              LIMIT {$limit} OFFSET {$offset}",
            $p
        );
    }

    // ------------------------------------------------------------------
    // Tulis
    // ------------------------------------------------------------------

    /** Ganti seluruh baris (dipanggil dalam transaksi). $items sudah tervalidasi controller. */
    public function replaceItems(int $pengajuanId, array $items): void
    {
        $this->db->query("DELETE FROM pengajuan_budget_items WHERE pengajuan_id = :id", ['id' => $pengajuanId]);
        $n = 0;
        foreach ($items as $it) {
            $n++;
            $this->db->insert('pengajuan_budget_items', [
                'pengajuan_id'    => $pengajuanId,
                'line_no'         => $n,
                'source_bank_id'  => (int) $it['source_bank_id'],
                'jenis_transaksi' => $it['jenis_transaksi'],
                'tujuan'          => $it['tujuan'],
                'tujuan_id'       => $it['tujuan_id'] ?: null,
                'amount'          => $it['amount'],
                'keterangan'      => $it['keterangan'] !== '' ? $it['keterangan'] : null,
                'category_id'     => $it['category_id'] ?: null,
                'project_id'      => $it['project_id'] ?: null,
            ]);
        }
    }

    /**
     * Pindah status ATOMIK: hanya berhasil kalau status SAAT INI termasuk $fromStatuses.
     * @return bool true bila 1 baris berubah
     */
    public function transition(int $id, array $fromStatuses, string $toStatus, array $extra = []): bool
    {
        if (!$fromStatuses || !isset(self::STATUS_LABELS[$toStatus])) {
            return false;
        }
        $ph = [];
        $params = ['id' => $id, 'to' => $toStatus];
        foreach (array_values($fromStatuses) as $i => $s) {
            $ph[] = ":f{$i}";
            $params["f{$i}"] = $s;
        }
        $set = "status = :to";
        foreach ($extra as $col => $val) {
            // kolom hanya dari kode pemanggil (bukan input user) -> aman disisipkan sebagai nama
            if (!preg_match('/^[a-z_]+$/', (string) $col)) {
                continue;
            }
            $set .= ", {$col} = :x_{$col}";
            $params["x_{$col}"] = $val;
        }
        $stmt = $this->db->query(
            "UPDATE pengajuan_budgets SET {$set}
              WHERE id = :id AND deleted_at IS NULL AND status IN (" . implode(',', $ph) . ")",
            $params
        );
        return $stmt->rowCount() === 1;
    }

    /** Hapus (soft) HANYA bila draft/dibatalkan -- direalisasikan/diajukan tidak pernah terhapus. */
    public function softDeleteGuarded(int $id, ?int $userId): bool
    {
        $stmt = $this->db->query(
            "UPDATE pengajuan_budgets SET deleted_at = NOW(), deleted_by = :u
              WHERE id = :id AND deleted_at IS NULL AND status IN ('draft','dibatalkan')",
            ['id' => $id, 'u' => $userId]
        );
        return $stmt->rowCount() === 1;
    }

    // ------------------------------------------------------------------
    // Opsi dropdown
    // ------------------------------------------------------------------

    /** Rekening sumber untuk form: Master Bank aktif + (saat edit) bank yang sudah tersimpan walau kini nonaktif. */
    public function sourceBankOptions(array $includeIds = []): array
    {
        $includeIds = array_values(array_filter(array_map('intval', $includeIds)));
        $sql = "SELECT * FROM master_banks WHERE deleted_at IS NULL AND is_active = 1";
        $params = [];
        if ($includeIds) {
            $in = [];
            foreach ($includeIds as $i => $id) {
                $in[] = ":i{$i}";
                $params["i{$i}"] = $id;
            }
            $sql = "SELECT * FROM master_banks WHERE (deleted_at IS NULL AND is_active = 1) OR id IN (" . implode(',', $in) . ")";
        }
        return $this->db->fetchAll($sql . " ORDER BY bank_name ASC", $params);
    }

    /** Jenis Transaksi = master Metode Pembayaran (payment_methods), satu-satunya master jenis transaksi di aplikasi. */
    public function jenisTransaksiOptions(): array
    {
        return array_column($this->db->fetchAll(
            "SELECT method_name FROM payment_methods WHERE deleted_at IS NULL ORDER BY method_name ASC"
        ), 'method_name');
    }

    /** Kategori = master Kategori Kas/Bank (cash_categories). */
    public function categoryOptions(): array
    {
        return $this->db->fetchAll("SELECT id, category_name FROM cash_categories WHERE deleted_at IS NULL ORDER BY category_name ASC");
    }

    /** Project aktif untuk dropdown Project per baris (opsional). */
    public function projectOptions(): array
    {
        return $this->db->fetchAll("SELECT id, project_name FROM projects WHERE deleted_at IS NULL AND status != 'closed' ORDER BY project_name ASC");
    }

    /** (tidak dipakai lagi) */
    public function recentTujuan(int $limit = 80): array
    {
        $limit = max(1, $limit);
        return array_column($this->db->fetchAll(
            "SELECT i.tujuan FROM pengajuan_budget_items i
               JOIN pengajuan_budgets pb ON pb.id = i.pengajuan_id AND pb.deleted_at IS NULL
           GROUP BY i.tujuan ORDER BY MAX(i.id) DESC LIMIT {$limit}"
        ), 'tujuan');
    }

    /** Pengaju yang pernah membuat pengajuan (filter laporan). */
    public function creatorOptions(): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT u.id, u.full_name FROM pengajuan_budgets pb JOIN users u ON u.id = pb.created_by
              WHERE pb.deleted_at IS NULL ORDER BY u.full_name ASC"
        );
    }

    // ------------------------------------------------------------------
    // Laporan "Permintaan Otorisasi"
    // ------------------------------------------------------------------

    /**
     * Normalisasi filter laporan dari input mentah ($_GET). Aturan tanggal:
     *   tanggal (tertentu) menang atas dari/sampai; tanpa satu pun -> HARI INI.
     * Tanggal tidak valid dibuang (bukan 0000-00-00). Hasil juga memuat 'saldo_date'
     * = tanggal acuan saldo (tanggal tertentu / "sampai" / hari ini).
     */
    public static function normalizeReportFilters(array $in): array
    {
        $valid = fn($v) => (is_string($v) && isValidDateString($v)) ? $v : '';
        $tanggal = $valid($in['tanggal'] ?? '');
        $from = $valid($in['date_from'] ?? '');
        $to = $valid($in['date_to'] ?? '');
        $today = date('Y-m-d');

        if ($tanggal !== '') {
            $from = $to = $tanggal;
        } elseif ($from === '' && $to === '') {
            $from = $to = $today;
            $tanggal = $today;
        } elseif ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $status = is_string($in['status'] ?? null) ? $in['status'] : '';
        if ($status !== '' && !isset(self::STATUS_LABELS[$status])) {
            $status = '';
        }

        return [
            'tanggal'     => $tanggal,
            'date_from'   => $from,
            'date_to'     => $to,
            'bank_id'     => (int) ($in['bank_id'] ?? 0),
            'created_by'  => (int) ($in['created_by'] ?? 0),
            'status'      => $status,
            'category_id' => (int) ($in['category_id'] ?? 0),
            'saldo_date'  => $to !== '' ? $to : $today,
        ];
    }

    /** Baris item laporan (urut: rekening, tanggal, nomor, baris). Default: semua status KECUALI dibatalkan. */
    public function reportItems(array $f): array
    {
        $sql = "SELECT i.*, pb.pengajuan_number, pb.pengajuan_date, pb.status, pb.notes AS pengajuan_notes,
                       cu.full_name AS created_by_name,
                       mb.bank_name, mb.no_rekening, mb.warna, mb.catatan_saldo, mb.jenis AS bank_jenis,
                       cc.category_name, pr.project_name
                  FROM pengajuan_budget_items i
                  JOIN pengajuan_budgets pb ON pb.id = i.pengajuan_id AND pb.deleted_at IS NULL
                  JOIN master_banks mb ON mb.id = i.source_bank_id
                  LEFT JOIN users cu ON cu.id = pb.created_by
                  LEFT JOIN cash_categories cc ON cc.id = i.category_id
                  LEFT JOIN projects pr ON pr.id = i.project_id
                 WHERE 1=1";
        $p = [];
        if (($f['date_from'] ?? '') !== '') {
            $sql .= " AND pb.pengajuan_date >= :df";
            $p['df'] = $f['date_from'];
        }
        if (($f['date_to'] ?? '') !== '') {
            $sql .= " AND pb.pengajuan_date <= :dt";
            $p['dt'] = $f['date_to'];
        }
        if (!empty($f['bank_id'])) {
            $sql .= " AND i.source_bank_id = :bk";
            $p['bk'] = (int) $f['bank_id'];
        }
        if (!empty($f['created_by'])) {
            $sql .= " AND pb.created_by = :cb";
            $p['cb'] = (int) $f['created_by'];
        }
        if (!empty($f['category_id'])) {
            $sql .= " AND i.category_id = :cat";
            $p['cat'] = (int) $f['category_id'];
        }
        if (($f['status'] ?? '') !== '') {
            $sql .= " AND pb.status = :st";
            $p['st'] = $f['status'];
        } else {
            $sql .= " AND pb.status <> 'dibatalkan'";
        }
        $sql .= " ORDER BY mb.bank_name ASC, mb.id ASC, pb.pengajuan_date ASC, pb.id ASC, i.line_no ASC, i.id ASC";
        return $this->db->fetchAll($sql, $p);
    }

    /**
     * Susun seluruh data laporan:
     *   groups[]  : tiap rekening sumber -> bank, color, text_color, rows[] (bernomor 1..n per kelompok), total
     *   grand     : total seluruh kelompok
     *   saldo[]   : per rekening -> saldo aktual (Bank sampai saldo_date), outstanding (draft/diajukan,
     *               dikurangkan), realized (direalisasikan, TIDAK dikurangkan lagi), sisa
     *   saldo_date: tanggal acuan saldo
     *
     * ATURAN SALDO (anti hitung ganda):
     *   Saldo awal  = saldo AKTUAL modul Bank (masuk - keluar sampai saldo_date), dibaca langsung.
     *   Sisa saldo  = saldo awal - total pengajuan yang BELUM direalisasikan (draft + diajukan) di rekening itu.
     *   Pengajuan 'direalisasikan' dianggap sudah dicatat sebagai Bank Keluar -> sudah tercermin di saldo
     *   aktual, jadi TIDAK dikurangkan lagi (hanya ditampilkan sebagai keterangan). Modul ini tidak pernah
     *   menulis ke bank_transactions, jadi saldo aktual tidak berubah karena pengajuan.
     *   Rekening di blok saldo: rekening yang punya pengajuan di laporan + rekening lain yang punya
     *   transaksi Bank (mengikuti filter rekening bila dipilih).
     */
    public function buildReport(array $f): array
    {
        $rows = $this->reportItems($f);
        $groups = [];
        foreach ($rows as $r) {
            $bid = (int) $r['source_bank_id'];
            if (!isset($groups[$bid])) {
                $bankRow = ['id' => $bid, 'bank_name' => $r['bank_name'], 'no_rekening' => $r['no_rekening'], 'warna' => $r['warna'], 'catatan_saldo' => $r['catatan_saldo']];
                $color = MasterBank::reportColor($bankRow);
                $groups[$bid] = [
                    'bank' => $bankRow, 'label' => MasterBank::reportLabel($bankRow),
                    'color' => $color, 'text_color' => MasterBank::textColorOn($color),
                    'rows' => [], 'total' => 0.0, 'outstanding' => 0.0, 'realized' => 0.0,
                ];
            }
            $amount = (float) $r['amount'];
            $r['no'] = count($groups[$bid]['rows']) + 1;
            $groups[$bid]['rows'][] = $r;
            $groups[$bid]['total'] += $amount;
            if ($r['status'] === self::DIREALISASIKAN) {
                $groups[$bid]['realized'] += $amount;
            } else {
                $groups[$bid]['outstanding'] += $amount;
            }
        }

        $grand = 0.0;
        foreach ($groups as $g) {
            $grand += $g['total'];
        }

        // --- Blok saldo ---
        $saldoDate = $f['saldo_date'] ?? date('Y-m-d');
        $balances = (new BankTransaction())->balancesByBankAsOf($saldoDate);
        $bankIds = array_keys($groups);
        if (empty($f['bank_id'])) {
            foreach (array_keys($balances) as $bid) {
                $bankIds[] = $bid;
            }
            // rekening yang pernah bertransaksi tapi saldonya persis 0 pada tanggal itu juga ikut
            foreach ($this->db->fetchAll("SELECT DISTINCT bank_id FROM bank_transactions WHERE deleted_at IS NULL AND trx_date <= :d", ['d' => $saldoDate]) as $r) {
                $bankIds[] = (int) $r['bank_id'];
            }
        } else {
            $bankIds[] = (int) $f['bank_id'];
        }
        $bankIds = array_values(array_unique(array_map('intval', $bankIds)));

        $saldo = [];
        if ($bankIds) {
            $in = [];
            $p = [];
            foreach ($bankIds as $i => $id) {
                $in[] = ":b{$i}";
                $p["b{$i}"] = $id;
            }
            $banks = $this->db->fetchAll(
                "SELECT * FROM master_banks WHERE id IN (" . implode(',', $in) . ") AND (deleted_at IS NULL OR id IN (SELECT DISTINCT bank_id FROM bank_transactions)) ORDER BY bank_name ASC, id ASC",
                $p
            );
            foreach ($banks as $b) {
                $bid = (int) $b['id'];
                $g = $groups[$bid] ?? null;
                $color = MasterBank::reportColor($b);
                $awal = (float) ($balances[$bid] ?? 0);
                $outstanding = $g ? $g['outstanding'] : 0.0;
                $saldo[] = [
                    'bank' => $b, 'label' => MasterBank::reportLabel($b), 'bank_name' => $b['bank_name'],
                    'color' => $color, 'text_color' => MasterBank::textColorOn($color),
                    'note' => (string) ($b['catatan_saldo'] ?? ''),
                    'saldo' => $awal,
                    'outstanding' => $outstanding,
                    'realized' => $g ? $g['realized'] : 0.0,
                    'sisa' => $awal - $outstanding,
                    'has_pengajuan' => $g !== null,
                ];
            }
        }

        return [
            'groups' => array_values($groups),
            'grand' => $grand,
            'saldo' => $saldo,
            'saldo_date' => $saldoDate,
            'item_count' => count($rows),
        ];
    }

    // ------------------------------------------------------------------
    // Persiapan realisasi -> Bank Keluar (BELUM ada UI yang memakainya)
    // ------------------------------------------------------------------

    /**
     * Data siap-tarik sebuah baris untuk membuat transaksi Bank Keluar tanpa input ulang
     * (rekening sumber, penerima, nominal, keterangan, kategori, tanggal). Hanya MEMBACA;
     * kolom pengajuan_budget_items.bank_transaction_id disiapkan untuk menyimpan tautan hasilnya.
     */
    public function bankKeluarPrefill(int $itemId): ?array
    {
        $r = $this->db->fetchOne(
            "SELECT i.id AS item_id, i.pengajuan_id, i.source_bank_id AS bank_id, i.tujuan AS penerima, i.tujuan_id AS rekening_tujuan_id, i.amount,
                    i.keterangan AS uraian, i.category_id, i.jenis_transaksi, pb.pengajuan_number, pb.pengajuan_date AS trx_date
               FROM pengajuan_budget_items i
               JOIN pengajuan_budgets pb ON pb.id = i.pengajuan_id AND pb.deleted_at IS NULL
              WHERE i.id = :id",
            ['id' => $itemId]
        );
        return $r ? $r + ['mutasi' => 'keluar'] : null;
    }
}
