-- =========================================================================
-- 2026_09_20_master_bank.sql
--
-- Master Bank (Master Data > Master Bank) -- sumber dropdown Bank untuk
-- modul Bank baru (Accounting). Jenis: LOAN / HR (istilah "Load" TIDAK
-- pernah dipakai di codebase ini -- sudah diaudit, jadi ini murni fitur baru,
-- bukan migrasi terminologi).
--
-- Berbeda dari `company_bank_accounts` (rekening bank perusahaan untuk kop
-- cetak Invoice Keluar) -- tidak dicampur.
-- =========================================================================

CREATE TABLE IF NOT EXISTS `master_banks` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bank_code`  VARCHAR(30) NOT NULL,
  `bank_name`  VARCHAR(150) NOT NULL,
  `jenis`      ENUM('loan','hr') NOT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `keterangan` VARCHAR(255) NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT UNSIGNED NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  UNIQUE KEY `uq_master_banks_code` (`bank_code`),
  CONSTRAINT `fk_master_banks_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_master_banks_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
