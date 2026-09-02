<?php
/**
 * bin/reset_user_password.php  --  ALAT PEMULIHAN DARURAT (CLI SAJA)
 * =================================================================
 * Reset password SATU user ke password ACAK yang kuat, di-hash dengan
 * password_hash(). Password baru dicetak SEKALI ke layar; tidak disimpan
 * di mana pun. Setiap reset ditulis ke tabel activity_logs.
 *
 * File ini SENGAJA menolak dijalankan lewat browser -- tidak ada gerbang
 * password statis, tidak ada endpoint web. Satu-satunya cara memakainya
 * adalah punya akses shell ke server (SSH / cPanel Terminal / cron).
 *
 * PEMAKAIAN
 *   php bin/reset_user_password.php --list
 *       Tampilkan daftar username (tanpa password).
 *
 *   php bin/reset_user_password.php <username>
 *       Reset password user tsb (minta konfirmasi ketik "YA").
 *
 *   php bin/reset_user_password.php <username> --yes
 *       Sama, tapi lewati konfirmasi (untuk skrip/cron).
 *
 * CONTOH
 *   Lokal (XAMPP):  C:\xampp\php\php.exe bin\reset_user_password.php admin
 *   Hosting (SSH):  php bin/reset_user_password.php admin
 *
 * Exit code: 0 sukses | 1 argumen/user salah | 2 dibatalkan | 3 error DB
 */

// ---------------------------------------------------------------------------
// Gerbang: HANYA boleh jalan dari command line. Tidak ada jalur web.
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 - skrip ini hanya bisa dijalankan dari terminal (CLI), bukan lewat browser.\n");
}

// Bootstrap konfigurasi project (ROOT_PATH, timezone, dll) + koneksi DB.
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . '/config/database.php';

// ---------------------------------------------------------------------------
// Util
// ---------------------------------------------------------------------------
function out(string $s = ''): void { fwrite(STDOUT, $s . PHP_EOL); }
function err(string $s = ''): void { fwrite(STDERR, $s . PHP_EOL); }

function usage(): void
{
    out('Pemakaian:');
    out('  php bin/reset_user_password.php --list');
    out('  php bin/reset_user_password.php <username> [--yes]');
}

/** Password acak kuat: 16 karakter alfanumerik (~95 bit entropi). */
function generatePassword(int $len = 16): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'; // tanpa 0/O/1/I/l
    $max = strlen($alphabet) - 1;
    $pw = '';
    for ($i = 0; $i < $len; $i++) {
        $pw .= $alphabet[random_int(0, $max)];
    }
    return $pw;
}

function prompt(string $q): string
{
    fwrite(STDOUT, $q);
    $line = fgets(STDIN);
    return $line === false ? '' : trim($line);
}

// ---------------------------------------------------------------------------
// Parse argumen
// ---------------------------------------------------------------------------
$args = array_slice($argv, 1);
$flags = array_values(array_filter($args, fn($a) => str_starts_with($a, '-')));
$positional = array_values(array_filter($args, fn($a) => !str_starts_with($a, '-')));

$wantList = in_array('--list', $flags, true);
$skipConfirm = in_array('--yes', $flags, true) || in_array('-y', $flags, true);
$wantHelp = in_array('--help', $flags, true) || in_array('-h', $flags, true);

if ($wantHelp) {
    usage();
    exit(0);
}

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    err('Gagal konek database: ' . $e->getMessage());
    exit(3);
}

