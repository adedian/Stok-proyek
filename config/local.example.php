<?php
/**
 * KONFIGURASI LOKAL / PER-SERVER  --  contoh (aman di-commit)
 * ==========================================================================
 * Cara pakai:
 *   1. Salin file ini menjadi  config/local.php  (TIDAK ikut di-commit).
 *   2. Sesuaikan nilainya dengan environment tempat aplikasi berjalan.
 *
 * Kalau file  config/local.php  TIDAK ADA, aplikasi otomatis jalan sebagai
 * 'production' (error disembunyikan) memakai kredensial DB bawaan di
 * config/database.php. Jadi di server produksi cukup: JANGAN buat local.php,
 * atau buat dengan 'app_env' => 'production'.
 *
 * Di komputer developer: buat config/local.php dengan 'app_env' => 'development'
 * supaya error tampil penuh.
 *
 * PENTING: lingkungan TIDAK lagi ditebak dari Host header (dulu: akses lewat
 * "localhost" dianggap development). Header Host bisa dipalsukan penyerang untuk
 * memaksa server produksi masuk mode development -> stack trace & path bocor.
 * Sekarang penentuannya eksplisit lewat file ini / env var APP_ENV.
 */

return [
    // 'development' = error tampil penuh (khusus komputer developer).
    // 'production'  = error disembunyikan, hanya dicatat ke logs/error.log.
    'app_env' => 'development',

    // Kredensial database untuk server ini. Kosongkan salah satu key untuk
    // memakai default dari config/database.php.
    'db_host'    => 'localhost',
    'db_name'    => 'db_stok_proyek',
    'db_user'    => 'root',
    'db_pass'    => '',
    'db_charset' => 'utf8mb4',

    // Path binari mysqldump untuk fitur Backup (Pengaturan Sistem).
    //   Windows/XAMPP : 'C:\\xampp\\mysql\\bin\\mysqldump.exe'
    //   Linux hosting : 'mysqldump'  (biasanya sudah ada di PATH)
    // Kosongkan untuk memakai default otomatis sesuai OS.
    'mysqldump_path' => '',

    // --- Kompresi PDF upload (opsional) ---
    // Kalau Ghostscript terpasang, PDF hasil scan (bukti bayar / invoice / surat
    // jalan) otomatis dikecilkan saat diunggah. Tanpa Ghostscript, PDF disimpan
    // apa adanya -- fitur lain tidak terganggu.
    //   Linux (VPS)   : sudo apt install ghostscript   -> path cukup 'gs'
    //   Windows/XAMPP : install dari ghostscript.com    -> mis. 'gswin64c'
    // Kosongkan untuk memakai default per-OS ('gs' / 'gswin64c').
    'ghostscript_path'   => '',
    // Preset kualitas: '/screen' (paling kecil) | '/ebook' (seimbang) | '/printer' (tajam).
    'pdf_compress_preset' => '/ebook',
    // Plafon ukuran PDF sebelum dikompres (MB). Default 25.
    'pdf_max_upload_mb'   => 25,

    // --- Push Notification (Web Push) ---
    // Kosongkan ketiganya untuk mematikan fitur push (aplikasi tetap jalan
    // normal, cuma notifikasi push tidak terkirim). Generate SEKALI per
    // environment (jangan pakai kunci yang sama persis dengan environment
    // lain) lewat:
    //   php -r 'require "vendor/autoload.php"; $k = Minishlink\WebPush\VAPID::createVapidKeys(); echo $k["publicKey"]."\n".$k["privateKey"]."\n";'
    'vapid_public_key'  => '',
    'vapid_private_key' => '',
    // Kontak wajib menurut spesifikasi VAPID (mailto: atau URL app) -- push
    // service (Google/Mozilla dst) memakainya untuk menghubungi pemilik
    // server kalau kirimannya dianggap bermasalah/spam.
    'vapid_subject'      => 'mailto:admin@hexamultienergi.com',
];
