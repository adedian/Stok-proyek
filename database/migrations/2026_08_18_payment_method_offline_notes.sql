-- ============================================================
-- MIGRASI: Master Metode Pembayaran (dipakai di modul Pembayaran,
-- dengan dukungan Quick-Add seperti master data lain) + kolom
-- Keterangan yang hilang di Pembelian Offline.
-- ADDITIVE -- tidak ada kolom/tabel lama yang diubah tipe/dihapus.
-- ============================================================

USE `db_stok_proyek`;

-- ------------------------------------------------------------
-- 1. Master: Metode Pembayaran
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `method_name` VARCHAR(100) NOT NULL UNIQUE,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_payment_methods_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nullable supaya pembayaran lama (sebelum kolom ini ada) tetap valid tanpa migrasi data
ALTER TABLE `payments`
  ADD COLUMN `payment_method_id` INT UNSIGNED NULL AFTER `termin`,
  ADD CONSTRAINT `fk_payments_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`);

-- ------------------------------------------------------------
-- 2. Pembelian Offline: kolom Keterangan yang belum ada
-- ------------------------------------------------------------
ALTER TABLE `offline_purchases`
  ADD COLUMN `notes` TEXT NULL AFTER `purchase_date`;

-- ------------------------------------------------------------
-- 3. Seed metode pembayaran umum
-- ------------------------------------------------------------
INSERT INTO `payment_methods` (`method_name`) VALUES
('Transfer Bank'), ('Tunai'), ('Cek'), ('Giro');
