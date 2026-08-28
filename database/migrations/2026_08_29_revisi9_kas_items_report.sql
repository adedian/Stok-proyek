-- ============================================================
-- REVISI 9 lanjutan (2026-08-29): Kas jadi header + rincian item
--
-- Perubahan atas permintaan user:
--   - Form Tambah Kas: bagian Uraian / Qty / Satuan pindah jadi baris item
--     berulang ("Tambah Barang"), persis pola form Purchase Order / Pembelian
--     Offline. Satu Kas = 1 header (tanggal/PIC/No Bukti/kategori/mutasi) +
--     banyak baris item {uraian, qty, satuan(=harga satuan Rp), jumlah}.
--   - Kolom "Qty" di daftar Kas diganti "Nominal (Rp)" = SUM(jumlah item).
--   - Laporan Kas (cetak / PDF / Excel) format buku kas: Saldo Awal + kolom
--     Masuk / Keluar / Saldo Akhir berjalan.
--
-- ADDITIVE + transform. `cash_transactions` baru dibuat 2026-08-28 (belum
-- dipakai produksi), jadi aman di-restrukturisasi. Baris lama (uji coba) tetap
-- dipertahankan: di-migrasi jadi 1 item per transaksi (satuan/jumlah = 0,
-- bisa diedit ulang lewat form baru).
-- ============================================================

USE `db_stok_proyek`;

-- 1) Tabel rincian item Kas
CREATE TABLE IF NOT EXISTS `cash_transaction_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cash_transaction_id` INT UNSIGNED NOT NULL,
  `uraian` VARCHAR(255) NOT NULL,
  `qty` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `satuan` DECIMAL(18,2) NOT NULL DEFAULT 0,   -- harga satuan (Rp)
  `jumlah` DECIMAL(18,2) NOT NULL DEFAULT 0,   -- qty * satuan
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_cash_item_trx` (`cash_transaction_id`),
  CONSTRAINT `fk_cash_item_trx` FOREIGN KEY (`cash_transaction_id`)
    REFERENCES `cash_transactions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Migrasi baris lama (kalau ada) jadi 1 item per transaksi
INSERT INTO `cash_transaction_items` (`cash_transaction_id`, `uraian`, `qty`, `satuan`, `jumlah`)
SELECT `id`, COALESCE(NULLIF(`uraian`, ''), '(tanpa uraian)'), `qty`, 0, 0
FROM `cash_transactions`;

-- 3) Header: buang kolom yang pindah ke item, tambah total_amount
ALTER TABLE `cash_transactions`
  ADD COLUMN `total_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `mutasi`;

UPDATE `cash_transactions` SET `total_amount` = COALESCE(`amount`, 0);

ALTER TABLE `cash_transactions` DROP FOREIGN KEY `fk_cash_unit`;
ALTER TABLE `cash_transactions`
  DROP COLUMN `amount`,
  DROP COLUMN `uraian`,
  DROP COLUMN `qty`,
  DROP COLUMN `unit_id`;
