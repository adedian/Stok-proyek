-- ============================================================
-- Invoice Keluar: pilihan Kategori Invoice -- Invoice Barang atau Invoice Jasa.
-- Invoice Jasa tidak memakai Master Barang & tidak bisa diterbitkan dari Surat Jalan
-- (jasa tidak ada pengiriman fisik). Nomor, perhitungan, dan alur Tanda Terima tetap sama.
-- DEFAULT 'barang' supaya semua invoice yang sudah ada otomatis tetap Invoice Barang.
-- Additive only.
-- ============================================================
ALTER TABLE `sales_invoices`
  ADD COLUMN `invoice_category` ENUM('barang','jasa') NOT NULL DEFAULT 'barang' AFTER `document_kind`;
