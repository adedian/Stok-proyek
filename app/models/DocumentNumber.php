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

    public static function romanMonth(int $month): string
    {
        return self::ROMAN_MONTHS[$month] ?? (string) $month;
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

        $this->db->beginTransaction();
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

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '/' . $code . '/' . self::romanMonth($month) . '/' . $year;
    }
}
