-- ============================================================
-- REVISI (2026-08-26): Hapus modul Lokasi Penyimpanan & Invoice (AP)
--
-- 1) Lokasi Penyimpanan (storage_locations) -- tidak pernah dipakai/dipilih
--    oleh modul transaksi manapun sejak dibuat (Fase Master Data, 2026-08-14).
--    0 baris data. Tidak ada tabel lain yang FK ke sini.
-- 2) Invoice (AP, invoice masuk dari supplier -- tabel invoices &
--    invoice_validations) -- sudah digantikan total oleh "Invoice Keluar"
--    (sales_invoices) sejak 2026-08-24. 0 baris AKTIF (2 baris invoices yang
--    ada semua sudah soft-deleted). Menu sidebar-nya sudah dihilangkan sejak
--    2026-08-24, sekarang modulnya (kode + tabel) dihapus total karena
--    membingungkan (2 konsep "Invoice" berbeda tanpa hubungan apapun).
--
-- Data yang masih ada di 3 tabel ini (termasuk yang soft-deleted) sudah
-- di-backup ke storage/backups/pre_remove_storagelocation_apinvoice_*.sql
-- sebelum migration ini dijalankan.
--
-- Urutan DROP wajib: invoice_validations dulu (FK ke invoices), baru invoices
-- -- storage_locations independen, urutan bebas.
-- ============================================================

DROP TABLE IF EXISTS `invoice_validations`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `storage_locations`;
