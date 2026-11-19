-- ============================================================
-- Pengajuan Budget:
--  (1) Integrasi PO: tiap baris pembayaran boleh ditautkan ke satu Purchase Order
--      (kolom purchase_order_id = relasi/FK, po_number + po_snapshot = SNAPSHOT saat diajukan
--      supaya histori pengajuan TIDAK ikut berubah bila PO diubah belakangan).
--  (2) Pembatalan Realisasi (khusus Super Admin): tabel log terpisah
--      pengajuan_budget_unrealize_log (siapa, kapan, alasan, status sebelum/sesudah,
--      jejak realisasi yang dibatalkan). Catatan realisasi lama TIDAK dihapus.
-- Additive only: tidak ada kolom/tabel lama yang diubah atau dihapus; data lama tetap valid
-- (baris lama otomatis purchase_order_id = NULL).
-- ============================================================

ALTER TABLE `pengajuan_budget_items`
  ADD COLUMN `purchase_order_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `project_id`,
  ADD COLUMN `po_number` VARCHAR(50) NULL DEFAULT NULL AFTER `purchase_order_id`,
  ADD COLUMN `po_snapshot` MEDIUMTEXT NULL DEFAULT NULL AFTER `po_number`,
  ADD KEY `idx_pbi_po` (`purchase_order_id`),
  ADD CONSTRAINT `fk_pbi_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `pengajuan_budget_unrealize_log` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pengajuan_id` INT(10) UNSIGNED NOT NULL,
  `cancelled_by` INT(10) UNSIGNED NULL DEFAULT NULL,
  `cancelled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason` VARCHAR(500) NOT NULL,
  `status_before` VARCHAR(30) NOT NULL,
  `status_after` VARCHAR(30) NOT NULL,
  `realized_at` DATETIME NULL DEFAULT NULL,
  `realized_by` INT(10) UNSIGNED NULL DEFAULT NULL,
  `financial_note` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pbul_pengajuan` (`pengajuan_id`),
  KEY `fk_pbul_cancelled_by` (`cancelled_by`),
  -- satu realisasi hanya bisa dibatalkan SEKALI (realisasi baru = realized_at baru)
  UNIQUE KEY `uq_pbul_realization` (`pengajuan_id`, `realized_at`),
  CONSTRAINT `fk_pbul_pengajuan` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan_budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbul_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
