-- ============================================================
-- MIGRASI: Master Data (Barang, Kategori, Satuan, Gudang, Lokasi),
-- Pengaturan Sistem, dan dukungan Quick-Add di Purchase Order.
-- Jalankan SETELAH database/schema.sql (dan seeder RBAC kalau dipakai).
-- Semua perubahan ADDITIVE -- tidak ada kolom/tabel lama yang diubah
-- tipe/dihapus, supaya data & fitur yang sudah berjalan tetap aman.
-- ============================================================

USE `db_stok_proyek`;

-- ------------------------------------------------------------
-- 1. Kolom tambahan di tabel yang sudah ada
-- ------------------------------------------------------------

ALTER TABLE `suppliers`
  ADD COLUMN `npwp` VARCHAR(30) NULL AFTER `contact_person`;

ALTER TABLE `stock_out`
  ADD COLUMN `stock_out_number` VARCHAR(50) NULL UNIQUE AFTER `id`;

-- ------------------------------------------------------------
-- 2. Master: Kategori Barang
-- ------------------------------------------------------------
CREATE TABLE `item_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_item_categories_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Master: Satuan
-- ------------------------------------------------------------
CREATE TABLE `units` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `unit_name` VARCHAR(50) NOT NULL UNIQUE,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_units_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Master: Barang (katalog item, terpisah dari `inventory`
--    yang melacak STOK per project -- lihat catatan phase)
-- ------------------------------------------------------------
CREATE TABLE `items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_code` VARCHAR(30) NOT NULL UNIQUE,
  `item_name` VARCHAR(200) NOT NULL,
  `category_id` INT UNSIGNED NULL,
  `unit_id` INT UNSIGNED NOT NULL,
  `specification` TEXT NULL,
  `min_stock` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `item_categories`(`id`),
  CONSTRAINT `fk_items_unit` FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`),
  CONSTRAINT `fk_items_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Master: Gudang & Lokasi Penyimpanan
--    (berdiri sendiri, TIDAK disambungkan ke inventory/stock_out)
-- ------------------------------------------------------------
CREATE TABLE `warehouses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `warehouse_code` VARCHAR(30) NOT NULL UNIQUE,
  `warehouse_name` VARCHAR(150) NOT NULL,
  `address` TEXT NULL,
  `pic_name` VARCHAR(100) NULL,
  `phone` VARCHAR(30) NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_warehouses_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `storage_locations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `warehouse_id` INT UNSIGNED NOT NULL,
  `location_code` VARCHAR(30) NOT NULL UNIQUE,
  `location_name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_storage_locations_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`),
  CONSTRAINT `fk_storage_locations_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. Pengaturan Sistem: key-value generik (company profile,
--    prefix nomor dokumen, session timeout, toggle notifikasi)
-- ------------------------------------------------------------
CREATE TABLE `system_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(80) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(40) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. Riwayat Backup Database
-- ------------------------------------------------------------
CREATE TABLE `backup_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. Keterlacakan opsional: baris item PO -> katalog Barang
--    (nullable, tidak mempengaruhi baris lama / logic yang sudah ada)
-- ------------------------------------------------------------
ALTER TABLE `purchase_order_items`
  ADD COLUMN `item_id` INT UNSIGNED NULL AFTER `purchase_order_id`,
  ADD CONSTRAINT `fk_poi_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`);

-- ------------------------------------------------------------
-- 9. Seed data awal
-- ------------------------------------------------------------

-- Satuan umum supaya form Barang tidak kosong saat pertama dipakai
INSERT INTO `units` (`unit_name`) VALUES
('Pcs'), ('Kg'), ('M3'), ('Liter'), ('Set'), ('Box'), ('Roll'), ('Unit'), ('Sak'), ('Batang'), ('Lembar');

-- Pengaturan default -- SAMA PERSIS dengan perilaku yang sudah berjalan
-- (kalau admin tidak pernah buka halaman Pengaturan, tidak ada yang berubah)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('company_name', 'Sistem Kontrol Stok Proyek', 'company'),
('company_logo', '', 'company'),
('company_address', '', 'company'),
('company_phone', '', 'company'),
('company_email', '', 'company'),
('company_npwp', '', 'company'),
('prefix_po', 'PO/', 'numbering'),
('prefix_gr', 'GR/', 'numbering'),
('prefix_opn', 'SO/', 'numbering'),
('prefix_sto', 'STO/', 'numbering'),
('session_timeout_minutes', '30', 'session'),
('notify_selisih_barang', '1', 'notification'),
('notify_invoice_pending', '1', 'notification'),
('notify_stok_minimum', '1', 'notification'),
('notify_po_belum_diproses', '1', 'notification');
