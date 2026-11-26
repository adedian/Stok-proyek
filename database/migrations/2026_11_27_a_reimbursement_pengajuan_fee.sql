-- ============================================================================
-- MODUL BARU: REIMBURSEMENT dan PENGAJUAN FEE (acuan: Reimbursement.xlsx & Pengajuan fee.xlsx)
--
-- Reimbursement  = "LAPORAN BIAYA REIMBURSE": header (Nama, Tanggal, Divisi, Kota, Tugas, B S/kasbon),
--                  daftar PENGELUARAN (tanggal, keterangan, kategori, jumlah), TOTAL PENGELUARAN,
--                  TOTAL KASBON - PENGELUARAN. Diperiksa Nissa/Purwati (salah satu cukup) lalu
--                  diteruskan otomatis ke Pengajuan Budget yang sudah ada (tanpa mengubah alurnya).
-- Pengajuan Fee  = lembar "PENGAJUAN FEE": header invoice/customer/sales/penerima fee, tabel barang
--                  (QTY x HARGA = TOTAL; FEE = TOTAL/(1+PPN) x Fee%), potong PPh, total fee diterima.
--                  Modul MANDIRI (tidak terhubung ke Pengajuan Budget).
--
-- Additive only: tidak ada tabel/kolom lama yang diubah/dihapus kecuali dua kolom referensi sumber
-- di pengajuan_budgets (source_type/source_id, NULL untuk data lama).
-- ============================================================================

-- ------------------------------ REIMBURSEMENT -------------------------------
CREATE TABLE IF NOT EXISTS `reimbursements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reimb_number` varchar(40) NOT NULL,
  `reimb_date` date NOT NULL,                       -- "Tanggal" pada header Excel
  `employee_name` varchar(150) NOT NULL,            -- "Nama"
  `division` varchar(100) DEFAULT NULL,             -- "Divisi"
  `city` varchar(100) DEFAULT NULL,                 -- "Kota"
  `task` varchar(255) DEFAULT NULL,                 -- "Tugas"
  `kasbon_amount` decimal(18,2) NOT NULL DEFAULT 0.00,  -- "B S" (kasbon / bon sementara), opsional
  `total_expense` decimal(18,2) NOT NULL DEFAULT 0.00,  -- TOTAL PENGELUARAN (=SUM jumlah)
  `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,     -- pengeluaran - kasbon; >0 = kurang bayar yang diteruskan
  `status` enum('draft','menunggu_pemeriksaan','ditolak','disetujui','diteruskan') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,      -- pemeriksa (Nissa/Purwati) yang memutuskan
  `reviewed_at` datetime DEFAULT NULL,
  `review_decision` enum('setuju','tolak') DEFAULT NULL,
  `review_note` varchar(500) DEFAULT NULL,
  `pengajuan_budget_id` int(10) unsigned DEFAULT NULL,  -- hasil penerusan (satu reimbursement = maks 1 pengajuan budget)
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reimb_number` (`reimb_number`),
  UNIQUE KEY `uq_reimb_pb` (`pengajuan_budget_id`),
  KEY `idx_reimb_status` (`status`),
  KEY `idx_reimb_creator` (`created_by`),
  KEY `idx_reimb_date` (`reimb_date`),
  CONSTRAINT `fk_reimb_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_reimb_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reimb_pb` FOREIGN KEY (`pengajuan_budget_id`) REFERENCES `pengajuan_budgets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Baris PENGELUARAN. amount_expr = ekspresi penjumlahan asli bila jumlah diisi seperti Excel
-- (mis. "9500+9000+9500+3000+6000"); amount = hasil hitungnya.
CREATE TABLE IF NOT EXISTS `reimbursement_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reimbursement_id` int(10) unsigned NOT NULL,
  `line_no` smallint(5) unsigned NOT NULL DEFAULT 1,
  `expense_date` date NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,      -- kelompok (mis. "Akomodasi") = Master Kategori Biaya
  `description` varchar(255) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `amount_expr` varchar(255) DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,           -- bukti/lampiran (opsional), folder uploads/reimbursement
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rbi_reimb` (`reimbursement_id`),
  KEY `idx_rbi_cat` (`category_id`),
  CONSTRAINT `fk_rbi_reimb` FOREIGN KEY (`reimbursement_id`) REFERENCES `reimbursements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rbi_cat` FOREIGN KEY (`category_id`) REFERENCES `cash_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Riwayat: pembuatan, perubahan, pengajuan, pemeriksaan (keputusan + catatan), penerusan ke Pengajuan Budget.
CREATE TABLE IF NOT EXISTS `reimbursement_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reimbursement_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rmbh_reimb` (`reimbursement_id`),
  CONSTRAINT `fk_rmbh_reimb` FOREIGN KEY (`reimbursement_id`) REFERENCES `reimbursements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rmbh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Referensi sumber di Pengajuan Budget (dari Reimbursement). UNIQUE (source_type, source_id) = satu sumber
