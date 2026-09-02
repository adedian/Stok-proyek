<?php
/**
 * bin/migrate.php  --  RUNNER MIGRASI DATABASE (CLI SAJA)
 * =========================================================================
 * Menjalankan file di database/migrations/ (*.sql & *.php) yang BELUM pernah
 * diterapkan, urut nama file, dan mencatatnya di tabel `schema_migrations`.
 * Idempoten: file yang sudah tercatat dilewati.
 *
 * PEMAKAIAN
 *   php bin/migrate.php --status
 *       Tampilkan mana yang sudah diterapkan & mana yang pending.
 *
 *   php bin/migrate.php --baseline
 *       Tandai SEMUA file migrasi yang ada sekarang sebagai "sudah diterapkan"
 *       TANPA menjalankannya. Dipakai SEKALI di database yang sudah lama jalan
 *       (skema sudah sesuai) supaya runner tidak mengulang migrasi lama.
 *
 *   php bin/migrate.php
 *       Jalankan semua migrasi yang pending.
 *
 *   php bin/migrate.php --pretend
 *       Tampilkan apa yang AKAN dijalankan, tanpa mengeksekusi apa pun.
 *
 * Exit code: 0 sukses | 1 argumen salah | 3 error saat migrasi
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 - hanya bisa dijalankan dari terminal (CLI).\n");
}

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/config/database.php';

function o(string $s = ''): void { fwrite(STDOUT, $s . PHP_EOL); }
function e(string $s = ''): void { fwrite(STDERR, $s . PHP_EOL); }

$MIGRATIONS_DIR = ROOT_PATH . '/database/migrations';

$args = array_slice($argv, 1);
$doStatus   = in_array('--status', $args, true);
$doBaseline = in_array('--baseline', $args, true);
$doPretend  = in_array('--pretend', $args, true);
$doHelp     = in_array('--help', $args, true) || in_array('-h', $args, true);

if ($doHelp) {
    o(trim(<<<TXT
    php bin/migrate.php --status     lihat status
    php bin/migrate.php --baseline   tandai semua yang ada sebagai terapan (DB lama)
    php bin/migrate.php --pretend    lihat yang akan dijalankan, tanpa eksekusi
    php bin/migrate.php              jalankan yang pending
    TXT));
    exit(0);
}

try {
    $pdo = getPDO();
} catch (Throwable $ex) {
    e('Gagal konek database: ' . $ex->getMessage());
    exit(3);
}

// Tabel pencatat
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Daftar file migrasi (urut nama). Hanya *.sql & *.php di folder migrations.
$files = [];
foreach (glob($MIGRATIONS_DIR . '/*.{sql,php}', GLOB_BRACE) ?: [] as $path) {
    $files[] = basename($path);
}
sort($files, SORT_STRING);

$applied = array_column(
    $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_ASSOC),
    'filename'
);
$appliedSet = array_flip($applied);
$pending = array_values(array_filter($files, fn($f) => !isset($appliedSet[$f])));

// ---- --status ----
if ($doStatus) {
    o('Migrasi terdaftar di database/migrations/ : ' . count($files));
    o('Sudah diterapkan                          : ' . count($applied));
    o('Pending                                   : ' . count($pending));
    o('');
    foreach ($files as $f) {
        o(sprintf('  [%s] %s', isset($appliedSet[$f]) ? 'x' : ' ', $f));
    }
    if ($extra = array_diff($applied, $files)) {
        o('');
        o('  (tercatat di DB tapi file-nya tidak ada lagi:)');
        foreach ($extra as $f) o('    - ' . $f);
    }
    exit(0);
}

// ---- --baseline ----
if ($doBaseline) {
    if (!$pending) {
        o('Tidak ada yang perlu di-baseline (semua file sudah tercatat).');
        exit(0);
    }
    $ins = $pdo->prepare("INSERT IGNORE INTO schema_migrations (filename) VALUES (:f)");
    foreach ($pending as $f) {
        $ins->execute(['f' => $f]);
        o('  baseline: ' . $f);
    }
    o('');
    o(count($pending) . ' file ditandai sebagai sudah diterapkan (tanpa dijalankan).');
    exit(0);
}

// ---- jalankan pending ----
if (!$pending) {
    o('Tidak ada migrasi pending. Database sudah up-to-date.');
    exit(0);
}

o(($doPretend ? '[PRETEND] ' : '') . 'Migrasi pending: ' . count($pending));
o('');

$ins = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (:f)");
$ok = 0;

foreach ($pending as $f) {
    $path = $MIGRATIONS_DIR . '/' . $f;
    o('  -> ' . $f);

    if ($doPretend) {
        $ok++;
        continue;
    }

    try {
        if (str_ends_with($f, '.sql')) {
            $sql = file_get_contents($path);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('file kosong / tidak terbaca');
            }
            // mysqlnd mengizinkan multi-statement lewat exec() untuk DDL migrasi.
            $pdo->exec($sql);
        } else {
            // Migrasi .php: file self-bootstrap (require_once config sendiri) &
            // menjalankan logikanya saat di-include.
            $__migPdo = $pdo;
            require $path;
        }
        $ins->execute(['f' => $f]);
        $ok++;
        o('     OK');
    } catch (Throwable $ex) {
        e('     GAGAL: ' . $ex->getMessage());
        e('');
        e("Migrasi berhenti di '{$f}'. Perbaiki, lalu jalankan lagi -- file yang");
        e('sudah sukses tidak akan diulang.');
        exit(3);
    }
}

o('');
o($doPretend
    ? "[PRETEND] {$ok} migrasi akan dijalankan."
    : "Selesai. {$ok} migrasi diterapkan.");
exit(0);
