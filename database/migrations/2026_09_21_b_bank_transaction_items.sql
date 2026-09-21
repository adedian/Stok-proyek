-- =========================================================================
-- 2026_09_21_b_bank_transaction_items.sql
--
-- Rincian baris Tambah/Edit Bank -- pola SEDERHANA dari cash_transaction_items
-- (Kas), TAPI cuma 2 kolom isi (Uraian + Nominal) karena Bank tidak menyentuh
-- stok/kategori/qty seperti Kas. `bank_transactions.uraian` & `.amount` TETAP
-- ada (tidak dihapus) sebagai RINGKASAN denormalisasi -- diisi otomatis oleh
-- BankController dari gabungan baris ini (uraian = concat "; ", amount = SUM),
-- pola sama persis dengan cash_transactions.total_amount. Jadi list/laporan/
-- print Bank yang sudah ada TIDAK PERLU diubah sama sekali.
--
-- Bukan soft-delete -- ikut hidup/mati bersama header (biarkan menempel saat
-- header di-soft-delete, sama seperti cash_transaction_items).
-- =========================================================================

CREATE TABLE IF NOT EXISTS `bank_transaction_items` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bank_transaction_id`  INT UNSIGNED NOT NULL,
  `uraian`               VARCHAR(255) NOT NULL,
  `amount`               DECIMAL(18,2) NOT NULL DEFAULT 0,
  `created_at`           DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_bank_item_trx` (`bank_transaction_id`),
  CONSTRAINT `fk_bank_item_trx` FOREIGN KEY (`bank_transaction_id`) REFERENCES `bank_transactions`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill data lama (1 baris flat -> 1 baris rincian) supaya transaksi Bank
-- yang sudah ada tetap muncul rincian-nya saat dibuka lewat Edit.
INSERT INTO `bank_transaction_items` (`bank_transaction_id`, `uraian`, `amount`)
SELECT `id`, `uraian`, `amount` FROM `bank_transactions`;
