-- ============================================================
-- REVISI (2026-08-24, lanjutan): Invoice/Penerimaan/Surat Jalan/Penomoran/Tanda Terima
--
-- 1) Upload Invoice pada Penerimaan Barang -- goods_receipts.invoice_file, pola
--    identik dengan goods_receipts.photo_goods yang sudah ada (handleFileUpload()).
--    invoices.goods_receipt_id (nullable) supaya invoice AP yang dibuat belakangan
--    bisa DIHUBUNGKAN ke penerimaan yang sudah punya file, bukan upload ulang/duplicate.
-- 2) Surat Jalan butuh field Kota terpisah dari nama tujuan (project/client) --
--    baris penutup "BARANG SUDAH DITERIMA... di [Kota], [Tanggal]" sebelumnya salah
--    memakai nama project/tujuan, bukan kota.
-- 3) Penomoran Invoice Keluar & Surat Jalan diubah ke format
--    "001/INV.HME/VIII/2026" (urut/kode/bulan romawi/tahun, reset per tahun).
--    Counter lama (LIKE-prefix parsing di generateInvoiceNumber()/generateDeliveryNumber(),
--    TANPA locking) diganti mekanisme atomic (SELECT...FOR UPDATE) via tabel counter
--    baru -- pola yang sama dengan CodeConfig::nextCode() yang sudah terbukti aman
--    dari race condition. Nomor yang SUDAH terbit (mis. INV/2026/08/0001 dari sesi
--    sebelumnya) TIDAK diubah/direnumber -- nomor dokumen tidak pernah berubah
--    retroaktif, cuma dokumen BARU yang pakai format baru.
-- 4) Master Rekening (company_bank_accounts) menggantikan 2 field flat
--    company_bank_name/company_bank_account (baru ditambahkan sesi sebelumnya,
--    belum pernah dipakai/diisi -- aman diganti) supaya bisa >1 rekening dengan
--    1 yang aktif dipakai di Invoice.
-- Semua perubahan ADDITIVE. Tidak ada DROP/TRUNCATE/hapus histori.
-- ============================================================

-- 1) Upload Invoice pada Penerimaan Barang -----------------------------------
ALTER TABLE `goods_receipts`
  ADD COLUMN `invoice_file` VARCHAR(255) NULL AFTER `photo_goods`;

ALTER TABLE `invoices`
  ADD COLUMN `goods_receipt_id` INT UNSIGNED NULL AFTER `purchase_order_id`,
  ADD CONSTRAINT `fk_invoice_goods_receipt` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts`(`id`);

-- 2) Kota pada Surat Jalan ----------------------------------------------------
ALTER TABLE `delivery_notes`
  ADD COLUMN `city` VARCHAR(100) NULL AFTER `destination_name`;

-- 3) Penomoran atomic (Invoice Keluar & Surat Jalan) -------------------------
CREATE TABLE IF NOT EXISTS `document_number_counters` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `doc_type` VARCHAR(30) NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `next_number` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_doc_year` (`doc_type`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- prefix_sls/prefix_sj sekarang menyimpan KODE dokumen (mis. "INV.HME"), bukan lagi
-- prefix path "INV/" -- format urut/romawi/tahun sekarang terstruktur, bukan string bebas.
UPDATE `system_settings` SET `setting_value` = 'INV.HME' WHERE `setting_key` = 'prefix_sls';
UPDATE `system_settings` SET `setting_value` = 'SJ.HME' WHERE `setting_key` = 'prefix_sj';

-- 4) Master Rekening -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `company_bank_accounts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bank_name` VARCHAR(150) NOT NULL,
  `account_number` VARCHAR(50) NOT NULL,
  `account_holder_name` VARCHAR(150) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
