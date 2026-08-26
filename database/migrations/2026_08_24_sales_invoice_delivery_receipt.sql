-- ============================================================
-- REVISI (2026-08-24): Template Invoice/Surat Jalan/Tanda Terima + cetak terpusat.
--
-- KONTEKS (lihat audit lengkap yang disepakati sebelum migration ini ditulis):
-- 1) Tabel `invoices` yang sudah ada adalah invoice MASUK (AP: supplier -> HME,
--    terikat purchase_order_id/supplier_id, cuma 1 nominal + file upload, sudah
--    dipakai InvoiceValidation untuk auto-match PO/pembayaran/goods-receipt).
--    Template yang diminta adalah invoice KELUAR (AR: HME -> client) dengan baris
--    item + PPN -- arah & bentuk data berbeda total, jadi dibuat TABEL BARU
--    (sales_invoices/sales_invoice_items), `invoices` (AP) TIDAK disentuh sama
--    sekali supaya validasi otomatis yang sudah jalan tidak berisiko rusak.
-- 2) `stock_out` (Pengeluaran Barang) = 1 baris = 1 barang, wajib project_id,
--    tanpa client/kendaraan/driver/penerima. Surat Jalan butuh 1 dokumen berisi
--    banyak barang + 1 tanda tangan penerima. Solusi: TABEL HEADER BARU
--    (delivery_notes) yang MENGELOMPOKKAN beberapa baris stock_out yang sudah
--    ada (lewat kolom baru stock_out.delivery_note_id, nullable) -- bukan
--    mengubah alur input Pengeluaran Barang yang sudah berjalan.
-- 3) Tanda Terima (sesuai template Draft Tanda Terima.pdf) adalah tanda terima
--    PENAGIHAN: daftar No. Invoice + Faktur Pajak + No. Surat Jalan + Total per
--    baris, bukan daftar barang. Sumbernya sales_invoices (poin 1), dikemas di
--    collection_receipts/collection_receipt_items.
--
-- Semua perubahan ADDITIVE: tabel baru + 1 kolom nullable baru di stock_out.
-- Tidak ada DROP/TRUNCATE, tidak ada kolom wajib baru di tabel yang sudah berisi
-- data transaksi.
-- ============================================================

-- 1) Invoice Keluar (Sales/AR Invoice) --------------------------------------
CREATE TABLE IF NOT EXISTS `sales_invoices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `client_id` INT UNSIGNED NOT NULL,
  `project_id` INT UNSIGNED NULL,
  `invoice_date` DATE NOT NULL,
  `contract_number` VARCHAR(100) NULL,
  `contract_date` DATE NULL,
  `ppn_percent` DECIMAL(5,2) NOT NULL DEFAULT 11.00,
  `subtotal` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ppn_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `tax_invoice_number` VARCHAR(50) NULL COMMENT 'No. Faktur Pajak, opsional',
  `signature_id` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_si_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
  CONSTRAINT `fk_si_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`),
  CONSTRAINT `fk_si_signature` FOREIGN KEY (`signature_id`) REFERENCES `signatures`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sales_invoice_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sales_invoice_id` INT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `qty` DECIMAL(15,2) NOT NULL,
  `unit` VARCHAR(30) NOT NULL,
  `unit_price` DECIMAL(18,2) NOT NULL,
  `subtotal` DECIMAL(18,2) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sii_invoice` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Surat Jalan (Delivery Note) header, mengelompokkan baris stock_out -----
CREATE TABLE IF NOT EXISTS `delivery_notes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `delivery_number` VARCHAR(50) NOT NULL UNIQUE,
  `delivery_date` DATE NOT NULL,
  `destination_type` ENUM('project','client') NOT NULL DEFAULT 'project',
  `client_id` INT UNSIGNED NULL,
  `project_id` INT UNSIGNED NULL,
  `destination_name` VARCHAR(200) NULL COMMENT 'Fallback teks bebas kalau tujuan bukan client/project terdaftar',
  `vehicle_number` VARCHAR(50) NULL,
  `driver_name` VARCHAR(150) NULL,
  `sender_name` VARCHAR(150) NULL,
  `recipient_name` VARCHAR(150) NULL,
  `signature_id` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_dn_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
  CONSTRAINT `fk_dn_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`),
  CONSTRAINT `fk_dn_signature` FOREIGN KEY (`signature_id`) REFERENCES `signatures`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `stock_out`
  ADD COLUMN `delivery_note_id` INT UNSIGNED NULL AFTER `project_id`,
  ADD CONSTRAINT `fk_so_delivery_note` FOREIGN KEY (`delivery_note_id`) REFERENCES `delivery_notes`(`id`) ON DELETE SET NULL;

-- 3) Tanda Terima (Collection Receipt) ---------------------------------------
CREATE TABLE IF NOT EXISTS `collection_receipts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `receipt_number` VARCHAR(50) NOT NULL UNIQUE,
  `client_id` INT UNSIGNED NOT NULL,
  `receipt_date` DATE NOT NULL,
  `recipient_name` VARCHAR(150) NULL,
  `signature_id` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_cr_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
  CONSTRAINT `fk_cr_signature` FOREIGN KEY (`signature_id`) REFERENCES `signatures`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `collection_receipt_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `collection_receipt_id` INT UNSIGNED NOT NULL,
  `sales_invoice_id` INT UNSIGNED NOT NULL,
  `delivery_note_id` INT UNSIGNED NULL,
  `total_amount` DECIMAL(18,2) NOT NULL COMMENT 'Snapshot total invoice saat ditambahkan ke tanda terima ini',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cri_receipt` FOREIGN KEY (`collection_receipt_id`) REFERENCES `collection_receipts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cri_invoice` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices`(`id`),
  CONSTRAINT `fk_cri_delivery_note` FOREIGN KEY (`delivery_note_id`) REFERENCES `delivery_notes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Nomor dokumen (pola PREFIX/YYYY/MM/0001, konsisten dgn prefix_po/prefix_sto dkk) --
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`)
VALUES
  ('prefix_sls', 'INV/', 'numbering'),
  ('prefix_sj', 'SJ/', 'numbering'),
  ('prefix_tt', 'TT/', 'numbering'),
  ('company_bank_name', '', 'company'),
  ('company_bank_account', '', 'company')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- 5) Permission baru: lihat config/permissions.php (sales_invoice, delivery_note,
--    collection_receipt, + 'client'.'quick_add') -- tidak ada perubahan DB untuk RBAC,
--    dicatat di sini untuk riwayat revisi.
