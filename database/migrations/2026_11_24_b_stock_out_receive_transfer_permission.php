<?php
/**
 * Izin baru stock_out.receive_transfer (Terima Mutasi Antar Gudang).
 * Disalin dari izin stock_out.create tiap role -- siapa yang boleh menambah Pengeluaran Barang
 * boleh menerima mutasi. Override per-akun TIDAK disalin (tetap lewat Hak Akses). Idempotent.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$n = $pdo->exec(
    "INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed)
     SELECT role_slug, 'stock_out', 'receive_transfer', allowed
       FROM role_permissions
      WHERE module = 'stock_out' AND action = 'create'"
);
echo "  receive_transfer: {$n} baris role_permissions baru (disalin dari stock_out.create)\n";
