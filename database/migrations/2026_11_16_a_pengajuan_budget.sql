-- ============================================================================
-- PENGAJUAN BUDGET (Permintaan Otorisasi) -- modul MANDIRI, terpisah dari
-- "Request Budget" (request_budgets*) yang sudah ada.
--
-- Pengajuan Budget = daftar rencana pembayaran lintas rekening sumber (Master Bank)
-- yang dicetak sebagai "PERMINTAAN OTORISASI" dengan blok Saldo & Sisa Saldo.
-- Modul ini TIDAK membuat/mengubah transaksi Bank/Kas; saldo hanya DIBACA.
-- ============================================================================

-- Header
CREATE TABLE IF NOT EXISTS `pengajuan_budgets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pengajuan_number` varchar(40) NOT NULL,
  `pengajuan_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('draft','diajukan','direalisasikan','dibatalkan') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `realized_at` datetime DEFAULT NULL,
  `realized_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `cancel_reason` varchar(500) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pb_number` (`pengajuan_number`),
  KEY `idx_pb_date` (`pengajuan_date`),
  KEY `idx_pb_status` (`status`),
  KEY `idx_pb_creator` (`created_by`),
  CONSTRAINT `fk_pb_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_pb_realized_by` FOREIGN KEY (`realized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pb_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Baris. source_bank_id = Rekening Sumber (Master Bank / master_banks).
-- category_id = Master Kategori yang sama dengan Kas (cash_categories).
-- bank_transaction_id = RELASI PERSIAPAN ke Bank Keluar (diisi nanti saat realisasi ditarik
--   ke transaksi Bank). Belum ada fitur yang menulis kolom ini; saldo Bank TIDAK diubah oleh modul ini.
CREATE TABLE IF NOT EXISTS `pengajuan_budget_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pengajuan_id` int(10) unsigned NOT NULL,
  `line_no` smallint(5) unsigned NOT NULL DEFAULT 1,
  `source_bank_id` int(10) unsigned NOT NULL,
  `jenis_transaksi` varchar(100) NOT NULL,
  `tujuan` varchar(255) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `bank_transaction_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pbi_pengajuan` (`pengajuan_id`),
  KEY `idx_pbi_bank` (`source_bank_id`),
  KEY `idx_pbi_category` (`category_id`),
  KEY `idx_pbi_bank_trx` (`bank_transaction_id`),
  CONSTRAINT `fk_pbi_pengajuan` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan_budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbi_bank` FOREIGN KEY (`source_bank_id`) REFERENCES `master_banks` (`id`),
  CONSTRAINT `fk_pbi_category` FOREIGN KEY (`category_id`) REFERENCES `cash_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pbi_bank_trx` FOREIGN KEY (`bank_transaction_id`) REFERENCES `bank_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Histori perubahan (siapa/kapan/apa + status lama -> baru)
CREATE TABLE IF NOT EXISTS `pengajuan_budget_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pengajuan_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pbh_pengajuan` (`pengajuan_id`),
  KEY `fk_pbh_user` (`user_id`),
  CONSTRAINT `fk_pbh_pengajuan` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan_budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Master Bank: atribut tampilan Permintaan Otorisasi (warna per rekening agar rekening baru
-- bisa punya warna sendiri; nomor rekening; catatan di samping blok saldo, mis. "autodebet tgl 02").
ALTER TABLE `master_banks`
  ADD COLUMN `no_rekening` varchar(60) DEFAULT NULL AFTER `bank_name`,
  ADD COLUMN `warna` char(7) DEFAULT NULL AFTER `jenis`,
  ADD COLUMN `catatan_saldo` varchar(255) DEFAULT NULL AFTER `keterangan`;

-- Kode penomoran: 001/PB.HME/XI/2026 (bisa diubah di Pengaturan > Penomoran Dokumen).
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`)
VALUES ('prefix_pb', 'PB.HME', 'numbering');
