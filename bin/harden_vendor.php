<?php

/**
 * Composer post-install/post-update hook -- vendor/ dibuat ulang dari nol
 * oleh `composer install` (folder ini di .gitignore, tidak ikut git push),
 * jadi .htaccess "Require all denied" miliknya (konsisten dengan app/, core/,
 * config/, database/, logs/, storage/ yang semua diblokir akses langsung
 * lewat browser) harus dibuat ulang di sini setiap kali, bukan cukup sekali
 * lewat git.
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
