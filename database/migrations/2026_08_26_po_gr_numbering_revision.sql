-- ============================================================
-- REVISI (2026-08-26): Penomoran Purchase Order & Penerimaan Barang
--
-- Purchase Order & Penerimaan Barang disamakan ke format
-- "001/PO.HME/X/2026" dan "001/LPB.HME/X/2026" (urut/kode/bulan romawi/tahun,
-- reset per tahun) -- pola yang sama dengan Invoice Keluar & Surat Jalan
-- (lihat 2026_08_24_invoice_gr_sj_numbering_revision.sql), pakai tabel
-- document_number_counters yang sudah ada (atomic, SELECT...FOR UPDATE).
-- Nomor yang SUDAH terbit (mis. PO/2026/08/0001, GR/2026/08/0001 dari sesi
-- sebelumnya) TIDAK diubah/direnumber -- cuma dokumen BARU yang pakai format baru.
-- ============================================================

UPDATE `system_settings` SET `setting_value` = 'PO.HME' WHERE `setting_key` = 'prefix_po';
UPDATE `system_settings` SET `setting_value` = 'LPB.HME' WHERE `setting_key` = 'prefix_gr';
