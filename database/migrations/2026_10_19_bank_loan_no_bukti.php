<?php
/**
 * No Bukti Bank yang SUDAH ADA disesuaikan dengan aturan baru Bank Loan (2026-10-12):
 *   Bank berjenis LOAN -> Masuk = BML-xxxx, Keluar = BKL-xxxx  (HR tetap BM / BK).
 *
 * Hanya transaksi Bank dengan bank berjenis 'loan' yang masih berformat "<BM|BK>-<angka>"
 * (prefix dasar dibaca dari Master Kode bank_masuk/bank_keluar) yang diubah. Prefix baru
 * ditentukan oleh MUTASI (Masuk -> BML, Keluar -> BKL), angka urutnya dipertahankan
 * (BK-0002 -> BKL-0002). Kalau angka itu sudah dipakai di prefix baru (mis. data lama yang
 * berprefix BK walau Masuk) dipakai angka kosong berikutnya, jadi tidak pernah ada No Bukti ganda.
 * Baris yang sudah di Tempat Sampah ikut disesuaikan supaya nomornya tidak bentrok saat dipulihkan.
 *
 * Nomor bukti Bank TIDAK dirujuk tabel lain (Kas merujuk lewat id: related_bank_transaction_id),
 * jadi aman. Counter BML/BKL di cash_number_counters diset ke (angka tertinggi + 1); counter
 * BM/BK TIDAK diubah (nomor lama yang kosong tidak dipakai ulang).
 *
 * Idempotent: dijalankan ulang tidak mengubah apa pun (baris yang sudah BML/BKL tidak cocok pola).
 * Semua dalam satu transaksi.
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
$bm = $basePrefix('bank_masuk', 'BM');
$bk = $basePrefix('bank_keluar', 'BK');
$target = ['masuk' => $bm . 'L', 'keluar' => $bk . 'L'];

$rows = $pdo->query(
    "SELECT bt.id, bt.no_bukti, bt.mutasi
       FROM bank_transactions bt
       JOIN master_banks mb ON mb.id = bt.bank_id
      WHERE mb.jenis = 'loan'
      ORDER BY bt.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Semua No Bukti yang sudah ada (termasuk Tempat Sampah) -- untuk cek bentrok.
$used = [];
foreach ($pdo->query("SELECT no_bukti FROM bank_transactions")->fetchAll(PDO::FETCH_COLUMN) as $nb) {
    $used[(string) $nb] = true;
}

$maxByPrefix = [$target['masuk'] => 0, $target['keluar'] => 0];
foreach (array_keys($used) as $nb) {
    foreach ($target as $tp) {
        if (preg_match('/^' . preg_quote($tp, '/') . '-(\d+)$/', $nb, $m)) {
            $maxByPrefix[$tp] = max($maxByPrefix[$tp], (int) $m[1]);
        }
    }
}

$pdo->beginTransaction();
try {
    $upd = $pdo->prepare("UPDATE bank_transactions SET no_bukti = :n WHERE id = :id");
    $changed = 0;
    foreach ($rows as $r) {
        $mutasi = $r['mutasi'] === 'masuk' ? 'masuk' : 'keluar';
        if (!preg_match('/^(' . preg_quote($bm, '/') . '|' . preg_quote($bk, '/') . ')-(\d+)$/', (string) $r['no_bukti'], $m)) {
            continue; // sudah BML/BKL atau format lain -> biarkan
        }
        $tp = $target[$mutasi];
        $num = (int) $m[2];
        $new = $tp . '-' . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
        if (isset($used[$new])) {
            $num = $maxByPrefix[$tp] + 1;
            $new = $tp . '-' . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
        }
        $upd->execute(['n' => $new, 'id' => $r['id']]);
        unset($used[$r['no_bukti']]);
        $used[$new] = true;
        $maxByPrefix[$tp] = max($maxByPrefix[$tp], $num);
        echo "  #{$r['id']} ({$mutasi}): {$r['no_bukti']} -> {$new}\n";
        $changed++;
    }

    // Counter BML/BKL = angka tertinggi + 1 (tidak pernah diturunkan).
    $sel = $pdo->prepare("SELECT id, next_number FROM cash_number_counters WHERE prefix = :p");
    foreach ($maxByPrefix as $prefix => $max) {
        if ($max <= 0) {
            continue;
        }
        $sel->execute(['p' => $prefix]);
        $c = $sel->fetch(PDO::FETCH_ASSOC);
        if ($c) {
            if ((int) $c['next_number'] <= $max) {
                $pdo->prepare("UPDATE cash_number_counters SET next_number = :n WHERE id = :id")
                    ->execute(['n' => $max + 1, 'id' => $c['id']]);
            }
        } else {
            $pdo->prepare("INSERT INTO cash_number_counters (prefix, next_number) VALUES (:p, :n)")
                ->execute(['p' => $prefix, 'n' => $max + 1]);
        }
    }

    $pdo->commit();
    echo "  {$changed} No Bukti Bank Loan disesuaikan.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
