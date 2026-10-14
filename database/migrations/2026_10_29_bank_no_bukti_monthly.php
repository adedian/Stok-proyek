<?php
/**
 * No Bukti Bank format bulanan: "BK 09/0032" (prefix + spasi + bulan 2 digit + "/" + urut 4 digit),
 * urutan kembali ke 0001 tiap bulan (2026-10-14).
 *
 *  - Tabel baru bank_number_counters: counter per (prefix, periode "YYYY-MM"). Tabel lama
 *    cash_number_counters (Kas per-PIC) TIDAK disentuh.
 *  - bank_transactions.no_bukti_year: tahun transaksi saat nomor dibuat (tidak berubah walau tanggal
 *    transaksi diedit). Keunikan No Bukti jadi (no_bukti, no_bukti_year) supaya "BK 09/0001" boleh muncul
 *    lagi di September tahun berikutnya.
 *  - No Bukti lama ("BK-0032", "BML-0003", ...) TIDAK diubah; hanya transaksi baru yang berformat bulanan.
 * Idempotent: tiap langkah dicek dulu lewat information_schema, aman dijalankan ulang.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();

$exists = static function (string $sql, array $params = []) use ($pdo): bool {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int) $st->fetchColumn() > 0;
};

// 1) Counter bulanan.
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `bank_number_counters` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `prefix`      VARCHAR(6) NOT NULL,
        `period`      CHAR(7) NOT NULL,
        `next_number` INT UNSIGNED NOT NULL DEFAULT 1,
        `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_bnc_prefix_period` (`prefix`, `period`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "tabel bank_number_counters siap\n";

// 2) Tahun nomor + backfill dari tanggal transaksi (baris di Tempat Sampah ikut).
if (!$exists(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_transactions' AND COLUMN_NAME = 'no_bukti_year'"
)) {
    $pdo->exec("ALTER TABLE bank_transactions ADD COLUMN no_bukti_year SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER no_bukti");
    $pdo->exec("UPDATE bank_transactions SET no_bukti_year = YEAR(trx_date)");
    echo "kolom no_bukti_year ditambahkan + diisi dari trx_date\n";
}

// 3) Keunikan: (no_bukti) -> (no_bukti, no_bukti_year).
$hasIdx = static fn(string $name): bool => $exists(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_transactions' AND INDEX_NAME = :n",
    ['n' => $name]
);
if (!$hasIdx('uq_bank_trx_no_bukti_year')) {
    $pdo->exec("ALTER TABLE bank_transactions ADD UNIQUE KEY uq_bank_trx_no_bukti_year (no_bukti, no_bukti_year)");
    echo "unique (no_bukti, no_bukti_year) ditambahkan\n";
}
if ($hasIdx('uq_bank_trx_no_bukti')) {
    $pdo->exec("ALTER TABLE bank_transactions DROP INDEX uq_bank_trx_no_bukti");
    echo "unique lama (no_bukti) dihapus\n";
}
echo "selesai\n";
