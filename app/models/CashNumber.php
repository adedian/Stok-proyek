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

    /** Tabel valid untuk seedFromExisting() -- whitelist, no_bukti hidup di dua tabel (Kas & Bank). */
    private const SEED_TABLES = ['cash_transactions', 'bank_transactions'];

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
     * $table: tabel tempat no_bukti hidup untuk prefix ini -- 'cash_transactions'
     * (Kas, per-PIC) atau 'bank_transactions' (Bank, prefix per-Mutasi: "BM"/"BK").
     */
    public function preview(string $prefix, string $table = 'cash_transactions'): string
    {
        $prefix = self::normalizePrefix($prefix);
        $table = in_array($table, self::SEED_TABLES, true) ? $table : 'cash_transactions';
        $row = $this->db->fetchOne(
            "SELECT next_number FROM cash_number_counters WHERE prefix = :p",
            ['p' => $prefix]
        );
        $number = $row ? (int) $row['next_number'] : $this->seedFromExisting($prefix, $table);
        return $this->format($prefix, $number);
    }

    /**
     * Generate + reserve nomor berikutnya untuk $prefix. Aman dipanggil di
     * dalam transaction caller (mengikuti pola nesting-safe DocumentNumber).
     * $table lihat preview() di atas -- HANYA dipakai saat counter belum ada
     * (seed pertama kali); sesudahnya counter jadi satu-satunya sumber kebenaran.
     */
    public function next(string $prefix, string $table = 'cash_transactions'): string
    {
        $prefix = self::normalizePrefix($prefix);
        $table = in_array($table, self::SEED_TABLES, true) ? $table : 'cash_transactions';
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
                $number = $this->seedFromExisting($prefix, $table);
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

    // ------------------------------------------------------------------
    // No Bukti BANK format bulanan: "BK 09/0032" -- urut kembali ke 0001 tiap bulan.
    // Bulan/tahun diambil dari TANGGAL TRANSAKSI. Counter per (prefix, "YYYY-MM") di
    // bank_number_counters; Kas (per-PIC, "AD-0001") tetap memakai next()/preview() di atas.
    // ------------------------------------------------------------------

    public function formatMonthly(string $prefix, string $trxDate, int $number): string
    {
        return $prefix . ' ' . substr($trxDate, 5, 2) . '/' . str_pad((string) $number, self::PAD, '0', STR_PAD_LEFT);
    }

    private function monthlyPeriod(string $trxDate): string
    {
        if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])-\d{2}$/', $trxDate, $m)) {
            throw new RuntimeException('Tanggal transaksi tidak valid untuk No Bukti.');
        }
        return $m[1] . '-' . $m[2];
    }

    /** Pratinjau nomor Bank bulan $trxDate TANPA menaikkan counter (label form). */
    public function previewMonthly(string $prefix, string $trxDate): string
    {
        $prefix = self::normalizePrefix($prefix);
        return $this->formatMonthly($prefix, $trxDate, $this->currentNextMonthly($prefix, $trxDate));
    }

    /** Nomor urut berikutnya bulan $trxDate (tanpa menaikkan counter). */
    public function currentNextMonthly(string $prefix, string $trxDate): int
    {
        $prefix = self::normalizePrefix($prefix);
        $period = $this->monthlyPeriod($trxDate);
        $row = $this->db->fetchOne(
            "SELECT next_number FROM bank_number_counters WHERE prefix = :p AND period = :pe",
            ['p' => $prefix, 'pe' => $period]
        );
        return $row ? (int) $row['next_number'] : $this->seedMonthly($prefix, $period);
    }

    /** Generate + reserve nomor Bank bulan $trxDate. Atomic & nesting-safe (pola sama dengan next()). */
    public function nextMonthly(string $prefix, string $trxDate): string
    {
        $prefix = self::normalizePrefix($prefix);
        if (!self::isValidPrefix($prefix)) {
            throw new RuntimeException('Prefix Bank tidak valid.');
        }
        $period = $this->monthlyPeriod($trxDate);

        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $row = $this->db->fetchOne(
                "SELECT * FROM bank_number_counters WHERE prefix = :p AND period = :pe FOR UPDATE",
                ['p' => $prefix, 'pe' => $period]
            );
            if ($row) {
                $number = (int) $row['next_number'];
                $this->db->query(
                    "UPDATE bank_number_counters SET next_number = :n WHERE id = :id",
                    ['n' => $number + 1, 'id' => $row['id']]
                );
            } else {
                $number = $this->seedMonthly($prefix, $period);
                $this->db->insert('bank_number_counters', [
                    'prefix'      => $prefix,
                    'period'      => $period,
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

        return $this->formatMonthly($prefix, $trxDate, $number);
    }

    /**
     * Urut pertama yang aman untuk (prefix, bulan) = angka tertinggi pada bank_transactions.no_bukti
     * berformat "PREFIX MM/NNNN" di tahun itu (termasuk Tempat Sampah) + 1. Default 1.
     */
    private function seedMonthly(string $prefix, string $period): int
    {
        [$year, $month] = explode('-', $period);
        $cut = strlen($prefix) + 5; // posisi 1-based tepat setelah "PREFIX MM/"
        $row = $this->db->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(no_bukti, {$cut}) AS UNSIGNED)) AS mx
               FROM bank_transactions
              WHERE no_bukti_year = :y AND no_bukti REGEXP :re",
            ['y' => (int) $year, 're' => '^' . $prefix . ' ' . $month . '/[0-9]+$']
        );
        return (int) ($row['mx'] ?? 0) + 1;
    }

    /** Nomor berikutnya SAAT INI untuk $prefix, tanpa menaikkan/membuat counter (0 kalau belum pernah dipakai). */
    public function currentNext(string $prefix): int
    {
        $prefix = self::normalizePrefix($prefix);
        $row = $this->db->fetchOne(
            "SELECT next_number FROM cash_number_counters WHERE prefix = :p",
            ['p' => $prefix]
        );
        return $row ? (int) $row['next_number'] : 0;
    }

    /** Prefix ini sudah punya baris counter (pernah dipakai menghasilkan nomor)? */
    public function prefixInUse(string $prefix): bool
    {
        $prefix = self::normalizePrefix($prefix);
        return (bool) $this->db->fetchOne(
            "SELECT id FROM cash_number_counters WHERE prefix = :p",
            ['p' => $prefix]
        );
    }

    /**
     * Ganti nama prefix pada baris counter (dipakai Master Kode > Bank Masuk/
     * Keluar saat admin rename prefix "BM"/"BK") -- next_number TIDAK berubah,
     * jadi sequence lanjut mulus, tidak reset ke 1. Kalau $old belum pernah
     * dipakai (belum ada baris counter), tidak ada apa-apa yang dipindah --
     * counter baru akan otomatis disemai dari data existing saat next()
     * pertama kali dipanggil untuk $new (lihat seedFromExisting()).
     * Return false kalau $new sudah dipakai prefix lain (caller wajib cek
     * dulu ke sumber prefix lain juga -- PIC Kas, kelompok Bank sebelah).
     */
    public function renamePrefix(string $old, string $new): bool
    {
        $old = self::normalizePrefix($old);
        $new = self::normalizePrefix($new);
        if ($old === $new) {
            return true;
        }
        if ($this->prefixInUse($new)) {
            return false;
        }
        $this->db->query(
            "UPDATE cash_number_counters SET prefix = :new WHERE prefix = :old",
            ['new' => $new, 'old' => $old]
        );
        // Counter Bank bulanan ikut (urutan tiap bulan lanjut, tidak reset).
        $this->db->query(
            "UPDATE bank_number_counters SET prefix = :new WHERE prefix = :old",
            ['new' => $new, 'old' => $old]
        );
        return true;
    }

    /**
     * Nomor urut pertama yang aman untuk $prefix = (angka tertinggi pada
     * $table.no_bukti yang cocok "^PREFIX-\d+$") + 1. Default 1. Termasuk
     * baris yang sudah di-soft-delete supaya nomor tidak didaur ulang.
     * $table SELALU dari whitelist SEED_TABLES (divalidasi di preview()/next()
     * sebelum sampai sini) -- aman diinterpolasi ke SQL.
     */
    private function seedFromExisting(string $prefix, string $table = 'cash_transactions'): int
    {
        $cut = strlen($prefix) + 2; // posisi 1-based tepat setelah "PREFIX-"
        $row = $this->db->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(no_bukti, {$cut}) AS UNSIGNED)) AS mx
               FROM {$table}
              WHERE no_bukti REGEXP :re",
            ['re' => '^' . $prefix . '-[0-9]+$']
        );
        $max = (int) ($row['mx'] ?? 0);
        return $max + 1;
    }
}
