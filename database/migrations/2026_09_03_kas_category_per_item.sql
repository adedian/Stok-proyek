-- ============================================================
-- KAS: kategori PER BARIS RINCIAN + stok mengikuti kategori (2026-09-03)
-- ============================================================
-- Revisi atas 2026_09_02_kas_item_category_stock.sql. Perubahan permintaan user:
--
--   1) Kategori TIDAK lagi di header transaksi. Tiap baris rincian punya
--      kategorinya sendiri (bisa beda-beda per baris).
--   2) Kategori diambil dari Master Kategori Kas (cash_categories). Tiap
--      kategori ditandai:
--        - affects_stock = 1  -> baris dengan kategori ini MASUK stok
--        - stock_scope        -> 'kantor' (bucket Stok Kantor, tanpa project)
--                                'proyek' (bucket Stok Proyek, wajib pilih Project)
--      "Biaya Operasional" (affects_stock = 0) TIDAK menyentuh stok.
--   3) Toggle "Pembelian Barang (masuk stok)" DIHAPUS -- masuk-tidaknya stok
--      murni ditentukan kategori tiap baris. Kolom Project & Supplier tetap di
--      header (Project dipakai baris ber-kategori scope 'proyek').
--   4) Kolom "Barang" (pilih dari master Item) DIHAPUS dari form -- Uraian
--      diketik bebas; Satuan dipilih dari Master Satuan hanya untuk baris stok.
--
-- Kolom lama cash_transactions.category_id / affects_stock dan
-- cash_transaction_items.item_id / category_id (FK ke item_categories) DIBUANG.
-- Data uji lama: kategori header disalin ke tiap barisnya sebagai default.
-- ============================================================

USE `db_stok_proyek`;

-- ------------------------------------------------------------
-- 1) Master Kategori Kas: tandai kategori yang mempengaruhi stok + scope
-- ------------------------------------------------------------
ALTER TABLE `cash_categories`
  ADD COLUMN `affects_stock` TINYINT(1) NOT NULL DEFAULT 0 AFTER `category_name`,
  ADD COLUMN `stock_scope`   ENUM('kantor','proyek') NULL DEFAULT NULL AFTER `affects_stock`;

UPDATE `cash_categories` SET `affects_stock` = 1, `stock_scope` = 'proyek' WHERE `category_name` IN ('Material Proyek', 'Material Projek');
UPDATE `cash_categories` SET `affects_stock` = 1, `stock_scope` = 'kantor' WHERE `category_name` = 'Inventory Kantor';
UPDATE `cash_categories` SET `affects_stock` = 1, `stock_scope` = 'proyek' WHERE `category_name` = 'Inventory Teknik';
UPDATE `cash_categories` SET `affects_stock` = 0, `stock_scope` = NULL     WHERE `category_name` = 'Biaya Operasional';

-- ------------------------------------------------------------
-- 2) Rincian item: kategori per baris (FK ke cash_categories)
-- ------------------------------------------------------------
ALTER TABLE `cash_transaction_items`
  ADD COLUMN `cash_category_id` INT UNSIGNED NULL DEFAULT NULL AFTER `cash_transaction_id`;

-- Data lama: pakai kategori header sebagai default tiap baris.
UPDATE `cash_transaction_items` cti
  JOIN `cash_transactions` ct ON ct.id = cti.cash_transaction_id
   SET cti.`cash_category_id` = ct.`category_id`
 WHERE cti.`cash_category_id` IS NULL;

ALTER TABLE `cash_transaction_items`
  ADD KEY `fk_cti_cash_category` (`cash_category_id`),
  ADD CONSTRAINT `fk_cti_cash_category` FOREIGN KEY (`cash_category_id`) REFERENCES `cash_categories`(`id`);

-- Buang tautan ke master Item / item_categories (tidak dipakai lagi).
ALTER TABLE `cash_transaction_items`
  DROP FOREIGN KEY `fk_cti_item`,
  DROP FOREIGN KEY `fk_cti_category`;
ALTER TABLE `cash_transaction_items`
  DROP COLUMN `item_id`,
  DROP COLUMN `category_id`;

-- ------------------------------------------------------------
-- 3) Header transaksi: buang kategori & toggle affects_stock
--    (Project & Supplier tetap dipertahankan)
-- ------------------------------------------------------------
ALTER TABLE `cash_transactions`
  DROP FOREIGN KEY `fk_cash_category`;
ALTER TABLE `cash_transactions`
  DROP KEY `idx_cash_affects_stock`,
  DROP COLUMN `category_id`,
  DROP COLUMN `affects_stock`;
