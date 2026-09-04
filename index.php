<?php
/**
 * Pengalih root proyek. Aplikasi sebenarnya dilayani dari public/index.php.
 * File ini hanya supaya URL yang menyebut folder proyek saja (tanpa "/public/")
 * -- mis. link berbagi di LAN "http://<ip>/stok-proyek/" -- tetap masuk ke app.
 *
 * Di deployment produksi (DocumentRoot diarahkan langsung ke public/), file ini
 * tidak pernah tereksekusi.
 */
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
header('Location: ' . $base . '/public/', true, 302);
exit;
