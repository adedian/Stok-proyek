-- Saldo Bank MANUAL untuk Laporan Pengajuan Budget (Permintaan Otorisasi).
--
-- Tabel ini TERPISAH dari ledger bank_transactions: mengubah saldo di sini TIDAK membuat/mengubah/
-- menghapus transaksi Bank Masuk/Keluar, dan TIDAK mengubah saldo aktual modul Kas/Bank.
-- Hanya dipakai laporan Pengajuan Budget sebagai "saldo awal" pada/setelah tanggal berlaku:
--   saldo(tanggal D) = saldo_baru entri terbaru dengan effective_date <= D
--                      + (Bank Masuk - Bank Keluar) yang trx_date > effective_date dan <= D.
-- Append-only (audit trail): baris tidak pernah di-UPDATE/DELETE oleh aplikasi.
CREATE TABLE IF NOT EXISTS `bank_balance_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bank_id` int(10) unsigned NOT NULL,
  `effective_date` date NOT NULL,
  `old_balance` decimal(18,2) NOT NULL,
  `new_balance` decimal(18,2) NOT NULL,
  `diff_amount` decimal(18,2) NOT NULL,
  `note` varchar(500) NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bba_bank_date` (`bank_id`, `effective_date`, `id`),
  KEY `fk_bba_user` (`created_by`),
  CONSTRAINT `fk_bba_bank` FOREIGN KEY (`bank_id`) REFERENCES `master_banks` (`id`),
  CONSTRAINT `fk_bba_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
