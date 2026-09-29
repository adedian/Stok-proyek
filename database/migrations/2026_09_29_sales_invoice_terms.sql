-- ============================================================
-- REVISI 10 FASE 5: Invoice Keluar -- Tagihan DP tunggal -> Termin bertahap
-- ============================================================
-- Dua tabel baru:
--   1. sales_invoice_terms    -- breakdown termin per invoice (label, %, nominal,
--                                 PPN, total, jatuh tempo opsional). Menggantikan
--                                 peran "1 baris DP" yang dulu melekat langsung di
--                                 sales_invoices (dp_percentage_id/dp_percentage/
--                                 dp_amount) -- kolom2 itu TETAP ADA (additive-only,
--                                 aturan repo ini) dan sekarang menyimpan AGREGAT
--                                 (SUM) dari semua termin invoice tsb, supaya kode
--                                 lama yang membaca total_amount/ppn_amount/dp_amount
--                                 (Tanda Terima, Laporan Invoice Keluar) tetap benar
--                                 tanpa perubahan.
--   2. sales_invoice_payments -- uang masuk per termin (analog tabel `payments`
--                                 yang sudah ada utk Purchase Order/AP, versi AR).
--                                 Status lunas/sebagian/belum dihitung dari SUM
--                                 baris ini vs sales_invoice_terms.total_amount,
--                                 BUKAN kolom status tersimpan (konsisten dgn
--                                 Payment::resolveStatus() pattern).
--
-- Backfill: setiap invoice yang sudah ada (dp_percentage_id/dp_percentage/
-- dp_amount/ppn_amount/total_amount sudah terisi) diberi 1 baris termin "Tagihan
-- DP" senilai data yang sudah ada -- supaya invoice lama langsung tampil benar di
-- UI baru tanpa migrasi data manual, dan uang yang sudah pernah diterima (kalau
-- ada catatan di luar sistem) tidak ikut diklaim "lunas" otomatis (baris
-- sales_invoice_payments TETAP kosong, harus dicatat manual kalau perlu).
-- ============================================================

CREATE TABLE `sales_invoice_terms` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sales_invoice_id` INT UNSIGNED NOT NULL,
  `term_no` INT UNSIGNED NOT NULL DEFAULT 1,
  `dp_percentage_id` INT UNSIGNED NULL,   -- preset yang dipakai (opsional, hanya jejak audit -- lihat komentar di SalesInvoiceController)
  `label` VARCHAR(150) NOT NULL,
  `percentage` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `ppn_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `due_date` DATE NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sit_invoice` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices`(`id`),
  CONSTRAINT `fk_sit_dp` FOREIGN KEY (`dp_percentage_id`) REFERENCES `dp_percentages`(`id`) ON DELETE SET NULL,
  INDEX `idx_sit_invoice` (`sales_invoice_id`)
) ENGINE=InnoDB;

CREATE TABLE `sales_invoice_payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sales_invoice_term_id` INT UNSIGNED NOT NULL,
  `payment_number` VARCHAR(50) NOT NULL UNIQUE,
  `amount` DECIMAL(18,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `proof_file` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT UNSIGNED NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_sip_term` FOREIGN KEY (`sales_invoice_term_id`) REFERENCES `sales_invoice_terms`(`id`),
  CONSTRAINT `fk_sip_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_sip_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`),
  INDEX `idx_sip_term` (`sales_invoice_term_id`)
) ENGINE=InnoDB;

INSERT INTO `sales_invoice_terms`
    (`sales_invoice_id`, `term_no`, `dp_percentage_id`, `label`, `percentage`, `amount`, `ppn_amount`, `total_amount`, `created_at`)
SELECT `id`, 1, `dp_percentage_id`, 'Tagihan DP', `dp_percentage`, `dp_amount`, `ppn_amount`, `total_amount`, `created_at`
FROM `sales_invoices`;
