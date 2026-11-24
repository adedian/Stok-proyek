<?php
/**
 * Penerimaan Barang: "Barang Dikirim dari Gudang" (2026-11-24).
 * Kolom goods_receipts.sent_from_warehouse_id (NULL, FK ke warehouses) -- CATATAN asal pengiriman saja,
 * TIDAK memengaruhi stok (pemindahan stok antar gudang hanya lewat Pengeluaran Barang > Gudang).
 * Idempotent.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$db  = $pdo->query('SELECT DATABASE()')->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :d AND TABLE_NAME = 'goods_receipts' AND COLUMN_NAME = 'sent_from_warehouse_id'");
$q->execute(['d' => $db]);
if (!(int) $q->fetchColumn()) {
    $pdo->exec("ALTER TABLE goods_receipts ADD COLUMN sent_from_warehouse_id INT UNSIGNED NULL AFTER warehouse_id");
    echo "  + kolom goods_receipts.sent_from_warehouse_id\n";
}
$q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :d AND TABLE_NAME = 'goods_receipts' AND CONSTRAINT_NAME = 'fk_gr_sent_from_warehouse'");
$q->execute(['d' => $db]);
if (!(int) $q->fetchColumn()) {
    $pdo->exec("ALTER TABLE goods_receipts ADD CONSTRAINT fk_gr_sent_from_warehouse FOREIGN KEY (sent_from_warehouse_id) REFERENCES warehouses(id)");
    echo "  + FK fk_gr_sent_from_warehouse\n";
}
echo "  selesai\n";
