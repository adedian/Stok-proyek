<?php
/**
 * bin/renumber_bank_move.php  --  PINDAHKAN SATU No Bukti BANK KE DERET LAIN (CLI SAJA)
 * =========================================================================
 * Kasus: transaksi Bank di-EDIT dari Keluar -> Masuk (atau sebaliknya). Aplikasi tidak
 * mengganti No Bukti saat edit, jadi baris itu tetap "BK 09/0064" padahal sekarang Masuk.
 * Tool ini memindahkannya ke posisi yang benar di deret tujuan, mis. "BM 09/0013":
 *   - deret TUJUAN: nomor >= posisi digeser +1 (0013 -> 0014, 0014 -> 0015, ...);
 *   - deret ASAL  : nomor di atas nomor lama digeser -1 (celah ditutup, tak ada loncat);
 *   - counter tujuan +1, counter asal -1 (hanya bila saat ini sinkron dgn nomor tertinggi).
 * Yang diubah HANYA bank_transactions.no_bukti (+ counter). Baris Tempat Sampah ikut dihitung.
 *
 * PEMAKAIAN
 *   php bin/renumber_bank_move.php --id=123 --to="BM 09/0013"            DRY-RUN (default)
 *   php bin/renumber_bank_move.php --id=123 --to="BM 09/0013" --apply    terapkan (+ backup JSON)
 *   php bin/renumber_bank_move.php --rollback=storage/backups/bank_move_XXXX.json [--apply]
 *
 * PENGAMAN: berhenti tanpa mengubah apa pun bila id tak ada, format nomor salah, deret
 * tujuan/asal punya duplikat, atau posisi tujuan > nomor tertinggi + 1.
 * Satu transaksi database. Exit: 0 sukses | 1 argumen salah | 2 ditolak | 3 error
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

function out(string $s): void { echo $s, "\n"; }
function fail(string $s, int $code): void { fwrite(STDERR, $s . "\n"); exit($code); }

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** Terapkan daftar [id, from, to] lewat nomor sementara agar tak bentrok dgn unique index. */
function applyRenames(PDO $pdo, array $moves): void
{
    $upd = $pdo->prepare("UPDATE bank_transactions SET no_bukti = :n WHERE id = :id AND no_bukti = :o");
    foreach ($moves as $m) {
        $upd->execute(['n' => 'MV-TMP-' . $m['id'], 'id' => $m['id'], 'o' => $m['from']]);
        if ($upd->rowCount() !== 1) {
            throw new RuntimeException("Baris #{$m['id']} tidak bernomor {$m['from']}.");
        }
    }
    foreach ($moves as $m) {
        $upd->execute(['n' => $m['to'], 'id' => $m['id'], 'o' => 'MV-TMP-' . $m['id']]);
    }
}

/** Parse "BM 09/0013" -> [prefix, month(int), n(int)] atau null. */
function parseNo(string $no): ?array
{
    if (!preg_match('/^([A-Z][A-Z0-9]{1,5}) (\d{2})\/(\d{4})$/', $no, $m)) {
        return null;
    }
    return [$m[1], (int) $m[2], (int) $m[3]];
}

function fmt(string $prefix, int $month, int $n): string
{
    return sprintf('%s %02d/%04d', $prefix, $month, $n);
}

/** Semua baris satu deret (termasuk Tempat Sampah), terkunci, urut nomor. */
function seriesRows(PDO $pdo, string $prefix, int $month, int $year): array
{
    $cut = strlen($prefix) + 5;
    $st = $pdo->prepare(
        "SELECT id, no_bukti, deleted_at, CAST(SUBSTRING(no_bukti, {$cut}) AS UNSIGNED) AS n
           FROM bank_transactions
          WHERE no_bukti_year = :y AND no_bukti REGEXP :re
          ORDER BY n ASC, id ASC FOR UPDATE"
    );
    $st->execute(['y' => $year, 're' => '^' . $prefix . ' ' . sprintf('%02d', $month) . '/[0-9]{4}$']);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------- ROLLBACK
if (isset($opt['rollback'])) {
    $file = (string) $opt['rollback'];
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($data) || empty($data['rows'])) {
        fail("Berkas backup tidak valid: {$file}", 1);
    }
    $pdo->beginTransaction();
    try {
        $moves = array_map(fn($r) => ['id' => $r['id'], 'from' => $r['new'], 'to' => $r['old']], $data['rows']);
        applyRenames($pdo, $moves);
        foreach ($data['counters'] as $c) {
            if ($c['before'] !== null) {
                $pdo->prepare("UPDATE bank_number_counters SET next_number = :n WHERE prefix = :p AND period = :pe")
                    ->execute(['n' => $c['before'], 'p' => $c['prefix'], 'pe' => $c['period']]);
            }
        }
        foreach ($moves as $m) { out(sprintf("  #%d  %s  ->  %s", $m['id'], $m['from'], $m['to'])); }
        if ($apply) { $pdo->commit(); out("Rollback diterapkan."); }
        else { $pdo->rollBack(); out("[DRY-RUN] tidak ada yang tersimpan. Tambah --apply untuk menerapkan."); }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        fail('GAGAL: ' . $e->getMessage(), 3);
    }
    exit(0);
}

// ---------------------------------------------------------------- PINDAH
$id = (int) ($opt['id'] ?? 0);
$to = parseNo(trim((string) ($opt['to'] ?? '')));
if ($id < 1 || $to === null) {
    fail('Pemakaian: php bin/renumber_bank_move.php --id=123 --to="BM 09/0013" [--apply]', 1);
}
[$toPrefix, $toMonth, $toN] = $to;

