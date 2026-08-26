-- ============================================================
-- REVISI 2 (2026-08-21): Pembelian Offline multi-item (header + detail)
-- + integrasi ke alur Penerimaan Barang -> Validasi -> Stok.
--
-- Konteks: offline_purchases sebelumnya flat (1 baris = 1 barang, tanpa
-- kolom satuan). Migration ini memindahkan data barang ke tabel detail baru
-- (offline_purchase_items, meniru persis pola purchase_order/purchase_order_items
-- yang sudah ada), lalu menambah kolom penomoran+status di header, dan
-- memperluas goods_receipts/goods_receipt_items supaya penerimaan barang bisa
-- bersumber dari Pembelian Offline (selain PO dan Pemakai/Internal yang sudah ada).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. Tabel detail item Pembelian Offline (baru)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `offline_purchase_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `offline_purchase_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NULL,
  `item_name` VARCHAR(200) NOT NULL,
  `unit` VARCHAR(30) NOT NULL,
  `qty` DECIMAL(15,2) NOT NULL,
  `price` DECIMAL(18,2) NOT NULL,
  `subtotal` DECIMAL(18,2) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  KEY `fk_opi_purchase` (`offline_purchase_id`),
  KEY `fk_opi_item` (`item_id`),
  CONSTRAINT `fk_opi_purchase` FOREIGN KEY (`offline_purchase_id`) REFERENCES `offline_purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_opi_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Header offline_purchases: tambah nomor + status + total_amount
-- ------------------------------------------------------------
ALTER TABLE `offline_purchases`
  ADD COLUMN `purchase_number` VARCHAR(50) NULL AFTER `id`,
  ADD COLUMN `status` ENUM('belum_diterima','menunggu_validasi','diterima_sebagian','selesai') NOT NULL DEFAULT 'belum_diterima' AFTER `purchase_date`,
  ADD COLUMN `total_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `status`;

-- ------------------------------------------------------------
-- 3. Migrasi data lama (flat) -> offline_purchase_items
--    Data live saat ini hanya 2 baris tanpa info satuan -- default 'Pcs'.
-- ------------------------------------------------------------
INSERT INTO `offline_purchase_items` (`offline_purchase_id`, `item_name`, `unit`, `qty`, `price`, `subtotal`, `created_by`, `created_at`)
SELECT `id`, `item_name`, 'Pcs', `qty`, `price`, `total`, `created_by`, `created_at`
FROM `offline_purchases`;

UPDATE `offline_purchases`
SET `total_amount` = `total`,
    `purchase_number` = CONCAT('OFF/', DATE_FORMAT(`purchase_date`, '%Y/%m/'), LPAD(`id`, 4, '0'))
WHERE `purchase_number` IS NULL;

ALTER TABLE `offline_purchases`
  MODIFY COLUMN `purchase_number` VARCHAR(50) NOT NULL,
  ADD UNIQUE KEY `purchase_number` (`purchase_number`),
  DROP COLUMN `item_name`,
  DROP COLUMN `qty`,
  DROP COLUMN `price`,
  DROP COLUMN `total`;

-- ------------------------------------------------------------
-- 4. goods_receipts: tambah sumber ke-3 "offline_purchase"
-- ------------------------------------------------------------
ALTER TABLE `goods_receipts`
  MODIFY COLUMN `receipt_type` ENUM('purchase_order','pemakai','offline_purchase') NOT NULL DEFAULT 'purchase_order',
  ADD COLUMN `offline_purchase_id` INT UNSIGNED NULL AFTER `purchase_order_id`,
  ADD KEY `fk_gr_offline_purchase` (`offline_purchase_id`),
  ADD CONSTRAINT `fk_gr_offline_purchase` FOREIGN KEY (`offline_purchase_id`) REFERENCES `offline_purchases` (`id`);

-- ------------------------------------------------------------
-- 5. goods_receipt_items: tautan ke item Pembelian Offline
-- ------------------------------------------------------------
ALTER TABLE `goods_receipt_items`
  ADD COLUMN `offline_purchase_item_id` INT UNSIGNED NULL AFTER `purchase_order_item_id`,
  ADD KEY `fk_gri_opi` (`offline_purchase_item_id`),
  ADD CONSTRAINT `fk_gri_opi` FOREIGN KEY (`offline_purchase_item_id`) REFERENCES `offline_purchase_items` (`id`);

SET FOREIGN_KEY_CHECKS = 1;