// ---------------------------------------------------------------------------
// Mode --list
// ---------------------------------------------------------------------------
if ($wantList) {
    $rows = $pdo->query(
        "SELECT u.id, u.username, u.full_name, r.role_slug, u.status
           FROM users u
           LEFT JOIN roles r ON r.id = u.role_id
          WHERE u.deleted_at IS NULL
          ORDER BY u.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        out('(tidak ada user aktif)');
        exit(0);
    }

    $w = ['id' => 4, 'username' => 8, 'full_name' => 9, 'role_slug' => 4, 'status' => 6];
    foreach ($rows as $r) {
        foreach ($w as $k => $len) {
            $w[$k] = max($len, strlen((string) ($r[$k] ?? '')));
        }
    }
    $fmt = "%-{$w['id']}s  %-{$w['username']}s  %-{$w['full_name']}s  %-{$w['role_slug']}s  %-{$w['status']}s";
    out(sprintf($fmt, 'ID', 'USERNAME', 'NAMA', 'ROLE', 'STATUS'));
    out(str_repeat('-', array_sum($w) + 8));
    foreach ($rows as $r) {
        out(sprintf($fmt, $r['id'], $r['username'], $r['full_name'], $r['role_slug'] ?? '-', $r['status']));
    }
    out();
    out('Tanpa menampilkan password (memang tidak bisa -- tersimpan sebagai hash).');
    exit(0);
}

// ---------------------------------------------------------------------------
// Mode reset: butuh tepat 1 username
// ---------------------------------------------------------------------------
if (count($positional) !== 1) {
    err('Butuh tepat 1 <username>. Pakai --list untuk melihat daftarnya.');
    err('');
    usage();
    exit(1);
}

$username = $positional[0];

$user = $pdo->prepare(
    "SELECT u.id, u.username, u.full_name, u.status, r.role_slug
       FROM users u
       LEFT JOIN roles r ON r.id = u.role_id
      WHERE u.username = :u AND u.deleted_at IS NULL
      LIMIT 1"
);
$user->execute(['u' => $username]);
$user = $user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    err("User '{$username}' tidak ditemukan (atau sudah dihapus).");
    err('Pakai --list untuk melihat username yang valid.');
    exit(1);
}

out('');
out('  Target reset password');
out('  --------------------------------------------------');
out('  Username : ' . $user['username']);
out('  Nama     : ' . ($user['full_name'] ?: '-'));
out('  Role     : ' . ($user['role_slug'] ?: '-'));
out('  Status   : ' . $user['status']);
out('  --------------------------------------------------');
out('');

if (!$skipConfirm) {
    $ans = prompt("  Ketik 'YA' (huruf besar) untuk mereset password user ini: ");
    if ($ans !== 'YA') {
        out('  Dibatalkan. Tidak ada yang diubah.');
        exit(2);
    }
}

$newPassword = generatePassword();
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

// Siapa yang menjalankan (untuk jejak audit) -- best effort, tidak fatal kalau kosong.
$osUser = getenv('USER') ?: getenv('USERNAME') ?: (function_exists('get_current_user') ? get_current_user() : 'unknown');
$host   = gethostname() ?: 'unknown-host';

try {
    $pdo->beginTransaction();

    $upd = $pdo->prepare(
        "UPDATE users
            SET password = :pw, password_changed_at = NOW(), updated_at = NOW()
          WHERE id = :id"
    );
    $upd->execute(['pw' => $hash, 'id' => $user['id']]);

    $log = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, module, action, description, ip_address, created_by)
         VALUES (:uid, 'user', 'password_reset_cli', :desc, 'CLI', NULL)"
    );
    $log->execute([
        'uid'  => $user['id'],
        'desc' => sprintf(
            "Password user '%s' (#%d) di-reset lewat bin/reset_user_password.php oleh OS user '%s' @ %s",
            $user['username'], $user['id'], $osUser, $host
        ),
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err('Gagal menyimpan perubahan: ' . $e->getMessage());
    exit(3);
}

out('');
out('  ====================================================');
out('  PASSWORD BARU untuk "' . $user['username'] . '":');
out('');
out('      ' . $newPassword);
out('');
out('  Password ini HANYA ditampilkan sekali. Catat sekarang,');
out('  berikan ke user ybs lewat jalur aman, dan minta dia');
out('  segera menggantinya setelah login.');
out('  ====================================================');
out('');

exit(0);