-- tidak pernah menghasilkan dua pengajuan budget (baris lama: NULL -> tidak terpengaruh).
ALTER TABLE `pengajuan_budgets`
  ADD COLUMN `source_type` varchar(30) DEFAULT NULL AFTER `notes`,
  ADD COLUMN `source_id` int(10) unsigned DEFAULT NULL AFTER `source_type`,
  ADD UNIQUE KEY `uq_pb_source` (`source_type`, `source_id`);

-- ------------------------------ PENGAJUAN FEE -------------------------------
CREATE TABLE IF NOT EXISTS `pengajuan_fees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fee_number` varchar(40) NOT NULL,
  `fee_date` date NOT NULL,                          -- tanggal dokumen ("Surabaya, 15 September 2026")
  `city` varchar(100) DEFAULT NULL,                  -- "Surabaya"
  `invoice_number` varchar(60) DEFAULT NULL,         -- "Invoice no."
  `customer_name` varchar(150) DEFAULT NULL,         -- "Customer"
  `sales_name` varchar(150) DEFAULT NULL,            -- "Sales"
  `billing_status` varchar(120) DEFAULT NULL,        -- "Status Tagihan" (mis. "Lunas (04/09/2026)")
  `recipient_name` varchar(150) DEFAULT NULL,        -- "Penerima Fee"
  `recipient_tax_id` varchar(40) DEFAULT NULL,       -- "NPWP / NIK"
  `fee_percent` decimal(6,3) NOT NULL DEFAULT 10.000,  -- "Fee 10% dari DPP"
  `ppn_percent` decimal(6,3) NOT NULL DEFAULT 11.000,  -- pembagi 1.11 pada rumus Excel
  `pph_percent` decimal(6,3) NOT NULL DEFAULT 2.500,   -- "Potong Pph" 2.5%
  `note` varchar(500) DEFAULT NULL,                  -- "NOTE = Fee : Lampu 963.000 / pcs"
  `nb` varchar(500) DEFAULT NULL,                    -- "NB : Fee 10% dari DPP"
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,  -- TOTAL (sum kolom TOTAL)
  `total_fee` decimal(18,2) NOT NULL DEFAULT 0.00,     -- TOTAL kolom FEE
  `pph_amount` decimal(18,2) NOT NULL DEFAULT 0.00,    -- Potong Pph (nilai positif; tampil negatif)
  `net_fee` decimal(18,2) NOT NULL DEFAULT 0.00,       -- Total Fee diterima
  `status` enum('draft','diajukan','diperiksa','disetujui','ditolak') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `checked_by` int(10) unsigned DEFAULT NULL,        -- "Diperiksa"
  `checked_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,       -- "Disetujui"
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(10) unsigned DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `reject_note` varchar(500) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned NOT NULL,            -- "Dibuat"
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_number` (`fee_number`),
  KEY `idx_fee_status` (`status`),
  KEY `idx_fee_creator` (`created_by`),
  KEY `idx_fee_date` (`fee_date`),
  CONSTRAINT `fk_fee_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_fee_checker` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fee_rejecter` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Baris BARANG: total = harga x qty ; fee = total / (1 + PPN%) x Fee%  (rumus Excel, dibulatkan per sen)
CREATE TABLE IF NOT EXISTS `pengajuan_fee_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fee_id` int(10) unsigned NOT NULL,
  `line_no` smallint(5) unsigned NOT NULL DEFAULT 1,
  `item_name` varchar(255) NOT NULL,
  `qty` decimal(18,2) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `price` decimal(18,2) NOT NULL,
  `total` decimal(18,2) NOT NULL,
  `fee` decimal(18,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fei_fee` (`fee_id`),
  CONSTRAINT `fk_fei_fee` FOREIGN KEY (`fee_id`) REFERENCES `pengajuan_fees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pengajuan_fee_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fee_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feh_fee` (`fee_id`),
  CONSTRAINT `fk_feh_fee` FOREIGN KEY (`fee_id`) REFERENCES `pengajuan_fees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Kode penomoran: 001/RMB.HME/XI/2026 dan 001/FEE.HME/XI/2026 (bisa diubah di Pengaturan > Penomoran Dokumen).
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
  ('prefix_reimb', 'RMB.HME', 'numbering'),
  ('prefix_fee',   'FEE.HME', 'numbering');
