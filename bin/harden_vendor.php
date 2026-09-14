<?php

/**
 * Dipanggil manual dari deploy/setup.sh & deploy/update.sh SETELAH
 * `composer install` -- vendor/ dibuat ulang dari nol tiap kali (folder ini
 * di .gitignore, tidak ikut git push), jadi .htaccess "Require all denied"
 * miliknya (konsisten dengan app/, core/, config/, database/, logs/,
 * storage/ yang semua diblokir akses langsung lewat browser) harus dibuat
 * ulang di sini setiap kali, bukan cukup sekali lewat git.
 *
 * SENGAJA dipanggil langsung ("php bin/harden_vendor.php" di shell script),
 * BUKAN lewat composer.json "scripts" (post-install-cmd/post-update-cmd) --
 * beberapa hosting (mis. shared hosting cPanel) mem-disable escapeshellarg()
 * di php.ini untuk hardening, dan Composer sendiri butuh fungsi itu secara
 * internal untuk MENJALANKAN event "scripts" apa pun (bukan cuma punya kita),
 * jadi seluruh `composer install` gagal total kalau didaftarkan sebagai
 * composer script di host seperti itu.
 */

$path = __DIR__ . '/../vendor/.htaccess';
if (!is_dir(dirname($path))) {
    return; // composer install belum selesai membuat folder vendor/ -- tidak seharusnya terjadi
}

file_put_contents(
    $path,
    "# Folder internal aplikasi -- tidak boleh diakses langsung lewat browser.\n"
    . "# Semua request PHP masuk lewat public/index.php (front controller).\n"
    . "Require all denied\n"
);
