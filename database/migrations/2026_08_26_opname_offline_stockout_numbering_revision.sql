-- ============================================================
-- REVISI (2026-08-26): Penomoran Stok Opname, Pembelian Offline & Pengeluaran Barang
--
-- Disamakan ke format "001/SO.HME/X/2026", "001/OFF.HME/X/2026", dan
-- "001/STO.HME/X/2026" (urut/kode/bulan romawi/tahun, reset per tahun) --
-- pola yang sama dengan PO/Penerimaan Barang/Invoice/Surat Jalan (lihat
-- 2026_08_24_invoice_gr_sj_numbering_revision.sql & 2026_08_26_po_gr_numbering_revision.sql),
-- pakai tabel document_number_counters yang sudah ada (atomic, SELECT...FOR UPDATE).
-- Nomor yang SUDAH terbit (mis. SO/2026/08/0001 dari sesi sebelumnya) TIDAK
-- diubah/direnumber -- cuma dokumen BARU yang pakai format baru.
-- ============================================================

UPDATE `system_settings` SET `setting_value` = 'SO.HME' WHERE `setting_key` = 'prefix_opn';
UPDATE `system_settings` SET `setting_value` = 'STO.HME' WHERE `setting_key` = 'prefix_sto';

-- prefix_off TERNYATA tidak pernah ke-seed di system_settings (cuma ada sebagai
-- fallback PHP 'OFF/' di kode lama) -- INSERT kalau belum ada, UPDATE kalau
-- ternyata sudah ada (mis. instalasi lain yang sempat mengisinya manual).
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`)
SELECT 'prefix_off', 'OFF.HME', 'numbering'
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'prefix_off');

UPDATE `system_settings` SET `setting_value` = 'OFF.HME' WHERE `setting_key` = 'prefix_off';
