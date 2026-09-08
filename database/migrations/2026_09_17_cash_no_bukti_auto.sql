-- =========================================================================
-- 2026_09_17_cash_no_bukti_auto.sql
--
-- No Bukti Kas OTOMATIS per PIC (format "AD-0001").
--
--  1) user_pic_assignments.kas_prefix  -- prefix Kas milik tiap PIC, UNIQUE
--     lintas akun (tidak boleh 2 PIC memakai prefix yang sama).
--
--  2) cash_number_counters            -- counter urut per prefix, dinaikkan
--     ATOMIC (SELECT ... FOR UPDATE) oleh app/models/CashNumber.php. Pola
--     yang sama dengan document_number_counters -- aman dari race condition
--     saat 2 transaksi disimpan hampir bersamaan (BUKAN MAX(no_bukti)+1).
--
-- NON-DESTRUKTIF: data no_bukti lama TIDAK diubah. Kolom no_bukti tetap
-- VARCHAR(100) NOT NULL. Transaksi lama yang no_bukti-nya sudah berformat
-- "PREFIX-NNNN" tetap aman: CashNumber::next() menyemai counter di atas
-- nomor tertinggi yang sudah ada untuk prefix itu saat pertama kali dipakai.
-- =========================================================================

ALTER TABLE `user_pic_assignments`
  ADD COLUMN `kas_prefix` VARCHAR(6) NULL DEFAULT NULL
    COMMENT 'Prefix No Bukti Kas untuk PIC ini (mis. AD). UNIQUE lintas akun.'
    AFTER `pic_username`;

-- MySQL: banyak baris boleh NULL walau ada UNIQUE (NULL != NULL), jadi PIC
-- lama yang belum di-set prefix tidak melanggar constraint.
ALTER TABLE `user_pic_assignments`
  ADD UNIQUE KEY `uq_upa_kas_prefix` (`kas_prefix`);

CREATE TABLE IF NOT EXISTS `cash_number_counters` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `prefix`      VARCHAR(6) NOT NULL,
  `next_number` INT UNSIGNED NOT NULL DEFAULT 1,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_ccn_prefix` (`prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
