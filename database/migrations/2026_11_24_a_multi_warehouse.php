<?php
/**
 * Multi Gudang + Mutasi Barang Antar Gudang (2026-11-24).
 *
 * Dasar penyimpanan stok: satu baris `inventory` = satu bucket (barang + satuan + project/scope)
 * DI SATU GUDANG. Kolom baru `inventory.warehouse_id` ikut jadi bagian kunci bucket, sehingga
 * semua query total/dashboard/stok minimum yang menjumlah baris tetap benar.
 *
 * Langkah (semua idempotent -- aman dijalankan ulang):
 *   1. Tentukan gudang awal: Master Gudang yang namanya memuat "Domas" (aktif); kalau belum ada,
 *      dibuat "Gudang Domas" lewat penomoran kode Master Gudang yang sama. Disimpan di
 *      system_settings `stock.default_warehouse_id` (dipakai Kas stok & stok lama).
 *   2. inventory.warehouse_id (+ FK/index), backfill SEMUA stok lama ke gudang awal. NOT NULL setelahnya.
 *   3. goods_receipts.warehouse_id (+ FK/index), backfill penerimaan lama ke gudang awal.
 *   4. stock_out: jenis tujuan baru 'gudang' + gudang asal/tujuan, status mutasi, penerima, tautan ke
 *      bucket tujuan. source_warehouse_id di-backfill dari bucket asal tiap baris lama.
 *
 * Data stok & kartu stok lama TIDAK diubah angkanya -- hanya diberi label gudang awal.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$db  = $pdo->query('SELECT DATABASE()')->fetchColumn();

$hasCol = static function (string $table, string $col) use ($pdo, $db): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :d AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $st->execute(['d' => $db, 't' => $table, 'c' => $col]);
    return (int) $st->fetchColumn() > 0;
};
$hasIndex = static function (string $table, string $index) use ($pdo, $db): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :d AND TABLE_NAME = :t AND INDEX_NAME = :i");
    $st->execute(['d' => $db, 't' => $table, 'i' => $index]);
    return (int) $st->fetchColumn() > 0;
};
$hasFk = static function (string $table, string $fk) use ($pdo, $db): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :d AND TABLE_NAME = :t AND CONSTRAINT_NAME = :c AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    $st->execute(['d' => $db, 't' => $table, 'c' => $fk]);
    return (int) $st->fetchColumn() > 0;
};

// ---------------------------------------------------------------- 1. Gudang awal
$defaultId = (int) $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'stock.default_warehouse_id'")->fetchColumn();
if ($defaultId > 0) {
    $ok = (int) $pdo->query("SELECT COUNT(*) FROM warehouses WHERE id = {$defaultId} AND deleted_at IS NULL")->fetchColumn();
    if (!$ok) {
        $defaultId = 0;
    }
}
if ($defaultId === 0) {
    $row = $pdo->query("SELECT id FROM warehouses WHERE deleted_at IS NULL AND status = 'active' AND warehouse_name LIKE '%Domas%' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $defaultId = (int) $row['id'];
        echo "  gudang awal = Master Gudang yang sudah ada (id {$defaultId})\n";
    } else {
        require_once ROOT_PATH . '/app/models/CodeConfig.php';
        $code = (new CodeConfig())->nextCode('warehouse');
        if ($code === null) {
            $code = 'WH-DOMAS';
        }
        $st = $pdo->prepare("INSERT INTO warehouses (warehouse_code, warehouse_name, status) VALUES (:c, 'Gudang Domas', 'active')");
        $st->execute(['c' => $code]);
        $defaultId = (int) $pdo->lastInsertId();
        echo "  + Master Gudang 'Gudang Domas' dibuat (id {$defaultId}, kode {$code})\n";
    }
    $st = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES ('stock.default_warehouse_id', :v, 'stock')
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $st->execute(['v' => (string) $defaultId]);
}

// ---------------------------------------------------------------- 2. inventory.warehouse_id
if (!$hasCol('inventory', 'warehouse_id')) {
    $pdo->exec("ALTER TABLE inventory ADD COLUMN warehouse_id INT UNSIGNED NULL AFTER project_id");
    echo "  + kolom inventory.warehouse_id\n";
}
$n = $pdo->exec("UPDATE inventory SET warehouse_id = {$defaultId} WHERE warehouse_id IS NULL");
echo "  backfill inventory.warehouse_id: {$n} baris\n";
$pdo->exec("ALTER TABLE inventory MODIFY COLUMN warehouse_id INT UNSIGNED NOT NULL");
if (!$hasIndex('inventory', 'idx_inventory_warehouse')) {
    $pdo->exec("ALTER TABLE inventory ADD INDEX idx_inventory_warehouse (warehouse_id)");
}
if (!$hasFk('inventory', 'fk_inventory_warehouse')) {
    $pdo->exec("ALTER TABLE inventory ADD CONSTRAINT fk_inventory_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)");
}

// ---------------------------------------------------------------- 3. goods_receipts.warehouse_id
if (!$hasCol('goods_receipts', 'warehouse_id')) {
    $pdo->exec("ALTER TABLE goods_receipts ADD COLUMN warehouse_id INT UNSIGNED NULL AFTER project_id");
    echo "  + kolom goods_receipts.warehouse_id\n";
}
$n = $pdo->exec("UPDATE goods_receipts SET warehouse_id = {$defaultId} WHERE warehouse_id IS NULL");
echo "  backfill goods_receipts.warehouse_id: {$n} baris\n";
if (!$hasIndex('goods_receipts', 'idx_gr_warehouse')) {
    $pdo->exec("ALTER TABLE goods_receipts ADD INDEX idx_gr_warehouse (warehouse_id)");
}
if (!$hasFk('goods_receipts', 'fk_gr_warehouse')) {
    $pdo->exec("ALTER TABLE goods_receipts ADD CONSTRAINT fk_gr_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)");
}

// ---------------------------------------------------------------- 4. stock_out: mutasi antar gudang
$pdo->exec("ALTER TABLE stock_out MODIFY COLUMN destination_type ENUM('project','client','gudang') NOT NULL DEFAULT 'project'");
$cols = [
    'source_warehouse_id'  => "INT UNSIGNED NULL AFTER inventory_id",
    'dest_warehouse_id'    => "INT UNSIGNED NULL AFTER source_warehouse_id",
    'transfer_status'      => "ENUM('dalam_perjalanan','diterima','dibatalkan') NULL AFTER dest_warehouse_id",
    'dest_inventory_id'    => "INT UNSIGNED NULL AFTER transfer_status",
    'transfer_received_at' => "DATETIME NULL AFTER dest_inventory_id",
    'transfer_received_by' => "INT UNSIGNED NULL AFTER transfer_received_at",
];
foreach ($cols as $col => $def) {
    if (!$hasCol('stock_out', $col)) {
        $pdo->exec("ALTER TABLE stock_out ADD COLUMN {$col} {$def}");
        echo "  + kolom stock_out.{$col}\n";
    }
}
$n = $pdo->exec("UPDATE stock_out so JOIN inventory i ON i.id = so.inventory_id
                    SET so.source_warehouse_id = i.warehouse_id
                  WHERE so.source_warehouse_id IS NULL");
echo "  backfill stock_out.source_warehouse_id: {$n} baris\n";
foreach (['idx_so_source_wh' => 'source_warehouse_id', 'idx_so_dest_wh' => 'dest_warehouse_id', 'idx_so_transfer_status' => 'transfer_status'] as $idx => $col) {
    if (!$hasIndex('stock_out', $idx)) {
        $pdo->exec("ALTER TABLE stock_out ADD INDEX {$idx} ({$col})");
    }
}
foreach (['fk_so_source_wh' => 'source_warehouse_id', 'fk_so_dest_wh' => 'dest_warehouse_id'] as $fk => $col) {
    if (!$hasFk('stock_out', $fk)) {
        $pdo->exec("ALTER TABLE stock_out ADD CONSTRAINT {$fk} FOREIGN KEY ({$col}) REFERENCES warehouses(id)");
    }
}
if (!$hasFk('stock_out', 'fk_so_dest_inventory')) {
    $pdo->exec("ALTER TABLE stock_out ADD CONSTRAINT fk_so_dest_inventory FOREIGN KEY (dest_inventory_id) REFERENCES inventory(id)");
}
if (!$hasFk('stock_out', 'fk_so_received_by')) {
    $pdo->exec("ALTER TABLE stock_out ADD CONSTRAINT fk_so_received_by FOREIGN KEY (transfer_received_by) REFERENCES users(id)");
}

echo "  selesai: multi gudang (gudang awal id {$defaultId})\n";
