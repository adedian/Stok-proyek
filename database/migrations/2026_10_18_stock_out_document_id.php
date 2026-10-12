<?php
/**
 * Pengeluaran Barang: satu DOKUMEN (satu nomor) boleh berisi banyak barang (2026-10-12).
 *
 * Model data tetap "1 baris stock_out = 1 barang" (Surat Jalan, validasi, laporan, Tempat
 * Sampah, dashboard tidak berubah), tetapi baris-baris yang berasal dari dokumen yang sama
 * kini berbagi:
 *   - stock_out_number  (nomor dokumen yang sama, mis. 017/LBK.HME/X/2026), dan
 *   - document_id       (id baris "kepala" dokumen = identitas dokumen; BUKAN nomor).
 * Dengan begitu Edit dokumen 017 dapat menambah/mengubah/menghapus barang tanpa pernah
 * membuat nomor baru (018).
 *
 * Langkah (semua idempotent -- aman dijalankan ulang):
 *   1. Kolom stock_out.document_id (NULL) + index.
 *   2. Backfill: setiap baris lama = dokumen sendiri (document_id = id). Nomor lama TIDAK diubah.
 *   3. Index UNIK pada stock_out_number diganti index biasa (nomor kini boleh dipakai >1 baris
 *      pada dokumen yang sama). Dicari lewat information_schema, jadi tidak bergantung nama index.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$db  = $pdo->query('SELECT DATABASE()')->fetchColumn();

$hasCol = static function (string $col) use ($pdo, $db): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :d AND TABLE_NAME = 'stock_out' AND COLUMN_NAME = :c");
    $st->execute(['d' => $db, 'c' => $col]);
    return (int) $st->fetchColumn() > 0;
};

if (!$hasCol('document_id')) {
    $pdo->exec("ALTER TABLE stock_out ADD COLUMN document_id INT UNSIGNED NULL DEFAULT NULL AFTER stock_out_number");
    echo "  + kolom document_id ditambahkan\n";
}

$n = $pdo->exec("UPDATE stock_out SET document_id = id WHERE document_id IS NULL");
echo "  backfill document_id: {$n} baris\n";

// Index lookup dokumen
$idx = $pdo->prepare("SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS
                      WHERE TABLE_SCHEMA = :d AND TABLE_NAME = 'stock_out' AND COLUMN_NAME = :c AND SEQ_IN_INDEX = 1");
$idx->execute(['d' => $db, 'c' => 'document_id']);
if (!$idx->fetch()) {
    $pdo->exec("ALTER TABLE stock_out ADD INDEX idx_stock_out_document (document_id)");
    echo "  + index idx_stock_out_document\n";
}

// stock_out_number: UNIQUE -> index biasa
$idx->execute(['d' => $db, 'c' => 'stock_out_number']);
$hasPlain = false;
foreach ($idx->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ((int) $row['NON_UNIQUE'] === 0) {
        $pdo->exec("ALTER TABLE stock_out DROP INDEX `" . str_replace('`', '', $row['INDEX_NAME']) . "`");
        echo "  - index unik {$row['INDEX_NAME']} dihapus\n";
    } else {
        $hasPlain = true;
    }
}
if (!$hasPlain) {
    $pdo->exec("ALTER TABLE stock_out ADD INDEX idx_stock_out_number (stock_out_number)");
    echo "  + index idx_stock_out_number\n";
}
echo "  OK\n";
