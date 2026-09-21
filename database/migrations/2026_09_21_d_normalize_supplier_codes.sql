-- =========================================================================
-- 2026_09_21_d_normalize_supplier_codes.sql
--
-- Samakan SEMUA kode Supplier ke format Master Kode v3 yang sekarang berlaku
-- (PREFIX.NOMOR.MASTERCODE, lihat CodeConfig::nextCode()) -- sebelumnya ada
-- 3 format tercampur: "SUP-0001" (4 digit lama), "SUP-00003".."SUP-00009"
-- (5 digit, transisi), dan "SUP.00011.SUP" (format baru, sudah benar).
--
-- Diurutkan ulang berdasarkan id (urutan dibuat) jadi SUP.00001.SUP s/d
-- SUP.00010.SUP -- rapi tanpa lompatan nomor. FK-safe: supplier_code HANYA
-- ada di tabel suppliers sendiri (sudah diaudit -- tidak ada tabel lain yang
-- menyimpan supplier_code sebagai teks, semua relasi pakai supplier_id FK).
--
-- code_configs.next_number (prefix SUP) disesuaikan ke 11 supaya Supplier
-- berikutnya otomatis lanjut dari SUP.00011.SUP, bukan bentrok/lompat.
-- =========================================================================

UPDATE `suppliers` SET `supplier_code` = 'SUP.00001.SUP' WHERE `id` = 1;
UPDATE `suppliers` SET `supplier_code` = 'SUP.00002.SUP' WHERE `id` = 2;
UPDATE `suppliers` SET `supplier_code` = 'SUP.00003.SUP' WHERE `id` = 7;
UPDATE `suppliers` SET `supplier_code` = 'SUP.00004.SUP' WHERE `id` = 8;
UPDATE `suppliers` SET `supplier_code` = 'SUP.00005.SUP' WHERE `id` = 9;
UPDATE `suppliers` SET `supplier_code` = 'SUP.00006.SUP' WHERE `id` = 10;
UPDATE `suppliers` SET `supplier_code` = 'SUP.00007.SUP' WHERE `id` = 11;
UPDATE `suppliers` SET `supplier_code` = 'SUP.00008.SUP' WHERE `id` = 12;
UPDATE `suppliers` SET `supplier_code` = 'SUP.00009.SUP' WHERE `id` = 13;
UPDATE `suppliers` SET `supplier_code` = 'SUP.00010.SUP' WHERE `id` = 17;

UPDATE `code_configs` SET `next_number` = 11 WHERE `entity_type` = 'supplier' AND `prefix` = 'SUP';
