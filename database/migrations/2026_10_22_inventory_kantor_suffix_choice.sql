-- Koreksi 2026_10_21: Inventory Kantor TIDAK memakai prefix baru INV.HME/INV.EXGF/INV.EXSS.
-- Format kode tetap PREFIX.NOMOR.KODEAKHIR; hanya KODEAKHIR (dulu INVT) yang kini dipilih pengguna
-- per barang dari INV.HME / INV.EXGF / INV.EXSS (daftar di CodeConfig::$entities).
--
-- 1) Buang 3 baris "prefix" INV.* yang dibuat 2026_10_21 -- HANYA kalau belum ada barang yang
--    memakai kode berawalan INV. (kalau sudah ada, baris dibiarkan agar counter-nya tidak hilang).
-- 2) Aktifkan lagi prefix lama (INVT, TA, ...) dan kosongkan master_code barisnya
--    (kode akhir sekarang dari pilihan pengguna, bukan dari baris config).
-- Kode barang lama (mis. INVT.0001.INVT, TA.0001.INVT) TIDAK diubah.
DELETE FROM code_configs
 WHERE entity_type = 'item_inventory_kantor'
   AND prefix IN ('INV.HME', 'INV.EXGF', 'INV.EXSS')
   AND NOT EXISTS (SELECT 1 FROM items WHERE item_code LIKE 'INV.%');

UPDATE code_configs
   SET status = 'active', master_code = ''
 WHERE entity_type = 'item_inventory_kantor';
