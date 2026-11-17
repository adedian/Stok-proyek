<?php
/**
 * Back Date per MENU transaksi (menggantikan toggle tunggal "Back Date PO").
 *
 *  1. Tabel backdate_settings: satu baris per kode menu (menu_code UNIQUE), is_active, diubah oleh/kapan.
 *     Kode tanpa baris = nonaktif (aturan tanggal lama berlaku). Riwayat perubahan memakai tabel
 *     system_setting_history yang sudah ada (setting_key = 'backdate:<kode>').
 *  2. Nilai lama system_settings.po_block_backdate dibawa ke baris 'purchase_order' (bila aktif),
 *     lalu baris lama dihapus -- riwayat lamanya di system_setting_history TIDAK dihapus.
 *
 * Idempoten. Rollback: database/rollback/2026_11_17_d_backdate_settings.down.sql
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = $__migPdo ?? getPDO();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS backdate_settings (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        menu_code VARCHAR(50) NOT NULL,
        menu_name VARCHAR(150) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        updated_by INT UNSIGNED NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_backdate_menu (menu_code),
        CONSTRAINT fk_backdate_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$old = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'po_block_backdate'")->fetchColumn();
if ($old !== false) {
    if ((string) $old === '1') {
        $pdo->exec(
            "INSERT INTO backdate_settings (menu_code, menu_name, is_active)
             VALUES ('purchase_order', 'Purchase Order (PO)', 1)
             ON DUPLICATE KEY UPDATE is_active = 1"
        );
        echo "  ~ pengaturan Back Date PO (aktif) dipindahkan ke baris purchase_order\n";
    }
    $pdo->exec("DELETE FROM system_settings WHERE setting_key = 'po_block_backdate'");
}

echo "  migrasi backdate_settings selesai\n";
