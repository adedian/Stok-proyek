<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';
require_once ROOT_PATH . '/app/helpers/money_helper.php';

class Payment extends Model
{
    protected string $table = 'payments';
    protected bool $softDelete = true;

    public array $statusLabels = [
        'pending'  => 'Belum Dibayar',
        'partial'  => 'Dibayar Sebagian',
        'paid'     => 'Lunas',
        'overpaid' => 'Kelebihan Bayar',
    ];

    public array $statusBadgeClass = [
        'pending'  => 'secondary',
        'partial'  => 'warning',
        'paid'     => 'success',
        'overpaid' => 'danger',
    ];

    /** Jenis pembayaran: Termin (nomor otomatis per PO) atau Pelunasan (sisa tagihan penuh). */
    public const TYPE_TERMIN    = 'termin';
    public const TYPE_PELUNASAN = 'pelunasan';

    public array $typeLabels = [
        self::TYPE_TERMIN    => 'Termin',
        self::TYPE_PELUNASAN => 'Pelunasan',
    ];

    /**
     * Label jenis+nomor satu baris pembayaran: "Termin 2" / "Pelunasan".
     * Pembayaran lama (sebelum fitur ini) otomatis berjenis termin dengan nomor yang dulu diketik manual.
     */
    public function typeNumberLabel(array $row): string
    {
        if (($row['payment_type'] ?? self::TYPE_TERMIN) === self::TYPE_PELUNASAN) {
            return 'Pelunasan';
        }
        return 'Termin ' . (int) ($row['termin'] ?? 1);
    }

    /**
     * Sumber dana pembayaran (Revisi 7 #16-21) -- label + kode BK/KK/KKP dan
     * key setting prefix nomor (system_settings, tab Pengaturan > Penomoran)
     * masing-masing, supaya sequence-nya TERPISAH per sumber dana (bukan
     * nomor global) sesuai permintaan.
     */
    public array $fundingSourceLabels = [
        'bank'         => 'Bank',
        'kas_kecil'    => 'Kas Kecil',
        'kas_project'  => 'Kas Project',
    ];

    public array $fundingSourceCodes = [
        'bank'        => 'BK',
        'kas_kecil'   => 'KK',
        'kas_project' => 'KKP',
    ];

    private const FUNDING_SOURCE_DOC_TYPE = [
        'bank'        => 'payment_bk',
        'kas_kecil'   => 'payment_kk',
        'kas_project' => 'payment_kkp',
    ];

    private const FUNDING_SOURCE_SETTING_KEY = [
        'bank'        => 'prefix_pay_bk',
        'kas_kecil'   => 'prefix_pay_kk',
        'kas_project' => 'prefix_pay_kkp',
    ];

    /**
     * Generate nomor pembayaran otomatis, format "001/BK.HME/VIII/2026" dkk
     * -- sequence ATOMIC & TERPISAH per sumber dana (lihat DocumentNumber::next()),
     * jadi Bank/Kas Kecil/Kas Project masing-masing mulai dari 001, bukan
     * berbagi satu nomor urut global.
     */
    public function generatePaymentNumber(string $fundingSource, ?string $date = null): string
    {
        $docType = self::FUNDING_SOURCE_DOC_TYPE[$fundingSource] ?? self::FUNDING_SOURCE_DOC_TYPE['bank'];
        $settingKey = self::FUNDING_SOURCE_SETTING_KEY[$fundingSource] ?? self::FUNDING_SOURCE_SETTING_KEY['bank'];
        $default = $this->fundingSourceCodes[$fundingSource] ?? 'BK';

        return (new DocumentNumber())->next($docType, $settingKey, $date, $default . '.HME');
    }

