<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * CashNumber
 * Generator No Bukti Kas OTOMATIS per PIC. Format: "AD-0001" (prefix PIC +
 * "-" + urut 4 digit, tumbuh sendiri kalau lebih dari 9999).
 *
 * Counter ATOMIC per prefix via SELECT ... FOR UPDATE dalam transaction --
 * pola identik DocumentNumber::next() / CodeConfig::nextCode() (terbukti aman
 * saat 2 transaksi disimpan hampir bersamaan). BUKAN naive MAX(no_bukti)+1.
 *
 * Nomor dibuat SEKALI di CashController::store() (di dalam transaction yang
 * sama dengan INSERT header) lalu disimpan permanen ke cash_transactions.
 * no_bukti. edit() TIDAK pernah membuat ulang nomor. preview() hanya untuk
 * label di form -- tidak menaikkan counter.
 *
 * UNIQUE index cash_transactions belum ada di no_bukti (data lama bebas
 * format), jadi lapisan aman: (a) FOR UPDATE di sini, (b) cek
 * CashTransaction::noBuktiExists() di controller sebelum commit.
 */
class CashNumber extends Model
{
    protected string $table = 'cash_number_counters';

    private const PAD = 4;

    /** Normalisasi prefix: huruf/angka, uppercase, 2-6 char, harus mulai huruf. */
    public static function normalizePrefix(string $raw): string
    {
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($raw)));
        return substr($p, 0, 6);
    }

    public static function isValidPrefix(string $raw): bool
    {
        $p = self::normalizePrefix($raw);
        return (bool) preg_match('/^[A-Z][A-Z0-9]{1,5}$/', $p);
    }

    public function format(string $prefix, int $number): string
    {
        return $prefix . '-' . str_pad((string) $number, self::PAD, '0', STR_PAD_LEFT);
    }

    /**
     * Nomor berikutnya untuk $prefix TANPA menaikkan counter (label form).
     * Kalau counter belum ada, hitung dari data existing supaya preview realistis.
     */
    public function preview(string $prefix): string
    {
        $prefix = self::normalizePrefix($prefix);
        $row = $this->db->fetchOne(
            "SELECT next_number FROM cash_number_counters WHERE prefix = :p",
            ['p' => $prefix]
        );
        $number = $row ? (int) $row['next_number'] : $this->seedFromExisting($prefix);
        return $this->format($prefix, $number);
    }

    /**
     * Generate + reserve nomor berikutnya untuk $prefix. Aman dipanggil di
     * dalam transaction caller (mengikuti pola nesting-safe DocumentNumber).
     */
    public function next(string $prefix): string
    {
        $prefix = self::normalizePrefix($prefix);
        if (!self::isValidPrefix($prefix)) {
            throw new RuntimeException('Prefix Kas tidak valid.');
        }

        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $row = $this->db->fetchOne(
                "SELECT * FROM cash_number_counters WHERE prefix = :p FOR UPDATE",
                ['p' => $prefix]
            );

            if ($row) {
                $number = (int) $row['next_number'];
                $this->db->query(
                    "UPDATE cash_number_counters SET next_number = :n WHERE id = :id",
                    ['n' => $number + 1, 'id' => $row['id']]
                );
            } else {
                // Baris counter belum ada -> semai dari No Bukti existing supaya
                // tidak bentrok dengan data lama yang mungkin sudah "PREFIX-NNNN".
                $number = $this->seedFromExisting($prefix);
                $this->db->insert('cash_number_counters', [
                    'prefix'      => $prefix,
                    'next_number' => $number + 1,
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

        return $this->format($prefix, $number);
    }

    /**
     * Nomor urut pertama yang aman untuk $prefix = (angka tertinggi pada
     * cash_transactions.no_bukti yang cocok "^PREFIX-\d+$") + 1. Default 1.
     * Termasuk baris yang sudah di-soft-delete supaya nomor tidak didaur ulang.
     */
    private function seedFromExisting(string $prefix): int
    {
        $cut = strlen($prefix) + 2; // posisi 1-based tepat setelah "PREFIX-"
        $row = $this->db->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(no_bukti, {$cut}) AS UNSIGNED)) AS mx
               FROM cash_transactions
              WHERE no_bukti REGEXP :re",
            ['re' => '^' . $prefix . '-[0-9]+$']
        );
        $max = (int) ($row['mx'] ?? 0);
        return $max + 1;
    }
}