$pdo->beginTransaction();
try {
    $st = $pdo->prepare("SELECT id, no_bukti, no_bukti_year, trx_date, mutasi, amount, deleted_at FROM bank_transactions WHERE id = :id FOR UPDATE");
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { fail("DITOLAK: transaksi #{$id} tidak ada.", 2); }
    $from = parseNo((string) $row['no_bukti']);
    if ($from === null) { fail("DITOLAK: format No Bukti '{$row['no_bukti']}' tidak dikenal.", 2); }
    [$fromPrefix, $fromMonth, $fromN] = $from;
    $year = (int) $row['no_bukti_year'];

    out(sprintf("Transaksi #%d  %s  %s  %s  %s  Rp %s", $id, $row['no_bukti'], $row['trx_date'], $row['mutasi'], $row['deleted_at'] ? '[Sampah]' : '', number_format((float) $row['amount'], 2)));
    if ($fromPrefix === $toPrefix && $fromMonth === $toMonth) {
        fail("DITOLAK: asal dan tujuan satu deret ({$fromPrefix} " . sprintf('%02d', $fromMonth) . "); tool ini hanya untuk pindah deret.", 2);
    }

    $dest = seriesRows($pdo, $toPrefix, $toMonth, $year);
    $src  = seriesRows($pdo, $fromPrefix, $fromMonth, $year);
    foreach ([$dest, $src] as $series) {
        $cnt = array_count_values(array_column($series, 'n'));
        foreach ($cnt as $n => $c) { if ($c > 1) { fail("DITOLAK: nomor {$n} dipakai {$c} baris di satu deret -- bersihkan duplikat dulu.", 2); } }
    }
    $destMax = $dest ? (int) max(array_column($dest, 'n')) : 0;
    $srcMax  = (int) max(array_column($src, 'n'));
    if ($toN < 1 || $toN > $destMax + 1) {
        fail("DITOLAK: posisi tujuan {$toN} di luar rentang 1.." . ($destMax + 1) . '.', 2);
    }

    // Rencana. Urutan: baris yang dipindah diparkir dulu, tujuan digeser dari atas ke bawah, asal dari bawah ke atas.
    $moves = [];
    foreach (array_reverse($dest) as $r) {
        if ((int) $r['n'] >= $toN) {
            $moves[] = ['id' => (int) $r['id'], 'from' => $r['no_bukti'], 'to' => fmt($toPrefix, $toMonth, (int) $r['n'] + 1), 'del' => $r['deleted_at'] !== null];
        }
    }
    $moves[] = ['id' => $id, 'from' => $row['no_bukti'], 'to' => fmt($toPrefix, $toMonth, $toN), 'del' => $row['deleted_at'] !== null];
    foreach ($src as $r) {
        if ((int) $r['n'] > $fromN) {
            $moves[] = ['id' => (int) $r['id'], 'from' => $r['no_bukti'], 'to' => fmt($fromPrefix, $fromMonth, (int) $r['n'] - 1), 'del' => $r['deleted_at'] !== null];
        }
    }
    out("\nRencana (" . count($moves) . " nomor berubah):");
    foreach ($moves as $m) { out(sprintf("  #%-5d %-14s -> %-14s%s", $m['id'], $m['from'], $m['to'], $m['del'] ? '  [Sampah]' : '')); }

    // Counter
    $counters = [];
    foreach ([[$toPrefix, $toMonth, $destMax, +1], [$fromPrefix, $fromMonth, $srcMax, -1]] as [$p, $mo, $max, $delta]) {
        $period = sprintf('%04d-%02d', $year, $mo);
        $c = $pdo->prepare("SELECT id, next_number FROM bank_number_counters WHERE prefix = :p AND period = :pe FOR UPDATE");
        $c->execute(['p' => $p, 'pe' => $period]);
        $cr = $c->fetch(PDO::FETCH_ASSOC);
        $before = $cr ? (int) $cr['next_number'] : null;
        $sync = $cr && $before === $max + 1;
        out("Counter {$p}/{$period}: " . ($cr ? ($sync ? "{$before} -> " . ($before + $delta) : "{$before} (TIDAK sinkron dgn nomor tertinggi {$max}; dibiarkan)") : 'belum ada'));
        $counters[] = ['prefix' => $p, 'period' => $period, 'before' => $before, 'id' => $cr['id'] ?? null, 'sync' => $sync, 'delta' => $delta];
    }

    if (!$apply) {
        $pdo->rollBack();
        out("\n[DRY-RUN] tidak ada yang tersimpan. Tambah --apply untuk menerapkan.");
        exit(0);
    }

    $dir = ROOT_PATH . '/storage/backups';
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    $bak = $dir . '/bank_move_' . $id . '_' . date('Ymd_His') . '.json';
    file_put_contents($bak, json_encode([
        'id' => $id, 'at' => date('c'),
        'counters' => array_map(fn($c) => ['prefix' => $c['prefix'], 'period' => $c['period'], 'before' => $c['before']], $counters),
        'rows' => array_map(fn($m) => ['id' => $m['id'], 'old' => $m['from'], 'new' => $m['to']], $moves),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    out("\nBackup: {$bak}");

    // Nomor sementara dulu untuk SEMUA baris, lalu ke nomor akhir -> tak ada tabrakan unique index.
    applyRenames($pdo, $moves);
    foreach ($counters as $c) {
        if ($c['sync']) {
            $pdo->prepare("UPDATE bank_number_counters SET next_number = next_number + :d WHERE id = :id")
                ->execute(['d' => $c['delta'], 'id' => $c['id']]);
        }
    }
    $pdo->commit();
    out(count($moves) . " nomor dikoreksi. Rollback: php bin/renumber_bank_move.php --rollback={$bak} --apply");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('GAGAL: ' . $e->getMessage(), 3);
}
