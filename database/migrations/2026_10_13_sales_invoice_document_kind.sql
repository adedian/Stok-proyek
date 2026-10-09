-- ============================================================
-- Invoice Keluar: pilihan Jenis Dokumen -- Invoice biasa atau Proforma Invoice.
-- Hanya mengubah JUDUL pada cetak (INVOICE -> PROFORMA INVOICE); nomor, isi,
-- perhitungan, dan alur Tanda Terima tetap sama. DEFAULT 'invoice' supaya semua
-- invoice yang sudah ada otomatis tetap Invoice biasa. Additive only.
-- ============================================================
ALTER TABLE `sales_invoices`
  ADD COLUMN `document_kind` ENUM('invoice','proforma') NOT NULL DEFAULT 'invoice' AFTER `invoice_type`;
