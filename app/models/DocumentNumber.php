<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';

/**
 * DocumentNumber
 * Generator nomor dokumen format "001/INV.HME/VIII/2026" (urut/kode/bulan
 * romawi/tahun) untuk Invoice Keluar & Surat Jalan. Counter ATOMIC per
 * (doc_type, tahun) via SELECT...FOR UPDATE dalam transaction -- pola yang
 * sama dengan CodeConfig::nextCode() (sudah terbukti aman dari race condition
 * saat 2 user submit hampir bersamaan), BUKAN naive MAX(number)+1.
 *
 * Nomor urut reset ke 1 setiap TAHUN BARU (bukan tiap bulan) -- sesuai contoh
 * penomoran yang diminta: 036/.../VIII/2026 -> 037/.../IX/2026 (sequence
 * lanjut lintas bulan, cuma reset saat tahun dokumen berganti).
 *
 * Nomor dibuat SEKALI saat dokumen pertama kali disimpan (bukan setiap kali
 * dicetak) -- caller (SalesInvoice::generateInvoiceNumber() dkk) hanya
 * dipanggil dari store(), lalu hasilnya disimpan permanen ke kolom
 * invoice_number/delivery_number. Cetak ulang membaca nilai yang sudah
 * tersimpan, tidak pernah generate baru.
 */
class DocumentNumber extends Model
{
    protected string $table = 'document_number_counters';

    private const ROMAN_MONTHS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    /**
     * Label Indonesia per doc_type -- dipakai UI "Penomoran Dokumen" (Pengaturan
     * Sistem, Super Admin). Daftar & urutan sama dengan yang dipakai
     * database/migrations/2026_08_27_document_number_migration.php.
     */
    public const DOC_TYPE_LABELS = [
        'purchase_order'      => 'Purchase Order (PO)',
        'goods_receipt'       => 'Penerimaan Barang',
        'stock_opname'        => 'Stok Opname',
        'stock_out'           => 'Pengeluaran Barang',
        'offline_purchase'    => 'Pembelian Offline',
        'sales_invoice'       => 'Invoice Keluar - Project',
        'sales_invoice_lampu' => 'Invoice Keluar - Lampu',
        'delivery_note'       => 'Surat Jalan',
        'collection_receipt'  => 'Tanda Terima',
        'payment_bk'          => 'Pembayaran - Bank',
        'payment_kk'          => 'Pembayaran - Kas Kecil',
        'payment_kkp'         => 'Pembayaran - Kas Project',
    ];

    /** Doc type yang pakai counter bergaya lain (tanpa bulan/tahun pada nomor) -- hanya No. Urut yang bisa diubah. */
    public const EXTRA_LABELS = [
        'request_budget' => 'Request Budget',
        'request_po'     => 'Request PO',
    ];

    /** Key system_settings (kode dokumen) + kode bawaan per doc_type -- untuk pratinjau nomor di UI. */
    public const DOC_TYPE_CODE = [
        'purchase_order'      => ['prefix_po',      'PO.HME'],
        'goods_receipt'       => ['prefix_gr',      'LPB.HME'],
        'stock_opname'        => ['prefix_opn',     'SO.HME'],
        'stock_out'           => ['prefix_sto',     'STO.HME'],
        'offline_purchase'    => ['prefix_off',     'OFF.HME'],
        'sales_invoice'       => ['prefix_sls',     'INV.HME'],
        'sales_invoice_lampu' => ['prefix_fkt',     'FKT.HME'],
        'delivery_note'       => ['prefix_sj',      'SJ.HME'],
        'collection_receipt'  => ['prefix_tt',      'TT.HME'],
        'payment_bk'          => ['prefix_pay_bk',  'BK.HME'],
        'payment_kk'          => ['prefix_pay_kk',  'KK.HME'],
        'payment_kkp'         => ['prefix_pay_kkp', 'KKP.HME'],
    ];

    public static function label(string $docType): string
    {
        return self::DOC_TYPE_LABELS[$docType] ?? self::EXTRA_LABELS[$docType] ?? $docType;
    }

