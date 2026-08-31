-- ============================================================
-- KAS: Project & Supplier PINDAH dari header ke tiap baris rincian (2026-09-04)
-- ============================================================
-- Lanjutan 2026_09_03_kas_category_per_item.sql. Permintaan user: Project &
-- Supplier juga dipilih per baris rincian (bukan lagi field header).
--
--   - cash_transaction_items + project_id (FK projects) + supplier_name.
--   - Aturan UI/validasi:
--       * project_id  : aktif & WAJIB hanya untuk baris ber-kategori stok scope
--                       'proyek' (Material Projek / Inventory Teknik).
--       * supplier_name: opsional, aktif untuk semua baris yang MASUK stok
--                       (affects_stock=1). Baris "Biaya Operasional" -> kosong.
--   - cash_transactions: kolom project_id & supplier_name DIBUANG.
--   - Data lama: nilai header disalin ke tiap barisnya.
-- ============================================================

USE `db_stok_proyek`;

-- 1) Rincian item: project & supplier per baris
ALTER TABLE `cash_transaction_items`
  ADD COLUMN `project_id`    INT UNSIGNED NULL DEFAULT NULL AFTER `cash_category_id`,
  ADD COLUMN `supplier_name` VARCHAR(150) NULL DEFAULT NULL AFTER `unit`;

UPDATE `cash_transaction_items` cti
  JOIN `cash_transactions` ct ON ct.id = cti.cash_transaction_id
   SET cti.`project_id`    = ct.`project_id`,
       cti.`supplier_name` = ct.`supplier_name`;

ALTER TABLE `cash_transaction_items`
  ADD KEY `fk_cti_project` (`project_id`),
  ADD CONSTRAINT `fk_cti_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`);

-- 2) Header: buang Project & Supplier
ALTER TABLE `cash_transactions`
  DROP FOREIGN KEY `fk_cash_project`;
ALTER TABLE `cash_transactions`
  DROP COLUMN `project_id`,
  DROP COLUMN `supplier_name`;
