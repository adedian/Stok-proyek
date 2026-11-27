-- ============================================================
-- Rekap PPN: PPN Masukan + PPN Keluaran dalam satu menu (acuan: sheet "PPN Masukan" & "PPN Keluaran"
-- pada Pengajuan fee.xlsx). Register faktur pajak yang diisi manual oleh Accounting -- TIDAK menyentuh
-- Purchase Order / Invoice Keluar / Kas. Additive only (4 tabel baru).
--
-- ppn_masukan       : 1 baris = 1 faktur pajak MASUKAN (dari vendor). DPP diisi; DPP Nilai Lain, PPN, Jumlah dihitung
--                     (DPP Nilai Lain = DPP x 11/12, PPN = DPP Nilai Lain x 12%, Jumlah = DPP + PPN). Masa/Tahun = masa pajak
--                     (boleh beda dari bulan tanggal faktur, seperti di Excel).
-- ppn_keluaran      : 1 baris = 1 faktur pajak KELUARAN (ke customer); rincian barangnya di ppn_keluaran_items.
--                     Masa pajak = bulan/tahun dari entry_date (di Excel tidak ada kolom Masa).
-- ppn_keluaran_items: harga satuan SUDAH termasuk PPN 11%: Subtotal = Qty x Harga, DPP = Subtotal / 1,11,
--                     DPP Nilai Lain = DPP x 11/12, PPN = Subtotal - DPP, Total = Subtotal.
-- ppn_periods       : isian per masa -- "Lebih Bayar masa sebelumnya" (kompensasi) + catatan.
-- ============================================================

CREATE TABLE IF NOT EXISTS `ppn_masukan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `faktur_number` varchar(40) NOT NULL COMMENT 'No. Faktur Pajak',
  `faktur_date` date NOT NULL,
  `npwp` varchar(30) DEFAULT NULL,
  `vendor_name` varchar(150) NOT NULL,
  `masa` tinyint(3) unsigned NOT NULL COMMENT 'Masa pajak (1-12)',
  `tahun` smallint(5) unsigned NOT NULL,
  `dpp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `dpp_nilai_lain` decimal(18,2) NOT NULL DEFAULT 0.00,
  `ppn_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `project_name` varchar(150) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ppn_masukan_periode` (`tahun`,`masa`),
  KEY `idx_ppn_masukan_faktur` (`faktur_number`),
  KEY `idx_ppn_masukan_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ppn_keluaran` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entry_date` date NOT NULL COMMENT 'Tanggal (menentukan masa pajak)',
  `invoice_number` varchar(60) DEFAULT NULL,
  `faktur_number` varchar(40) DEFAULT NULL COMMENT 'No. Faktur Pajak',
  `customer_name` varchar(150) NOT NULL,
  `npwp` varchar(30) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `total_subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_dpp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_dpp_nilai_lain` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_ppn` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ppn_keluaran_date` (`entry_date`),
  KEY `idx_ppn_keluaran_faktur` (`faktur_number`),
  KEY `idx_ppn_keluaran_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ppn_keluaran_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ppn_keluaran_id` int(10) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `qty` decimal(14,2) NOT NULL DEFAULT 0.00,
  `pricelist` decimal(18,2) DEFAULT NULL COMMENT 'Harga daftar (opsional) -- hanya untuk menghitung % diskon',
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Harga satuan, SUDAH termasuk PPN',
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `dpp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `dpp_nilai_lain` decimal(18,2) NOT NULL DEFAULT 0.00,
  `ppn_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_ppn_keluaran_items_parent` (`ppn_keluaran_id`),
  CONSTRAINT `fk_ppn_keluaran_items_parent` FOREIGN KEY (`ppn_keluaran_id`) REFERENCES `ppn_keluaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ppn_periods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tahun` smallint(5) unsigned NOT NULL,
  `masa` tinyint(3) unsigned NOT NULL,
  `lebih_bayar_sebelumnya` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Lebih bayar masa sebelumnya yang dikompensasikan ke masa ini',
  `notes` varchar(300) DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ppn_periods` (`tahun`,`masa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
