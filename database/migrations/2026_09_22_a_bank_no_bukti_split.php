<?php
/**
 * Rapikan No Bukti Bank LAMA (era sebelum split prefix per-Mutasi, commit
 * "Pecah No Bukti Bank per Mutasi"): dulu SEMUA transaksi Bank -- apa pun
 * Mutasi-nya -- dinomori dengan satu prefix "BK" tunggal (BK-0001, BK-0004,
 * BK-0005, dst tercampur masuk/keluar). Sekarang barisnya diselaraskan:
 *   - Mutasi Masuk  -> BM-0001, BM-0002, ... (prefix baru, sequence sendiri)
 *   - Mutasi Keluar -> BK-0001, BK-0002, ... (prefix sama, sequence dirapikan
 *     mulai dari 0001, bukan melompat dari sisa nomor lama yang tadinya
 *     bercampur dengan baris Masuk)
 * Urut berdasarkan id (= urutan transaksi dibuat).
 *
 * HANYA menyasar baris yang MASIH berpola lama "^BK-[0-9]+$". Transaksi yang
 * sudah dibuat SETELAH kode split prefix di-deploy (otomatis BM-xxxx atau
 * BK-xxxx yang sudah rapi lewat CashNumber/CodeConfig) TIDAK disentuh sama
 * sekali -- baik karena polanya sudah "BM-..." (tidak match regex ini) maupun
 * karena nomor barunya PASTI dicek dulu belum dipakai di bank_transactions
 * (skip maju kalau bentrok, pola sama dengan guard di BankController::store())
 * supaya tidak pernah menimpa/duplikat nomor transaksi yang sudah berjalan
 * kalau migrasi ini baru sempat dijalankan belakangan setelah kodenya aktif.
 *
 * cash_number_counters (BM/BK) disinkronkan ke angka TERBESAR yang benar-benar
 * terpakai + 1 -- diambil yang LEBIH BESAR antara nilai sekarang vs hasil
 * migrasi (tidak pernah dimundurkan), supaya transaksi baru sesudah migrasi
 * tetap lanjut, tidak reset atau bentrok.
 *
 * Idempotent: sekali baris lama sudah dirapikan, tidak ada lagi yang match
 * regex sasaran (kecuali baris Keluar yang kebetulan sudah pas di posisi
 * targetnya sendiri -- terdeteksi & di-skip, bukan ditulis ulang) sehingga
 * aman dijalankan ulang / di-baseline di environment mana pun.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();

$pdo->beginTransaction();
try {
    $rows = $pdo->query(
        "SELECT id, no_bukti, mutasi FROM bank_transactions
          WHERE no_bukti REGEXP '^BK-[0-9]+$'
       ORDER BY id ASC
            FOR UPDATE"
    )->fetchAll(PDO::FETCH_ASSOC);

    $existsStmt = $pdo->prepare("SELECT id FROM bank_transactions WHERE no_bukti = :nb");
    $updStmt = $pdo->prepare("UPDATE bank_transactions SET no_bukti = :nb WHERE id = :id");

    $seq = ['masuk' => 0, 'keluar' => 0];
    $changed = 0;

    foreach ($rows as $r) {
        $mutasi = $r['mutasi'] === 'masuk' ? 'masuk' : 'keluar';
        $prefix = $mutasi === 'masuk' ? 'BM' : 'BK';

        do {
            $seq[$mutasi]++;
            $candidate = $prefix . '-' . str_pad((string) $seq[$mutasi], 4, '0', STR_PAD_LEFT);
            if ($candidate === $r['no_bukti']) {
                $taken = false; // baris keluar yang kebetulan sudah pas di posisinya sendiri
                break;
            }
            $existsStmt->execute(['nb' => $candidate]);
            $taken = (bool) $existsStmt->fetch();
        } while ($taken);

        if ($candidate !== $r['no_bukti']) {
            $updStmt->execute(['nb' => $candidate, 'id' => $r['id']]);
            $changed++;
            echo "  id={$r['id']} ({$mutasi}): {$r['no_bukti']} -> {$candidate}\n";
        }
    }

    foreach (['BM', 'BK'] as $prefix) {
        $maxRow = $pdo->prepare(
            "SELECT MAX(CAST(SUBSTRING(no_bukti, LENGTH(:p) + 2) AS UNSIGNED)) AS mx
               FROM bank_transactions WHERE no_bukti REGEXP :re"
        );
        $maxRow->execute(['p' => $prefix, 're' => '^' . $prefix . '-[0-9]+$']);
        $maxNo = (int) ($maxRow->fetch()['mx'] ?? 0);
        $nextNumber = $maxNo + 1;

        $cur = $pdo->prepare("SELECT next_number FROM cash_number_counters WHERE prefix = :p");
        $cur->execute(['p' => $prefix]);
        $curRow = $cur->fetch();
        if ($curRow) {
            if ((int) $curRow['next_number'] < $nextNumber) {
                $pdo->prepare("UPDATE cash_number_counters SET next_number = :n WHERE prefix = :p")
                    ->execute(['n' => $nextNumber, 'p' => $prefix]);
            }
        } else {
            $pdo->prepare("INSERT INTO cash_number_counters (prefix, next_number) VALUES (:p, :n)")
                ->execute(['p' => $prefix, 'n' => $nextNumber]);
        }
    }

    $pdo->commit();
    echo "Selesai. {$changed} No Bukti Bank lama dirapikan (BM/BK per Mutasi).\n";
} catch (Throwable $ex) {
    $pdo->rollBack();
    throw $ex;
}
