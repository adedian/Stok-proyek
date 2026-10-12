<?php
/**
 * No Bukti Bank HR dirapatkan ulang mulai dari 0001 (2026-10-12).
 *
 * Setelah transaksi Bank Loan dipindah ke BML/BKL (2026_10_19), nomor BK/BM yang tersisa
 * hanyalah milik bank HR dan berlubang di depan (mis. BK-0003, BK-0004 sementara BK-0001/0002
 * sudah kosong). Migrasi ini menomori ulang SELURUH baris yang masih berformat "<BM|BK>-<angka>"
 * (prefix dasar dari Master Kode bank_masuk/bank_keluar) berurutan 0001..n menurut urutan input (id),
 * per prefix, lalu mengatur counter = n + 1.
 *
 *   BK-0003 -> BK-0001, BK-0004 -> BK-0002 (urutan relatif TIDAK berubah)
 *
 * Pengaman:
 *   - Baris Bank Loan yang (entah kenapa) masih berformat BM/BK membatalkan perapatan prefix itu
 *     (tidak diubah sama sekali) supaya tidak mungkin bentrok -- dilaporkan di output.
 *   - Dua tahap (nama sementara) di dalam satu transaksi, jadi UNIQUE no_bukti tidak pernah bentrok
 *     di tengah jalan. Baris di Tempat Sampah ikut dinomori ulang (nomornya tidak boleh bentrok saat dipulihkan).
 *   - Prefix tanpa baris sama sekali dibiarkan (counter tidak disentuh).
 *   - Nomor bukti Bank tidak dirujuk tabel lain (Kas merujuk lewat id), jadi aman.
 * Idempotent: dijalankan ulang tidak mengubah apa pun (sudah rapat).
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();

$basePrefix = static function (string $entityType, string $default) use ($pdo): string {
    $st = $pdo->prepare("SELECT prefix FROM code_configs WHERE entity_type = :t ORDER BY id ASC LIMIT 1");
    $st->execute(['t' => $entityType]);
    $p = $st->fetchColumn();
    return ($p !== false && $p !== '') ? (string) $p : $default;
};

$pdo->beginTransaction();
try {
    foreach ([$basePrefix('bank_masuk', 'BM'), $basePrefix('bank_keluar', 'BK')] as $prefix) {
        $sel = $pdo->prepare(
            "SELECT bt.id, bt.no_bukti, mb.jenis
               FROM bank_transactions bt
               JOIN master_banks mb ON mb.id = bt.bank_id
              WHERE bt.no_bukti REGEXP :re
              ORDER BY bt.id ASC"
        );
        $sel->execute(['re' => '^' . $prefix . '-[0-9]+$']);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            echo "  {$prefix}: tidak ada transaksi -> dilewati\n";
            continue;
        }
        if (count(array_filter($rows, fn($r) => $r['jenis'] === 'loan')) > 0) {
            echo "  {$prefix}: masih ada transaksi Bank Loan berformat {$prefix}- -> DILEWATI (jalankan penyesuaian Loan dulu)\n";
            continue;
        }

        $plan = [];
        foreach ($rows as $i => $r) {
            $new = $prefix . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            if ($new !== $r['no_bukti']) {
                $plan[] = ['id' => (int) $r['id'], 'old' => $r['no_bukti'], 'new' => $new];
            }
        }
        $n = count($rows);

        if (!empty($plan)) {
            // Tahap 1: nama sementara (hindari bentrok UNIQUE di tengah jalan)
            $tmp = $pdo->prepare("UPDATE bank_transactions SET no_bukti = :t WHERE id = :id");
            foreach ($plan as $p) {
                $tmp->execute(['t' => '~TMP~' . $p['id'], 'id' => $p['id']]);
            }
            // Tahap 2: nomor final
            $fin = $pdo->prepare("UPDATE bank_transactions SET no_bukti = :n WHERE id = :id");
            foreach ($plan as $p) {
                $fin->execute(['n' => $p['new'], 'id' => $p['id']]);
                echo "  #{$p['id']}: {$p['old']} -> {$p['new']}\n";
            }
        } else {
            echo "  {$prefix}: sudah rapat (1..{$n})\n";
        }

        // Counter = n + 1 (semua nomor kini 1..n)
        $c = $pdo->prepare("SELECT id FROM cash_number_counters WHERE prefix = :p");
        $c->execute(['p' => $prefix]);
        $cid = $c->fetchColumn();
        if ($cid) {
            $pdo->prepare("UPDATE cash_number_counters SET next_number = :n WHERE id = :id")->execute(['n' => $n + 1, 'id' => $cid]);
        } else {
            $pdo->prepare("INSERT INTO cash_number_counters (prefix, next_number) VALUES (:p, :n)")->execute(['p' => $prefix, 'n' => $n + 1]);
        }
        echo "  {$prefix}: counter -> " . ($n + 1) . "\n";
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
