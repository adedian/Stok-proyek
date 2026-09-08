<?php
/**
 * bin/cleanup.php  --  PEMBERSIH FILE LAMA (CLI SAJA)
 * =========================================================================
 * Membuang file yang menumpuk di disk dan TIDAK dibersihkan otomatis oleh
 * aplikasi:
 *
 *   1. storage/backups/*.sql   -> backup DB manual dari menu
 *      "Pengaturan Sistem -> Backup Database". Aplikasi TIDAK pernah
 *      menghapusnya sendiri. Baris di tabel `backup_history` untuk file
 *      yang dihapus ikut dibersihkan.
 *
 *   2. logs/error.log.*        -> arsip rotasi log lama (belt-and-suspenders;
 *      config.php hanya memangkasnya saat rotasi berikutnya terpicu).
 *
 *   3. <temp dir>/dompdf_*     -> file sementara Dompdf yang nyangkut kalau
 *      sebuah render PDF gagal di tengah jalan.
 *
 * CATATAN: export PDF/Excel/CSV di modul Laporan di-stream langsung ke
 * browser, TIDAK disimpan ke disk -- jadi tidak ada yang perlu dibersihkan
 * di sana.
 *
 * PEMAKAIAN
 *   php bin/cleanup.php
 *       Jalankan pembersihan dengan setelan default.
 *
 *   php bin/cleanup.php --dry-run
 *       Tampilkan apa yang AKAN dihapus, tanpa menghapus apa pun.
 *
 *   php bin/cleanup.php --days=45 --keep=10
 *       Hapus backup > 45 hari, tapi SELALU sisakan 10 backup terbaru
 *       (berapa pun umurnya).
 *
 * OPSI
 *   --days=N       umur (hari) minimum sebelum sebuah file dianggap "lama".
 *                  Berlaku untuk backup DB dan arsip log.  Default: 30
 *   --keep=N       jumlah backup DB terbaru yang selalu dipertahankan,
 *                  walau lebih tua dari --days.                Default: 7
 *   --tmp-days=N   umur (hari) file sementara Dompdf sebelum dibuang.
 *                                                             Default: 2
 *   --dry-run      jangan hapus apa pun, cuma laporkan.
 *   --help, -h     tampilkan bantuan ini.
 *
 * Exit code: 0 sukses | 1 argumen salah | 3 error
 *
 * CONTOH
 *   Lokal (XAMPP):  C:\xampp\php\php.exe bin\cleanup.php --dry-run
 *   Hosting (SSH):  php bin/cleanup.php
 *   Cron harian  :  lihat deploy/cleanup.sh
 */

// ---------------------------------------------------------------------------
// Gerbang: HANYA command line.
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 - skrip ini hanya bisa dijalankan dari terminal (CLI), bukan lewat browser.\n");
}

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/config/database.php';

function o(string $s = ''): void { fwrite(STDOUT, $s . PHP_EOL); }
function e(string $s = ''): void { fwrite(STDERR, $s . PHP_EOL); }

function humanBytes(int $b): string
{
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float) $b;
    while ($n >= 1024 && $i < count($u) - 1) {
        $n /= 1024;
        $i++;
    }
    return ($i === 0 ? (int) $n : number_format($n, 1)) . ' ' . $u[$i];
}

/** Ambil opsi --key=value dari argv; kembalikan default kalau tidak ada. */
function intOpt(array $args, string $key, int $default): int
{
    foreach ($args as $a) {
        if (preg_match('/^--' . preg_quote($key, '/') . '=(-?\d+)$/', $a, $m)) {
            return (int) $m[1];
        }
    }
    return $default;
}

$args = array_slice($argv, 1);

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    $doc = file_get_contents(__FILE__) ?: '';
    if (preg_match('#/\*\*(.*?)\*/#s', $doc, $m)) {
        o(trim(preg_replace('/^\s*\*\s?/m', '', $m[1])));
    }
    exit(0);
}

// Validasi: tolak flag asing supaya salah ketik tidak diam-diam terabaikan.
foreach ($args as $a) {
    if (!preg_match('/^--(dry-run|days=-?\d+|keep=-?\d+|tmp-days=-?\d+)$/', $a)) {
        e("Argumen tidak dikenal: {$a}");
        e('Jalankan: php bin/cleanup.php --help');
        exit(1);
    }
}

$dryRun  = in_array('--dry-run', $args, true);
$days    = intOpt($args, 'days', 30);
$keep    = intOpt($args, 'keep', 7);
$tmpDays = intOpt($args, 'tmp-days', 2);

if ($days < 1 || $keep < 0 || $tmpDays < 1) {
    e('Nilai --days/--tmp-days minimal 1, --keep minimal 0.');
    exit(1);
}

$now         = time();
$backupCut   = $now - $days * 86400;
$tmpCut      = $now - $tmpDays * 86400;
$totalFiles  = 0;
$totalBytes  = 0;
$hadError    = false;

o(sprintf(
    '[%s] cleanup  (days=%d, keep=%d, tmp-days=%d%s)',
    date('Y-m-d H:i:s'),
    $days,
    $keep,
    $tmpDays,
    $dryRun ? ', DRY-RUN' : ''
));
o(str_repeat('-', 60));

// ---------------------------------------------------------------------------
// 1. Backup DB manual: storage/backups/*.sql
// ---------------------------------------------------------------------------
o('1) Backup DB manual  (' . BACKUP_PATH . ')');

