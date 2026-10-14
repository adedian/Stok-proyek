<?php
/**
 * No Bukti Bank LAMA -> format bulanan baru (2026-10-14).
 *
 *   BK-0032 (trx 11 Sep 2026)  ->  BK 09/000n   (n = urutan di bulan itu)
 *
 * Aturan:
 *   - Semua baris bank_transactions ber-No Bukti format lama "<PREFIX>-<angka>" (BK, BM, BKL, BML, atau
 *     prefix hasil rename Master Kode) diganti. Baris di Tempat Sampah ikut (nomor tidak boleh bentrok saat dipulihkan).
 *   - Prefix TIDAK berubah; bulan & tahun dari tanggal transaksi (trx_date).
 *   - Urutan dalam satu (prefix, bulan, tahun): trx_date lalu id (kronologis). Kalau sudah ada nomor format baru
 *     di bulan itu (transaksi yang dibuat setelah fitur format bulanan), penomoran ulang MELANJUTKAN setelah
 *     nomor tertinggi -- nomor baru tidak pernah diubah.
 *   - Counter bank_number_counters disesuaikan (tidak pernah diturunkan) supaya transaksi berikutnya lanjut.
 *   - Nomor bukti Bank tidak dirujuk tabel lain (Kas merujuk lewat id); nomor lama hanya tersisa di teks
 *     Riwayat Aktivitas (catatan historis, tidak diubah).
 *   - Satu transaksi database: gagal di tengah = tidak ada yang berubah.
 * Idempotent: setelah dijalankan tak ada lagi nomor format lama, jalan ulang tidak mengubah apa pun.
 * DRY_RUN=1 -> hanya mencetak rencana, lalu rollback.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$dry = getenv('DRY_RUN') === '1';

$pdo->beginTransaction();
try {
    $rows = $pdo->query(
        "SELECT id, no_bukti, trx_date
           FROM bank_transactions
          WHERE no_bukti REGEXP '^[A-Z][A-Z0-9]{1,5}-[0-9]+$'
          ORDER BY trx_date ASC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "Tidak ada No Bukti format lama -> tidak ada yang diubah\n";
        $pdo->commit();
        return;
    }

    $maxSt = $pdo->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(no_bukti, :cut) AS UNSIGNED)), 0)
           FROM bank_transactions
          WHERE no_bukti_year = :y AND no_bukti REGEXP :re"
    );
    $upd = $pdo->prepare("UPDATE bank_transactions SET no_bukti = :nb, no_bukti_year = :y WHERE id = :id");
    $cnt = $pdo->prepare(
        "INSERT INTO bank_number_counters (prefix, period, next_number) VALUES (:p, :pe, :n)
         ON DUPLICATE KEY UPDATE next_number = GREATEST(next_number, VALUES(next_number))"
    );

    $next = [];   // "PREFIX|YYYY|MM" => nomor berikutnya
    $touched = [];
    foreach ($rows as $r) {
        [$prefix] = explode('-', $r['no_bukti'], 2);
        $year  = (int) substr($r['trx_date'], 0, 4);
        $month = substr($r['trx_date'], 5, 2);
        $key = "{$prefix}|{$year}|{$month}";

        if (!isset($next[$key])) {
            $maxSt->execute([
                'cut' => strlen($prefix) + 5,   // posisi 1-based tepat setelah "PREFIX MM/"
                'y'   => $year,
                're'  => '^' . $prefix . ' ' . $month . '/[0-9]+$',
            ]);
            $next[$key] = (int) $maxSt->fetchColumn() + 1;
        }
        $n = $next[$key]++;
        $new = $prefix . ' ' . $month . '/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);

        $upd->execute(['nb' => $new, 'y' => $year, 'id' => (int) $r['id']]);
        $touched[$key] = [$prefix, "{$year}-{$month}", $n + 1];
        echo sprintf("  #%d  %s  (%s)  ->  %s\n", $r['id'], $r['no_bukti'], $r['trx_date'], $new);
    }

    foreach ($touched as [$prefix, $period, $nextNumber]) {
        $cnt->execute(['p' => $prefix, 'pe' => $period, 'n' => $nextNumber]);
    }

    echo count($rows) . " No Bukti diubah ke format bulanan, " . count($touched) . " counter disesuaikan\n";

    if ($dry) {
        $pdo->rollBack();
        echo "[DRY_RUN] di-rollback, tidak ada perubahan tersimpan\n";
    } else {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
