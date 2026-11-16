-- Master Rekening Tujuan (penerima pembayaran) -- sumber dropdown "Rekening Tujuan"
-- pada Pengajuan Budget (Master Data > Master Rekening Tujuan).
CREATE TABLE IF NOT EXISTS `master_rekening_tujuan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nama_penerima` varchar(150) NOT NULL,
  `bank_tujuan` varchar(100) DEFAULT NULL,
  `no_rekening` varchar(60) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mrt_nama` (`nama_penerima`),
  KEY `idx_mrt_norek` (`no_rekening`),
  KEY `fk_mrt_deleted_by` (`deleted_by`),
  KEY `fk_mrt_created_by` (`created_by`),
  CONSTRAINT `fk_mrt_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mrt_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Baris Pengajuan Budget tersambung ke master (opsional; tetap boleh ketik manual).
-- Kolom `tujuan` (teks) tetap disimpan sebagai snapshot supaya laporan lama tidak berubah bila master diedit.
ALTER TABLE `pengajuan_budget_items`
  ADD COLUMN `tujuan_id` int(10) unsigned DEFAULT NULL AFTER `tujuan`,
  ADD KEY `idx_pbi_tujuan` (`tujuan_id`),
  ADD CONSTRAINT `fk_pbi_tujuan` FOREIGN KEY (`tujuan_id`) REFERENCES `master_rekening_tujuan` (`id`) ON DELETE SET NULL;
