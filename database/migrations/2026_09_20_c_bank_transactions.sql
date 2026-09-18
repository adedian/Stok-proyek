-- =========================================================================
-- 2026_09_20_bank_transactions.sql
--
-- Modul Bank (khusus Accounting/Super Admin -- lihat config/permissions.php
-- modul 'bank'). Lebih flat dari Kas (tidak ada baris rincian/kategori/
-- integrasi stok -- Bank tidak menyentuh stok barang).
-- =========================================================================

CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `trx_date`   DATE NOT NULL,
  `bank_id`    INT UNSIGNED NOT NULL,
  `project_id` INT UNSIGNED NULL,
  `pic`        VARCHAR(150) NULL,
  `uraian`     VARCHAR(255) NOT NULL,
  `no_bukti`   VARCHAR(100) NOT NULL,
  `mutasi`     ENUM('masuk','keluar') NOT NULL,
  `amount`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT UNSIGNED NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  UNIQUE KEY `uq_bank_trx_no_bukti` (`no_bukti`),
  KEY `idx_bank_trx_date` (`trx_date`),
  KEY `idx_bank_trx_bank` (`bank_id`),
  KEY `idx_bank_trx_project` (`project_id`),
  CONSTRAINT `fk_bank_trx_bank` FOREIGN KEY (`bank_id`) REFERENCES `master_banks`(`id`),
  CONSTRAINT `fk_bank_trx_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`),
  CONSTRAINT `fk_bank_trx_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_bank_trx_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
