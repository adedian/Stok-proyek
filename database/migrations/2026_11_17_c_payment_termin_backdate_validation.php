<?php
/**
 * Revisi: Validasi Barang massal, Termin/Pelunasan otomatis, presisi pembayaran, Back Date PO.
 *
 * Perubahan (SEMUA aditif -- tidak ada data/nominal lama yang diubah):
 *  1. payments.payment_type  ENUM('termin','pelunasan') DEFAULT 'termin' -> pembayaran lama = termin.
 *  2. payments.status        ENUM + 'overpaid' (kelebihan bayar).
 *  3. purchase_orders.termin_counter -> nomor termin terakhir yang PERNAH dipakai PO (tak pernah turun,
 *     walau pembayarannya dihapus/dibatalkan). Diisi awal = max(termin pernah ada, jumlah pembayaran
 *     termasuk yang sudah di Tempat Sampah) supaya nomor lama tidak dipakai ulang.
 *  4. system_setting_history -> riwayat perubahan pengaturan (siapa, kapan, nilai lama -> baru).
 *  5. goods_receipt_item_validations -> riwayat validasi per item penerimaan (append-only).
 *  6. system_settings 'po_block_backdate' = 0 (default NONAKTIF = perilaku lama).
 *
 * Idempoten (aman dijalankan ulang). Rollback: database/rollback/2026_11_17_c_*.down.sql
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = $__migPdo ?? getPDO();

$colExists = function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c");
    $st->execute(['t' => $table, 'c' => $col]);
    return (bool) $st->fetchColumn();
};

// 1. payments.payment_type
if (!$colExists('payments', 'payment_type')) {
    $pdo->exec("ALTER TABLE payments ADD COLUMN payment_type ENUM('termin','pelunasan') NOT NULL DEFAULT 'termin' AFTER termin");
    echo "  + payments.payment_type\n";
}

// 2. payments.status + 'overpaid'
$statusType = (string) $pdo->query(
    "SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'payments' AND column_name = 'status'"
)->fetchColumn();
if (stripos($statusType, 'overpaid') === false) {
    $pdo->exec("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','partial','paid','overpaid') DEFAULT 'pending'");
    echo "  ~ payments.status + overpaid\n";
}

// 3. purchase_orders.termin_counter
if (!$colExists('purchase_orders', 'termin_counter')) {
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN termin_counter INT UNSIGNED NOT NULL DEFAULT 0");
    $pdo->exec(
        "UPDATE purchase_orders po
         JOIN (SELECT purchase_order_id, GREATEST(COALESCE(MAX(termin), 0), COUNT(*)) AS n
                 FROM payments GROUP BY purchase_order_id) x ON x.purchase_order_id = po.id
            SET po.termin_counter = x.n"
    );
    echo "  + purchase_orders.termin_counter (diisi dari pembayaran yang ada, termasuk yang dihapus)\n";
}

// 4. Riwayat perubahan pengaturan
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS system_setting_history (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL,
        old_value VARCHAR(255) NULL,
        new_value VARCHAR(255) NULL,
        changed_by INT UNSIGNED NULL,
        changed_by_name VARCHAR(150) NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ssh_key (setting_key, changed_at),
        CONSTRAINT fk_ssh_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

// 5. Riwayat validasi item penerimaan (append-only; tanpa FK ke item supaya riwayat tetap ada
//    walau item penerimaan dihapus -- kolom snapshot menjaga konteksnya).
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS goods_receipt_item_validations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        goods_receipt_item_id INT UNSIGNED NOT NULL,
        receipt_number VARCHAR(60) NULL,
        item_name VARCHAR(255) NULL,
        qty_received DECIMAL(18,2) NULL,
        previous_status VARCHAR(20) NULL,
        new_status VARCHAR(20) NOT NULL,
        notes TEXT NULL,
        mode ENUM('single','bulk','legacy') NOT NULL DEFAULT 'single',
        batch_id VARCHAR(40) NULL,
        validated_by INT UNSIGNED NULL,
        validated_at DATETIME NOT NULL,
        KEY idx_griv_item (goods_receipt_item_id, validated_at),
        KEY idx_griv_batch (batch_id),
        CONSTRAINT fk_griv_user FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
// Salin validasi yang SUDAH ada (fakta di goods_receipt_items) sebagai riwayat awal -- sekali saja.
$pdo->exec(
    "INSERT INTO goods_receipt_item_validations
        (goods_receipt_item_id, receipt_number, item_name, qty_received, previous_status, new_status, notes, mode, validated_by, validated_at)
     SELECT gri.id, gr.receipt_number,
            COALESCE(gri.actual_item_name, poi.item_name, opi.item_name), gri.qty_received,
            NULL, gri.comparison_status, gri.validation_notes, 'legacy', gri.validated_by, gri.validated_at
       FROM goods_receipt_items gri
       JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
       LEFT JOIN purchase_order_items poi ON poi.id = gri.purchase_order_item_id
       LEFT JOIN offline_purchase_items opi ON opi.id = gri.offline_purchase_item_id
      WHERE gri.validated_at IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM goods_receipt_item_validations v WHERE v.goods_receipt_item_id = gri.id)"
);

// 6. Pengaturan Back Date PO (default 0 = tidak dibatasi, sama seperti perilaku lama)
$pdo->exec(
    "INSERT INTO system_settings (setting_key, setting_value, setting_group)
     SELECT 'po_block_backdate', '0', 'purchase_order' FROM DUAL
      WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'po_block_backdate')"
);

echo "  migrasi termin/pelunasan, riwayat validasi & back date PO selesai\n";