    /** Bulan/tahun override yang valid (atau null = otomatis dari tanggal dokumen). */
    private static function validMonth($m): ?int
    {
        $m = (int) $m;
        return ($m >= 1 && $m <= 12) ? $m : null;
    }

    private static function validYear($y): ?int
    {
        $y = (int) $y;
        return ($y >= 2000 && $y <= 2100) ? $y : null;
    }

    public static function romanMonth(int $month): string
    {
        return self::ROMAN_MONTHS[$month] ?? (string) $month;
    }

    /**
     * Preview nomor berikutnya TANPA menaikkan counter -- khusus untuk ditampilkan
     * di FORM tambah dokumen (label "otomatis"). Nomor asli tetap dibuat sekali di
     * store() lewat next(). Dulu form memanggil next() langsung, jadi tiap kali form
     * dibuka nomor "terbakar"/terlewat walau tidak jadi disimpan.
     */
    public function preview(string $docType, string $codeSettingKey, ?string $date = null, ?string $defaultCode = null): string
    {
        $date = $date ?: date('Y-m-d');
        $ts = strtotime($date) ?: time();
        $year = (int) date('Y', $ts);
        $month = (int) date('n', $ts);
        $code = (new SystemSetting())->get($codeSettingKey, $defaultCode ?? strtoupper($docType));

        $row = $this->db->fetchOne(
            "SELECT next_number, period_month, period_year FROM document_number_counters WHERE doc_type = :t AND year = :y",
            ['t' => $docType, 'y' => $year]
        );
        $number = $row ? (int) $row['next_number'] : 1;
        if ($row) {
            $month = self::validMonth($row['period_month']) ?? $month;
            $year = self::validYear($row['period_year']) ?? $year;
        }

        return str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '/' . $code . '/' . self::romanMonth($month) . '/' . $year;
    }

    /**
     * Generate nomor berikutnya untuk $docType pada tahun dari $date (format
     * Y-m-d, default hari ini). $codeSettingKey adalah key di system_settings
     * yang menyimpan kode dokumen (mis. 'prefix_sls' -> "INV.HME").
     */
    public function next(string $docType, string $codeSettingKey, ?string $date = null, ?string $defaultCode = null): string
    {
        $date = $date ?: date('Y-m-d');
        $ts = strtotime($date) ?: time();
        $year = (int) date('Y', $ts);
        $month = (int) date('n', $ts);
        $code = (new SystemSetting())->get($codeSettingKey, $defaultCode ?? strtoupper($docType));

        // Kalau caller (mis. PurchaseOrderController::store()) SUDAH membuka
        // transaction sendiri, JANGAN buka transaction baru di sini -- PDO tidak
        // mendukung nested transaction & akan throw "There is already an active
        // transaction". Cukup ikut transaction yang sedang berjalan (SELECT ...
        // FOR UPDATE tetap mengunci baris counter dengan benar), commit/rollback
        // diserahkan ke caller. Kalau belum ada transaction, kelola sendiri.
        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $row = $this->db->fetchOne(
                "SELECT * FROM document_number_counters WHERE doc_type = :t AND year = :y FOR UPDATE",
                ['t' => $docType, 'y' => $year]
            );

            if ($row) {
                $number = (int) $row['next_number'];
                // Bulan/Tahun pada nomor bisa di-reset manual (Pengaturan > Penomoran);
                // kunci counter tetap tahun TANGGAL dokumen ($year di query di atas).
                $month = self::validMonth($row['period_month'] ?? null) ?? $month;
                $labelYear = self::validYear($row['period_year'] ?? null) ?? $year;
                $this->db->query(
                    "UPDATE document_number_counters SET next_number = :next WHERE id = :id",
                    ['next' => $number + 1, 'id' => $row['id']]
                );
            } else {
                $labelYear = $year;
                $number = 1;
                $this->db->insert('document_number_counters', [
                    'doc_type'    => $docType,
                    'year'        => $year,
                    'next_number' => 2,
                ]);
            }

            if ($manageTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '/' . $code . '/' . self::romanMonth($month) . '/' . $labelYear;
    }

    /**
     * Semua baris counter yang SUDAH PERNAH dibuat (tiap doc_type baru muncul di
     * sini setelah dokumen pertamanya dibuat -- lihat next()). Dipakai UI
     * "Penomoran Dokumen" (Pengaturan Sistem > Super Admin) untuk menampilkan
     * & mengubah next_number per jenis dokumen.
     */
    public function allCounters(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM document_number_counters ORDER BY doc_type ASC, year DESC"
        );
    }