$deletedBackupNames = [];
$sqlFiles = glob(BACKUP_PATH . '/*.sql') ?: [];

if (!$sqlFiles) {
    o('   (tidak ada file .sql)');
} else {
    // Urut terbaru dulu supaya --keep menyisakan yang paling baru.
    usort($sqlFiles, static fn($a, $b) => filemtime($b) <=> filemtime($a));

    $kept = 0;
    $n    = 0;
    $b    = 0;
    foreach ($sqlFiles as $i => $path) {
        if ($i < $keep) {
            $kept++;
            continue; // selalu pertahankan N terbaru
        }
        if (filemtime($path) >= $backupCut) {
            continue; // belum cukup tua
        }
        $size = (int) filesize($path);
        $ageD = floor(($now - filemtime($path)) / 86400);
        o(sprintf('   %s hapus  %s  (%s, %d hari)',
            $dryRun ? '[dry]' : '  -  ',
            basename($path),
            humanBytes($size),
            $ageD
        ));
        if (!$dryRun) {
            if (@unlink($path)) {
                $deletedBackupNames[] = basename($path);
            } else {
                e('   ! gagal hapus ' . $path);
                $hadError = true;
                continue;
            }
        } else {
            $deletedBackupNames[] = basename($path);
        }
        $n++;
        $b += $size;
    }
    o(sprintf('   -> %d dihapus, %s dibebaskan, %d dipertahankan (terbaru + belum tua)',
        $n, humanBytes($b), $kept + max(0, count($sqlFiles) - $keep - $n)));
    $totalFiles += $n;
    $totalBytes += $b;
}

// Bersihkan baris backup_history untuk file yang barusan dihapus.
if ($deletedBackupNames && !$dryRun) {
    try {
        $pdo = getPDO();
        $in  = implode(',', array_fill(0, count($deletedBackupNames), '?'));
        $stmt = $pdo->prepare("DELETE FROM backup_history WHERE filename IN ({$in})");
        $stmt->execute($deletedBackupNames);
        o(sprintf('   -> %d baris backup_history ikut dibersihkan', $stmt->rowCount()));
    } catch (Throwable $ex) {
        e('   ! gagal bersihkan backup_history: ' . $ex->getMessage());
        e('     (file sudah terhapus; baris riwayat bisa dihapus manual dari menu)');
        $hadError = true;
    }
} elseif ($deletedBackupNames && $dryRun) {
    o(sprintf('   [dry] %d baris backup_history akan ikut dihapus', count($deletedBackupNames)));
}

// ---------------------------------------------------------------------------
// 2. Arsip log lama: logs/error.log.*
// ---------------------------------------------------------------------------
o('');
o('2) Arsip log lama  (' . dirname(LOG_PATH) . ')');

$logArchives = glob(LOG_PATH . '.*') ?: [];
$n = 0;
$b = 0;
foreach ($logArchives as $path) {
    if (!is_file($path) || filemtime($path) >= $backupCut) {
        continue;
    }
    $size = (int) filesize($path);
    o(sprintf('   %s hapus  %s  (%s)',
        $dryRun ? '[dry]' : '  -  ', basename($path), humanBytes($size)));
    if (!$dryRun && !@unlink($path)) {
        e('   ! gagal hapus ' . $path);
        $hadError = true;
        continue;
    }
    $n++;
    $b += $size;
}
o($n ? sprintf('   -> %d dihapus, %s dibebaskan', $n, humanBytes($b))
     : '   (tidak ada arsip log lebih tua dari ' . $days . ' hari)');
$totalFiles += $n;
$totalBytes += $b;

// ---------------------------------------------------------------------------
// 3. File sementara Dompdf yang nyangkut: <temp>/dompdf_*
// ---------------------------------------------------------------------------
o('');
$tmpDir = sys_get_temp_dir();
o('3) Sisa temp Dompdf  (' . $tmpDir . ')');

$stray = array_merge(
    glob($tmpDir . '/dompdf_*') ?: [],
    glob($tmpDir . '/dompdf-*') ?: []
);
$n = 0;
$b = 0;
foreach ($stray as $path) {
    if (!is_file($path) || filemtime($path) >= $tmpCut) {
        continue;
    }
    if (!is_writable($path)) {
        continue; // milik proses/ user lain -- jangan sentuh
    }
    $size = (int) filesize($path);
    o(sprintf('   %s hapus  %s  (%s)',
        $dryRun ? '[dry]' : '  -  ', basename($path), humanBytes($size)));
    if (!$dryRun && !@unlink($path)) {
        continue; // race dengan proses lain -- abaikan diam-diam
    }
    $n++;
    $b += $size;
}
o($n ? sprintf('   -> %d dihapus, %s dibebaskan', $n, humanBytes($b))
     : '   (tidak ada sisa temp Dompdf lebih tua dari ' . $tmpDays . ' hari)');
$totalFiles += $n;
$totalBytes += $b;

// ---------------------------------------------------------------------------
o(str_repeat('-', 60));
o(sprintf('[%s] selesai%s -- %d file, %s dibebaskan',
    date('Y-m-d H:i:s'),
    $dryRun ? ' (DRY-RUN, tidak ada yang dihapus)' : '',
    $totalFiles,
    humanBytes($totalBytes)
));

exit($hadError ? 3 : 0);