    /**
     * Total yang sudah dibayar untuk satu PO (tidak termasuk yang soft-deleted/dibatalkan).
     * Mengembalikan STRING desimal persis dari MySQL ("2029028.00") -- bukan float -- supaya
     * perbandingan Lunas/Sebagian dilakukan per sen lewat Money. $excludePaymentId = abaikan
     * satu pembayaran (dipakai saat Edit).
     */
    public function totalPaidByPo(int $poId, ?int $excludePaymentId = null): string
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) AS total FROM payments
                WHERE purchase_order_id = :po_id AND deleted_at IS NULL";
        $params = ['po_id' => $poId];
        if ($excludePaymentId) {
            $sql .= " AND id <> :ex";
            $params['ex'] = $excludePaymentId;
        }
        $result = $this->db->fetchOne($sql, $params);
        return Money::fromCents(Money::cents($result['total'] ?? 0));
    }

    /** Total pembayaran PO dalam RUPIAH (SUM amount_idr; tiap pembayaran memakai kursnya sendiri). */
    public function totalPaidIdrByPo(int $poId): float
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount_idr), 0) AS total FROM payments
             WHERE purchase_order_id = :po_id AND deleted_at IS NULL",
            ['po_id' => $poId]
        );
        return (float) ($row['total'] ?? 0);
    }

    /** Jumlah pembayaran aktif (belum dihapus) pada PO -- dipakai mengunci perubahan mata uang PO. */
    public function countByPo(int $poId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM payments WHERE purchase_order_id = :po_id AND deleted_at IS NULL",
            ['po_id' => $poId]
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Total dibayar per PO untuk sekumpulan PO sekaligus (1 query, bukan N+1) --
     * dipakai untuk badge "Status Bayar" di halaman List PO. Key = purchase_order_id,
     * nilai = string desimal (lihat totalPaidByPo()).
     */
    public function paidTotalsByPoIds(array $poIds): array
    {
        if (empty($poIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($poIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT purchase_order_id, COALESCE(SUM(amount), 0) AS total_paid
             FROM payments WHERE deleted_at IS NULL AND purchase_order_id IN ({$placeholders})
             GROUP BY purchase_order_id",
            array_values($poIds)
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['purchase_order_id']] = Money::fromCents(Money::cents($r['total_paid']));
        }
        return $out;
    }

    /**
     * Status pembayaran PO dari perbandingan nominal AKTUAL (Money::paymentStatus) --
     * SATU-SATUNYA tempat status dihitung. Persentase tampilan tidak pernah dipakai di sini.
     */
    public function resolveStatus($totalAmount, $totalPaid): string
    {
        return Money::paymentStatus($totalPaid, $totalAmount);
    }

    /**
     * Ringkasan progres satu PO (satu sumber untuk list/detail/form/rekap/dashboard):
     * total, dibayar, sisa, kelebihan, status, dan persentase presisi penuh.
     * Semua nominal = string desimal; percentage* lihat Money::percent().
     */
    public function progressFor($totalAmount, $totalPaid): array
    {
        $pct = Money::percent($totalPaid, $totalAmount);
        $paidC = Money::cents($totalPaid);
        $totalC = Money::cents($totalAmount);
        return [
            'total_amount'    => Money::fromCents($totalC),
            'total_paid'      => Money::fromCents($paidC),
            'remaining'       => Money::remaining($totalAmount, $totalPaid),
            'overpaid_amount' => Money::fromCents(max(0, $paidC - $totalC)),
            'status'          => Money::paymentStatus($totalPaid, $totalAmount),
            'percentage'      => $pct['value'],   // string titik, mis. "99.9999926"
            'percentage_text' => $pct['text'],    // string Indonesia, mis. "99,9999926"
            'percentage_bar'  => $pct['bar'],     // 0..100 (dipotong) khusus lebar progress bar
        ];
    }

    /**
     * Kunci baris PO (SELECT ... FOR UPDATE) -- WAJIB di dalam transaksi. Dua pengguna yang
     * mencatat pembayaran PO yang sama diserialisasi di sini: yang kedua menunggu, lalu
     * membaca sisa tagihan & nomor termin TERBARU. null = PO tak ada/sudah dihapus.
     */
    public function lockPo(int $poId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, po_number, po_category, status, currency, total_amount, termin_counter
               FROM purchase_orders WHERE id = :id AND deleted_at IS NULL FOR UPDATE",
            ['id' => $poId]
        );
        return $row ?: null;
    }

    /**
     * Terbitkan nomor termin berikutnya untuk PO. Counter di purchase_orders hanya NAIK (tak pernah
     * turun) -- nomor dari pembayaran yang sudah dihapus/dibatalkan TIDAK dipakai ulang. Panggil
     * hanya setelah lockPo() dalam transaksi yang sama.
     */
    public function nextTerminNumber(int $poId): int
    {
        $this->db->query(
            "UPDATE purchase_orders SET termin_counter = termin_counter + 1 WHERE id = :id",
            ['id' => $poId]
        );
        $row = $this->db->fetchOne("SELECT termin_counter FROM purchase_orders WHERE id = :id", ['id' => $poId]);
        return (int) ($row['termin_counter'] ?? 1);
    }

    /** Nomor termin yang AKAN dipakai pembayaran termin berikutnya (hanya informasi di form). */
    public function previewNextTermin(int $poId): int
    {
        $row = $this->db->fetchOne("SELECT termin_counter FROM purchase_orders WHERE id = :id", ['id' => $poId]);
        return (int) ($row['termin_counter'] ?? 0) + 1;
    }

    /**
     * Samakan kolom payments.status semua pembayaran AKTIF suatu PO dengan status PO terkini
     * (status tersimpan = snapshot, jadi baris lama bisa basi setelah ada pembayaran lain).
     * Nominal tidak disentuh.
     */
    public function syncStatusForPo(int $poId, $totalAmount): string
    {
        $status = Money::paymentStatus($this->totalPaidByPo($poId), $totalAmount);
        $this->db->query(
            "UPDATE payments SET status = :s WHERE purchase_order_id = :id AND deleted_at IS NULL AND status <> :s2",
            ['s' => $status, 'id' => $poId, 's2' => $status]
        );
        return $status;
    }

    /**
     * Riwayat pembayaran SATU PO, TERMASUK yang sudah dibatalkan (soft-deleted) -- tidak ada yang
     * hilang dari histori. Pembayaran batal ditandai is_cancelled dan tidak ikut total aktif.
     */
    public function historyByPo(int $poId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT pay.*, pm.method_name, u.full_name AS created_by_name, d.full_name AS deleted_by_name
               FROM payments pay
               LEFT JOIN payment_methods pm ON pm.id = pay.payment_method_id
               LEFT JOIN users u ON u.id = pay.created_by
               LEFT JOIN users d ON d.id = pay.deleted_by
              WHERE pay.purchase_order_id = :id
              ORDER BY pay.created_at ASC, pay.id ASC",
            ['id' => $poId]
        );
        foreach ($rows as &$r) {
            $r['is_cancelled'] = !empty($r['deleted_at']);
            $r['type_number_label'] = $this->typeNumberLabel($r);
        }
        return $rows;
    }

    /**
     * List pembayaran + relasi PO/supplier, dengan filter opsional.
     * Setiap baris juga dilengkapi progres KUMULATIF PO terkait. `status` pada baris = status PO
     * TERKINI dari nominal aktual (bukan snapshot lama yang bisa basi); filter status memakai nilai itu.
     */
    public function listWithRelations(array $filters = []): array
    {
        // pay.currency/pay.kurs = mata uang & kurs PEMBAYARAN (snapshot); po.currency dipisah
        // sebagai po_currency supaya tidak menimpa kolom 'currency' milik pembayaran.
        $sql = "SELECT pay.*, po.po_number, po.po_category, po.currency AS po_currency, po.total_amount, po.project_id, po.is_non_project, po.pembuat_po, s.supplier_name,
                       IF(po.is_non_project = 1, 'Non Project', p.project_name) AS project_name,
                       pm.method_name,
                       (SELECT COALESCE(SUM(p2.amount), 0) FROM payments p2
                        WHERE p2.purchase_order_id = po.id AND p2.deleted_at IS NULL) AS po_total_paid
                FROM payments pay
                JOIN purchase_orders po ON po.id = pay.purchase_order_id
                JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN projects p ON p.id = po.project_id
                LEFT JOIN payment_methods pm ON pm.id = pay.payment_method_id
                WHERE pay.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['po_id'])) {
            $sql .= " AND pay.purchase_order_id = :po_id";
            $params['po_id'] = $filters['po_id'];
        }
        if (($filters['project_id'] ?? '') === 'non_project') {
            $sql .= " AND po.is_non_project = 1";
        } elseif (!empty($filters['project_id'])) {
            $sql .= " AND po.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['s.supplier_name'],
                ['pay.payment_number', 'po.po_number'],
                'paykw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND pay.payment_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND pay.payment_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY pay.created_at DESC";

        $rows = $this->db->fetchAll($sql, $params);
        $wantStatus = (string) ($filters['status'] ?? '');
        $out = [];
        foreach ($rows as $row) {
            $prog = $this->progressFor($row['total_amount'], $row['po_total_paid']);
            $row['stored_status'] = $row['status'];
            $row['status'] = $prog['status'];
            if ($wantStatus !== '' && $row['status'] !== $wantStatus) {
                continue;
            }
            $row['po_total_paid'] = $prog['total_paid'];
            $row['po_payment_percentage'] = $prog['percentage'];
            $row['po_payment_percentage_text'] = $prog['percentage_text'];
            $row['po_payment_percentage_bar'] = $prog['percentage_bar'];
            $row['type_number_label'] = $this->typeNumberLabel($row);
            $out[] = $row;
        }

        return $out;
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT pay.*, po.po_number, po.po_category, po.currency AS po_currency, po.total_amount, po.pembuat_po, s.supplier_name, pm.method_name
                FROM payments pay
                JOIN purchase_orders po ON po.id = pay.purchase_order_id
                JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN payment_methods pm ON pm.id = pay.payment_method_id
                WHERE pay.id = :id AND pay.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Ringkasan status pembayaran semua PO (untuk kartu di halaman pembayaran)
     * PO yang belum punya pembayaran sama sekali (status draft misalnya) tetap dihitung "belum dibayar".
     */
    public function poPaymentSummary(): array
    {
        $sql = "SELECT po.id, po.po_number, po.po_category, po.currency, po.total_amount, po.status AS po_status, po.pembuat_po,
                       s.supplier_name,
                       COALESCE(SUM(pay.amount), 0) AS total_paid,
                       COALESCE(SUM(pay.amount_idr), 0) AS total_paid_idr
                FROM purchase_orders po
                JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN payments pay ON pay.purchase_order_id = po.id AND pay.deleted_at IS NULL
                WHERE po.deleted_at IS NULL
                GROUP BY po.id, po.po_number, po.po_category, po.currency, po.total_amount, po.status, po.pembuat_po, s.supplier_name
                ORDER BY po.created_at DESC";

        $rows = $this->db->fetchAll($sql);

        foreach ($rows as &$row) {
            $prog = $this->progressFor($row['total_amount'], $row['total_paid']);
            $row['total_paid'] = $prog['total_paid'];
            $row['remaining'] = $prog['remaining'];
            $row['overpaid_amount'] = $prog['overpaid_amount'];
            $row['payment_status'] = $prog['status'];
            $row['percentage'] = $prog['percentage'];
            $row['percentage_text'] = $prog['percentage_text'];
            $row['percentage_bar'] = $prog['percentage_bar'];
        }

        return $rows;
    }

    /**
     * Info pembayaran satu PO: total PO, sudah dibayar, persentase (PRESISI PENUH, tak pernah
     * dibulatkan jadi 100 selama belum persis lunas), sisa, kelebihan, dan status. Dipakai di
     * kartu "Info Pembayaran" Detail PO dan form Tambah/Edit Pembayaran. Selalu dihitung dari data
     * payments/purchase_orders yang sebenarnya -- tidak pernah disimpan manual.
     */
    public function poPaymentInfo(int $poId, $totalAmount): array
    {
        $info = $this->progressFor($totalAmount, $this->totalPaidByPo($poId));
        // Setara Rupiah dari seluruh pembayaran PO ini (tiap pembayaran dgn kursnya sendiri);
        // HANYA informasi -- sisa/status tetap dihitung dalam mata uang PO.
        $info['total_paid_idr'] = $this->totalPaidIdrByPo($poId);
        return $info;
    }

    /**
     * Progres pembayaran keseluruhan (semua PO yang bisa dibayar, bukan draft/cancelled) --
     * dipakai untuk kartu ringkasan di Dashboard.
     */
    public function overallProgress(): array
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(po.total_amount), 0) AS total_amount,
                    COALESCE(SUM(pay.amount), 0) AS total_paid
             FROM purchase_orders po
             LEFT JOIN payments pay ON pay.purchase_order_id = po.id AND pay.deleted_at IS NULL
             WHERE po.deleted_at IS NULL AND po.status NOT IN ('draft', 'cancelled')"
        );
        return $this->progressFor($row['total_amount'] ?? 0, $row['total_paid'] ?? 0);
    }

    /**
     * Hitung jumlah PO per kategori status pembayaran (untuk kartu summary)
     */
    public function countStatusSummary(): array
    {
        $summary = $this->poPaymentSummary();
        $counts = ['pending' => 0, 'partial' => 0, 'paid' => 0, 'overpaid' => 0];

        foreach ($summary as $row) {
            $counts[$row['payment_status']]++;
        }

        return $counts;
    }
}
