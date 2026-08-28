<?php
/**
 * FRONT CONTROLLER
 * Satu-satunya pintu masuk publik aplikasi.
 * Urutan include SANGAT penting: config -> session -> core -> helpers -> router
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// Composer dipakai untuk Dompdf (export PDF) & PhpSpreadsheet (export Excel
// bergaya) di modul Laporan -- guarded supaya modul lain tetap jalan normal
// kalau vendor/ belum ter-install.
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

require_once ROOT_PATH . '/app/helpers/functions.php';
require_once ROOT_PATH . '/app/helpers/auth_helper.php';
require_once ROOT_PATH . '/app/helpers/image_helper.php';
require_once ROOT_PATH . '/app/helpers/upload_helper.php';
require_once ROOT_PATH . '/app/helpers/pdf_helper.php';
require_once ROOT_PATH . '/app/helpers/excel_helper.php';
require_once ROOT_PATH . '/app/helpers/permission_helper.php';
require_once ROOT_PATH . '/app/helpers/menu_helper.php';

require_once ROOT_PATH . '/core/Router.php';

// Header keamanan HTTP untuk semua response (CSP, nosniff, frame-options, dst)
sendSecurityHeaders();

$router = new Router();
$router->dispatch();
