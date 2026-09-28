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
            "SELECT next_number FROM document_number_counters WHERE doc_type = :t AND year = :y",
            ['t' => $docType, 'y' => $year]
        );
        $number = $row ? (int) $row['next_number'] : 1;

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
                $this->db->query(
                    "UPDATE document_number_counters SET next_number = :next WHERE id = :id",
                    ['next' => $number + 1, 'id' => $row['id']]
                );
            } else {
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

        return str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '/' . $code . '/' . self::romanMonth($month) . '/' . $year;
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
    public function setNextNumber(string $docType, int $year, int $newNextNumber): void
    {
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
                    "UPDATE document_number_counters SET next_number = :n WHERE id = :id",
                    ['n' => $newNextNumber, 'id' => $row['id']]
                );
            } else {
                $this->db->insert('document_number_counters', [
                    'doc_type'    => $docType,
                    'year'        => $year,
                    'next_number' => $newNextNumber,
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
