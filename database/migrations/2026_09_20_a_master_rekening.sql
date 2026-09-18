-- =========================================================================
-- 2026_09_20_master_rekening.sql
--
-- Master Rekening (Master Data > Master Rekening) -- KHUSUS untuk modul Kas.
-- Berbeda dari `company_bank_accounts` (rekening bank PERUSAHAAN untuk kop
-- cetak Invoice Keluar, dikelola di Pengaturan Sistem) -- tidak dicampur,
-- sesuai instruksi: keduanya beda konsep bisnis.
--
-- Dipakai sebagai dropdown opsional (rekening_id) pada transaksi Kas --
-- lihat migration 2026_09_20_kas_header_project_rekening.sql.
-- =========================================================================

CREATE TABLE IF NOT EXISTS `master_rekening` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `kode_rekening`  VARCHAR(30) NOT NULL,
  `nama_rekening`  VARCHAR(150) NOT NULL,
  `jenis`          VARCHAR(50) NULL,
  `pic_name`       VARCHAR(150) NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `keterangan`     VARCHAR(255) NULL,
  `deleted_at`     DATETIME NULL,
  `deleted_by`     INT UNSIGNED NULL,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`     INT UNSIGNED NULL,
  UNIQUE KEY `uq_master_rekening_kode` (`kode_rekening`),
  CONSTRAINT `fk_master_rekening_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_master_rekening_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
