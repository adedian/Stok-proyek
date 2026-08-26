-- ============================================================
-- REVISI (2026-08-26): Pengeluaran Barang untuk Client (Invoice Lampu)
--
-- stock_out sebelumnya WAJIB terikat Project (project_id NOT NULL) -- tidak
-- bisa mencatat pengeluaran barang untuk penjualan "Lampu" (client, TANPA
-- project, lihat sales_invoices.invoice_type). Sekarang project_id jadi
-- NULLABLE, ditambah destination_type (pola identik dengan
-- delivery_notes.destination_type yang sudah ada) + sales_invoice_id supaya
-- pengeluaran barang Lampu bisa ditaut ke Invoice Keluar spesifiknya
-- (bukan cuma nama client bebas) -- barangnya sendiri ambil dari stok
-- Kantor (Inventory::listByOfficeScope()), bukan stok project manapun.
-- ============================================================

ALTER TABLE `stock_out`
  MODIFY COLUMN `project_id` INT UNSIGNED NULL,
  ADD COLUMN `destination_type` ENUM('project','client') NOT NULL DEFAULT 'project' AFTER `project_id`,
  ADD COLUMN `sales_invoice_id` INT UNSIGNED NULL AFTER `destination_type`,
  ADD CONSTRAINT `fk_stock_out_sales_invoice` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices`(`id`);
