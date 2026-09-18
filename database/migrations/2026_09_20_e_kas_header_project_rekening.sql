-- =========================================================================
-- 2026_09_20_kas_header_project_rekening.sql
--
-- Tambah `project_id` & `rekening_id` di HEADER cash_transactions (dulu
-- project_id hanya ada di cash_transaction_items/baris). Dipakai untuk:
--   - filter Project & judul dinamis Laporan Kas
--   - gerbang Kas berbasis Project (purchase/pic_project/admin_project)
--   - dropdown Rekening opsional (master_rekening) pada form Kas
--
-- ADDITIVE & NON-DESTRUKTIF -- kolom baru NULL-able, data lama tidak disentuh.
-- Backfill best-effort: kalau SEMUA baris rincian satu transaksi menunjuk ke
-- project_id yang SAMA, header ikut diisi; kalau beda-beda/kosong -> NULL
-- (Super Admin bisa lengkapi manual lewat Edit Kas nanti).
-- =========================================================================

ALTER TABLE `cash_transactions`
  ADD COLUMN `project_id`  INT UNSIGNED NULL AFTER `division`,
  ADD COLUMN `rekening_id` INT UNSIGNED NULL AFTER `project_id`;

ALTER TABLE `cash_transactions`
  ADD CONSTRAINT `fk_cash_trx_project`  FOREIGN KEY (`project_id`)  REFERENCES `projects`(`id`),
  ADD CONSTRAINT `fk_cash_trx_rekening` FOREIGN KEY (`rekening_id`) REFERENCES `master_rekening`(`id`);

ALTER TABLE `cash_transactions`
  ADD INDEX `idx_cash_trx_project` (`project_id`),
  ADD INDEX `idx_cash_trx_rekening` (`rekening_id`);

UPDATE `cash_transactions` c
   SET c.project_id = (
       SELECT MIN(i.project_id)
         FROM `cash_transaction_items` i
        WHERE i.cash_transaction_id = c.id
          AND i.project_id IS NOT NULL
   )
 WHERE c.project_id IS NULL
   AND (
       SELECT COUNT(DISTINCT i.project_id)
         FROM `cash_transaction_items` i
        WHERE i.cash_transaction_id = c.id
          AND i.project_id IS NOT NULL
   ) = 1;
