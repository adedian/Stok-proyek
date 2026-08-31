-- ============================================================
-- KAS: kategori per item + integrasi stok (2026-09-02)
-- ============================================================
-- Kas dapat menandai transaksi sebagai "Pembelian Barang (masuk stok)"
-- (affects_stock=1). Saat ON: pilih Project + Supplier, tiap baris item bisa
-- dipilih dari master Barang (+ kategori barang + satuan). Saat disimpan,
-- tiap baris ber-item_id LANGSUNG menambah stok (stock_transactions type 'in',
-- reference_type='kas') -- TIDAK lewat Validasi Barang.
--
-- affects_stock=0 (default): Kas seperti sebelumnya (uraian bebas, tidak
-- menyentuh stok). Kolom baru NULLABLE -> baris lama aman.

ALTER TABLE `cash_transactions`
  ADD COLUMN `affects_stock`  TINYINT(1)     NOT NULL DEFAULT 0 AFTER `mutasi`,
  ADD COLUMN `project_id`     INT UNSIGNED   NULL DEFAULT NULL  AFTER `affects_stock`,
  ADD COLUMN `supplier_name`  VARCHAR(150)   NULL DEFAULT NULL  AFTER `project_id`,
  ADD KEY `idx_cash_affects_stock` (`affects_stock`),
  ADD KEY `fk_cash_project` (`project_id`),
  ADD CONSTRAINT `fk_cash_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`);

ALTER TABLE `cash_transaction_items`
  ADD COLUMN `item_id`     INT UNSIGNED NULL DEFAULT NULL AFTER `cash_transaction_id`,
  ADD COLUMN `category_id` INT UNSIGNED NULL DEFAULT NULL AFTER `item_id`,
  ADD COLUMN `unit`        VARCHAR(30)  NULL DEFAULT NULL AFTER `uraian`,
  ADD COLUMN `stock_posted_at` DATETIME NULL DEFAULT NULL AFTER `jumlah`,
  ADD KEY `fk_cti_item` (`item_id`),
  ADD KEY `fk_cti_category` (`category_id`),
  ADD CONSTRAINT `fk_cti_item`     FOREIGN KEY (`item_id`)     REFERENCES `items`(`id`),
  ADD CONSTRAINT `fk_cti_category` FOREIGN KEY (`category_id`) REFERENCES `item_categories`(`id`);
