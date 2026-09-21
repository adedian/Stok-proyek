-- =========================================================================
-- 2026_09_21_a_bank_rekening_and_no_bukti_fix.sql
--
-- Revisi lanjutan Kas/Bank (audit implementasi existing) -- dua perbaikan:
--
--  1) `bank_transactions.rekening_id` -- supaya transaksi Bank juga bisa
--     ditautkan OPSIONAL ke Master Rekening (sama seperti Kas), dipakai
--     filter Rekening baru di menu Kas (Kas+Bank sudah digabung sejak
--     migration 2026_09_20). Non-destruktif, kolom baru NULL-able.
--
--  2) No Bukti Bank diperbaiki dari format lama "BANK-0001" (dipakai commit
--     awal modul Bank) menjadi "BK-0001" sesuai instruksi revisi. Modul Bank
--     BELUM PERNAH di-push/deploy ke produksi (masih commit lokal), jadi
--     baris "BANK-0001" yang ada di DB lokal murni data uji -- aman diganti
--     non-destruktif di sini. Counter lama untuk prefix "BANK" dibuang
--     supaya CashNumber::next('BK', 'bank_transactions') menyemai ulang dari
--     data (yang sudah bernomor "BK-xxxx") lewat seedFromExisting().
-- =========================================================================

ALTER TABLE `bank_transactions`
  ADD COLUMN `rekening_id` INT UNSIGNED NULL AFTER `bank_id`;

ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `fk_bank_trx_rekening` FOREIGN KEY (`rekening_id`) REFERENCES `master_rekening`(`id`),
  ADD INDEX `idx_bank_trx_rekening` (`rekening_id`);

UPDATE `bank_transactions`
   SET no_bukti = CONCAT('BK-', SUBSTRING(no_bukti, 6))
 WHERE no_bukti REGEXP '^BANK-[0-9]+$';

DELETE FROM `cash_number_counters` WHERE prefix = 'BANK';
