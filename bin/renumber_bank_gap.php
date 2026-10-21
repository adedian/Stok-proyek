<?php
/**
 * bin/renumber_bank_gap.php  --  KOREKSI NOMOR URUT No Bukti BANK YANG LONCAT (CLI SAJA)
 * =========================================================================
 * Contoh masalah: ... BK 09/0015, BK 09/0016, BK 09/0018, BK 09/0019 ...  (0017 hilang)
 * Tujuan: nomor 0018 dst. DIGESER MUNDUR 1 -> 0017, 0018, ... sehingga urut lagi.
 *
 * Yang diubah HANYA kolom bank_transactions.no_bukti (+ counter bank_number_counters
 * bila sinkron). id, nominal, tanggal, uraian, PIC, relasi Kas/laporan tidak disentuh.
 * No Bukti Bank tidak dirujuk tabel lain (Kas merujuk lewat id) -- teks lama hanya
 * tersisa di Riwayat Aktivitas (catatan historis, sengaja tidak diubah).
 *
 * Cakupan = SATU deret nomor: (prefix, tahun, bulan) -- sama dengan cara counter
 * dibuat (bank_number_counters per prefix+bulan), jadi semua bank yang berbagi
 * prefix itu ikut tergeser; tool menampilkan bank mana saja yang terkena.
 * Baris di Tempat Sampah IKUT dihitung (unique index no_bukti+tahun mencakup mereka).
 *
 * PEMAKAIAN
 *   php bin/renumber_bank_gap.php --prefix=BK --period=2026-09 --gap=17
 *       DRY-RUN (default): tampilkan rencana "nomor lama -> baru", tidak mengubah apa pun.
 *   php bin/renumber_bank_gap.php --prefix=BK --period=2026-09 --gap=17 --apply
 *       Terapkan. Sebelumnya menulis berkas backup storage/backups/bank_renumber_*.json.
 *   php bin/renumber_bank_gap.php --rollback=storage/backups/bank_renumber_XXXX.json [--apply]
 *       Kembalikan nomor persis seperti sebelum koreksi (dry-run kecuali --apply).
 *
 * OPSI
 *   --prefix=BK        prefix deret (sesuai Master Kode, mis. BK / BKL).
 *   --period=YYYY-MM   bulan deret.
 *   --gap=N            nomor yang HILANG (mis. 17). Semua nomor > N digeser -1.
 *   --park-trashed     bila nomor --gap ternyata dipegang transaksi di TEMPAT SAMPAH saja, baris itu
 *                      TIDAK dihapus: ia dipindah ke nomor paling ujung deret (setelah semua nomor
 *                      di atas --gap bergeser -1), lalu nomor --gap terisi oleh nomor berikutnya.
 *                      Transaksi aktif yang memegang --gap tetap ditolak.
 *   --apply            benar-benar menulis (tanpa ini = dry-run).
 *
 * PENGAMAN (tool berhenti tanpa mengubah apa pun bila):
 *   - nomor --gap ternyata MASIH dipakai (termasuk di Tempat Sampah) -- berarti bukan celah
 *     sungguhan; pulihkan transaksinya, jangan dihapus-nomor-kan;
 *   - tidak ada nomor di atas --gap;
 *   - format nomor tidak "PREFIX MM/NNNN".
 * Seluruh perubahan dalam SATU transaksi database; urutan UPDATE naik (18->17, 19->18, ...)
 * sehingga tak pernah bentrok dengan unique index (no_bukti, no_bukti_year).
 * Counter bulan itu diturunkan 1 HANYA bila saat ini tepat = nomor tertinggi + 1 (sinkron);
 * kalau tidak sinkron dibiarkan dan dilaporkan.
 *
 * Exit code: 0 sukses | 1 argumen salah | 2 ditolak pengaman | 3 error
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $opt[$m[1]] = $m[2] ?? true;
    }
}
$apply = !empty($opt['apply']);
$park  = !empty($opt['park-trashed']);

function out(string $s): void { echo $s, "\n"; }
function fail(string $s, int $code): void { fwrite(STDERR, $s . "\n"); exit($code); }

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------------------------------------------------------------- ROLLBACK
if (isset($opt['rollback'])) {
    $file = (string) $opt['rollback'];
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($data) || empty($data['rows'])) {
        fail("Berkas backup tidak valid: {$file}", 1);
    }
    $pdo->beginTransaction();
    try {
        // Dua tahap lewat nomor sementara supaya tak bentrok (baris parkir & baris geser saling menukar nomor).
        $rows = $data['rows'];
        $upd = $pdo->prepare("UPDATE bank_transactions SET no_bukti = :o WHERE id = :id AND no_bukti = :n");
        foreach ($rows as $r) {
            $upd->execute(['o' => 'RBK-TMP-' . $r['id'], 'id' => $r['id'], 'n' => $r['new']]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException("Baris #{$r['id']} tidak lagi bernomor {$r['new']} -- rollback dihentikan.");
            }
        }
        foreach ($rows as $r) {
            $upd->execute(['o' => $r['old'], 'id' => $r['id'], 'n' => 'RBK-TMP-' . $r['id']]);
            out(sprintf("  #%d  %s  ->  %s", $r['id'], $r['new'], $r['old']));
        }
        if ($data['counter']['before'] !== null) {
            $pdo->prepare("UPDATE bank_number_counters SET next_number = :n WHERE prefix = :p AND period = :pe")
                ->execute(['n' => $data['counter']['before'], 'p' => $data['prefix'], 'pe' => $data['period']]);
        }
        if ($apply) { $pdo->commit(); out("Rollback diterapkan."); }
        else { $pdo->rollBack(); out("[DRY-RUN] tidak ada yang tersimpan. Tambah --apply untuk menerapkan."); }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        fail('GAGAL: ' . $e->getMessage(), 3);
    }
    exit(0);
}

// ---------------------------------------------------------------- KOREKSI
$prefix = strtoupper((string) ($opt['prefix'] ?? ''));
$period = (string) ($opt['period'] ?? '');
$gap    = (int) ($opt['gap'] ?? 0);
if (!preg_match('/^[A-Z][A-Z0-9]{1,5}$/', $prefix) || !preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $pm) || $gap < 1) {
    fail("Pemakaian: php bin/renumber_bank_gap.php --prefix=BK --period=2026-09 --gap=17 [--apply]", 1);
}
[$year, $month] = [(int) $pm[1], $pm[2]];
$cut = strlen($prefix) + 5;               // posisi 1-based tepat setelah "PREFIX MM/"
$re  = '^' . $prefix . ' ' . $month . '/[0-9]+$';

$pdo->beginTransaction();
try {
    $st = $pdo->prepare(
        "SELECT t.id, t.no_bukti, t.trx_date, t.mutasi, t.amount, t.deleted_at, b.bank_name,
                CAST(SUBSTRING(t.no_bukti, {$cut}) AS UNSIGNED) AS n
           FROM bank_transactions t
           JOIN master_banks b ON b.id = t.bank_id
          WHERE t.no_bukti_year = :y AND t.no_bukti REGEXP :re
          ORDER BY n ASC, t.id ASC
            FOR UPDATE"
    );
    $st->execute(['y' => $year, 're' => $re]);
    $all = $st->fetchAll(PDO::FETCH_ASSOC);

    out("Deret {$prefix} {$month}/NNNN tahun {$year}: " . count($all) . " baris (termasuk Tempat Sampah)");
    $parked = [];
    foreach ($all as $r) {
        if ((int) $r['n'] === $gap && $park && $r['deleted_at'] !== null) {
            $parked[] = $r;
            continue;
        }
        if ((int) $r['n'] === $gap) {
            fail("DITOLAK: nomor {$gap} MASIH dipakai oleh #{$r['id']} ({$r['no_bukti']}, {$r['bank_name']}"
                . ($r['deleted_at'] ? ", ada di Tempat Sampah -- pakai --park-trashed bila ingin tetap disimpan di sana" : "")
                . ") -- bukan celah. Tidak ada yang diubah.", 2);
        }
    }
    $shift = array_values(array_filter($all, fn($r) => (int) $r['n'] > $gap));
    if (!$shift) {
        fail("DITOLAK: tidak ada nomor di atas {$gap} pada deret ini. Tidak ada yang diubah.", 2);
    }
    $dups = array_count_values(array_column($shift, 'n'));
    foreach ($dups as $n => $c) {
        if ($c > 1) { fail("DITOLAK: nomor {$n} dipakai {$c} baris -- bersihkan duplikat dulu.", 2); }
    }
    $hasLower = (bool) array_filter($all, fn($r) => (int) $r['n'] < $gap);
    out($hasLower ? "Nomor di bawah {$gap}: tidak diubah." : "Peringatan: tidak ada nomor di bawah {$gap}.");

    $plan = [];
    out("\nRencana (nomor di atas {$gap} digeser -1):");
    foreach ($shift as $r) {
        $new = $prefix . ' ' . $month . '/' . str_pad((string) ((int) $r['n'] - 1), 4, '0', STR_PAD_LEFT);
        $plan[] = ['id' => (int) $r['id'], 'old' => $r['no_bukti'], 'new' => $new];
        out(sprintf("  #%-5d %-14s -> %-14s  %s  %-16s %-6s %14s%s", $r['id'], $r['no_bukti'], $new, $r['trx_date'],
            $r['bank_name'], $r['mutasi'], number_format((float) $r['amount'], 2), $r['deleted_at'] ? '  [Sampah]' : ''));
    }
    // Baris Tempat Sampah yang "parkir": nomor akhir = nomor tertinggi lama (setelah geser, ujung deret).
    $maxN = (int) max(array_column($all, 'n'));
    $parkPlan = [];
    foreach ($parked as $i => $r) {
        $new = $prefix . ' ' . $month . '/' . str_pad((string) ($maxN + $i), 4, '0', STR_PAD_LEFT);
        $parkPlan[] = ['id' => (int) $r['id'], 'old' => $r['no_bukti'], 'new' => $new,
                       'tmp' => $prefix . ' ' . $month . '/TMP' . $r['id']];
        out(sprintf("  #%-5d %-14s -> %-14s  %s  %-16s %-6s %14s  [Sampah, dipindah ke ujung deret]", $r['id'], $r['no_bukti'], $new,
            $r['trx_date'], $r['bank_name'], $r['mutasi'], number_format((float) $r['amount'], 2)));
    }
    $banks = array_unique(array_column(array_merge($shift, $parked), 'bank_name'));
    out("\nBank terdampak: " . implode(', ', $banks));

    // Counter
    $c = $pdo->prepare("SELECT id, next_number FROM bank_number_counters WHERE prefix = :p AND period = :pe FOR UPDATE");
    $c->execute(['p' => $prefix, 'pe' => $period]);
    $counter = $c->fetch(PDO::FETCH_ASSOC);
    $counterBefore = $counter ? (int) $counter['next_number'] : null;
    $counterDelta = count($parked) - 1;   // geser -1, tiap baris parkir menambah satu nomor di ujung
    if ($counter && $counterBefore === $maxN + 1) {
        out("Counter {$prefix}/{$period}: {$counterBefore} -> " . ($counterBefore + $counterDelta));
    } else {
        out("Counter {$prefix}/{$period}: " . ($counter ? "{$counterBefore} (TIDAK sinkron dgn nomor tertinggi {$maxN}; dibiarkan)" : 'belum ada') );
    }

    if (!$apply) {
        $pdo->rollBack();
        out("\n[DRY-RUN] tidak ada yang tersimpan. Tambah --apply untuk menerapkan.");
        exit(0);
    }

    // Backup SEBELUM menulis.
    $dir = ROOT_PATH . '/storage/backups';
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    $bak = $dir . '/bank_renumber_' . $prefix . '_' . $period . '_' . date('Ymd_His') . '.json';
    file_put_contents($bak, json_encode([
        'prefix' => $prefix, 'period' => $period, 'gap' => $gap, 'at' => date('c'),
        'counter' => ['before' => $counterBefore], 'rows' => array_merge($plan, $parkPlan),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    out("\nBackup: {$bak}");

    // Naik (18->17, 19->18, ...): target selalu baru dikosongkan -> tak bentrok dgn unique index.
    // Baris parkir disingkirkan dulu ke nomor sementara (memegang nomor --gap), lalu ke ujung deret.
    $upd = $pdo->prepare("UPDATE bank_transactions SET no_bukti = :n WHERE id = :id AND no_bukti = :o");
    foreach ($parkPlan as $p) {
        $upd->execute(['n' => $p['tmp'], 'id' => $p['id'], 'o' => $p['old']]);
        if ($upd->rowCount() !== 1) { throw new RuntimeException("Gagal memarkir #{$p['id']}."); }
    }
    foreach ($plan as $p) {
        $upd->execute(['n' => $p['new'], 'id' => $p['id'], 'o' => $p['old']]);
        if ($upd->rowCount() !== 1) { throw new RuntimeException("Gagal mengubah #{$p['id']}."); }
    }
    foreach ($parkPlan as $p) {
        $upd->execute(['n' => $p['new'], 'id' => $p['id'], 'o' => $p['tmp']]);
        if ($upd->rowCount() !== 1) { throw new RuntimeException("Gagal menempatkan #{$p['id']}."); }
    }
    if ($counter && $counterBefore === $maxN + 1 && $counterDelta !== 0) {
        $pdo->prepare("UPDATE bank_number_counters SET next_number = next_number + :d WHERE id = :id")
            ->execute(['d' => $counterDelta, 'id' => $counter['id']]);
    }
    $pdo->commit();
    out(count($plan) . " nomor dikoreksi. Rollback: php bin/renumber_bank_gap.php --rollback={$bak} --apply");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('GAGAL: ' . $e->getMessage(), 3);
}