    public function findCounter(string $docType, int $year): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM document_number_counters WHERE doc_type = :t AND year = :y",
            ['t' => $docType, 'y' => $year]
        );
        return $row ?: null;
    }

    /**
     * Baris untuk tabel "Reset Nomor Urut": semua counter yang sudah ada + SEMUA jenis
     * dokumen bernomor yang belum punya counter tahun ini (ditampilkan mulai dari 1, baris
     * baru baru dibuat kalau memang diubah -- lihat SettingsController::saveCounters()).
     */
    public function overviewRows(int $currentYear): array
    {
        // Counter yatim (modul yang sudah dihapus, mis. sales_invoice_payment) tidak ditampilkan.
        $rows = array_values(array_filter($this->allCounters(), function ($r) {
            return isset(self::DOC_TYPE_LABELS[$r['doc_type']]) || isset(self::EXTRA_LABELS[$r['doc_type']]);
        }));
        $have = [];
        foreach ($rows as $r) {
            if ((int) $r['year'] === $currentYear) {
                $have[$r['doc_type']] = true;
            }
        }
        foreach (array_keys(self::DOC_TYPE_LABELS) as $docType) {
            if (empty($have[$docType])) {
                $rows[] = [
                    'id' => null, 'doc_type' => $docType, 'year' => $currentYear,
                    'next_number' => 1, 'period_month' => null, 'period_year' => null,
                    'virtual' => true,
                ];
            }
        }
        // Urut: ikuti urutan DOC_TYPE_LABELS, lalu extra, tahun terbaru dulu.
        $order = array_flip(array_merge(array_keys(self::DOC_TYPE_LABELS), array_keys(self::EXTRA_LABELS)));
        usort($rows, function ($a, $b) use ($order) {
            $oa = $order[$a['doc_type']] ?? 999;
            $ob = $order[$b['doc_type']] ?? 999;
            return $oa === $ob ? ((int) $b['year'] <=> (int) $a['year']) : ($oa <=> $ob);
        });
        return $rows;
    }

    /**
     * Set ULANG next_number untuk (doc_type, year) tertentu -- dipakai Super Admin
     * lewat UI "Penomoran Dokumen". Kalau baris (doc_type, year) belum pernah ada
     * (dokumen jenis itu belum pernah dibuat tahun itu), baris baru dibuat.
     * Nomor dokumen (mis. po_number) UNIQUE di tabel pemiliknya masing-masing --
     * jadi kalau di-set MUNDUR ke nomor yang sudah pernah dipakai, percobaan buat
     * dokumen baru dengan nomor bentrok itu akan gagal aman di constraint DB,
     * bukan menimpa data lama. Pola SELECT...FOR UPDATE sama seperti next() supaya
     * tidak race dengan next() yang sedang berjalan bersamaan.
     */
    public function setNextNumber(string $docType, int $year, int $newNextNumber, ?int $periodMonth = null, ?int $periodYear = null): void
    {
        $periodMonth = self::validMonth($periodMonth);
        $periodYear = self::validYear($periodYear);
        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $row = $this->db->fetchOne(
                "SELECT id FROM document_number_counters WHERE doc_type = :t AND year = :y FOR UPDATE",
                ['t' => $docType, 'y' => $year]
            );
            if ($row) {
                $this->db->query(
                    "UPDATE document_number_counters SET next_number = :n, period_month = :pm, period_year = :py WHERE id = :id",
                    ['n' => $newNextNumber, 'pm' => $periodMonth, 'py' => $periodYear, 'id' => $row['id']]
                );
            } else {
                $this->db->insert('document_number_counters', [
                    'doc_type'     => $docType,
                    'year'         => $year,
                    'next_number'  => $newNextNumber,
                    'period_month' => $periodMonth,
                    'period_year'  => $periodYear,
                ]);
            }
            if ($manageTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
