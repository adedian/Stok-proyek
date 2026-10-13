-- Inventory Kantor: kode barang baru memakai 3 pilihan Master Kode (bukan lagi INVT):
--   INV.HME / INV.EXGF / INV.EXSS  -> kode jadi mis. INV.HME.0001 (tanpa kode akhir).
-- Ketiganya baris code_configs biasa pada kelompok yang SAMA (item_inventory_kantor),
-- masing-masing counter sendiri (next_number) -- bukan kategori terpisah.
--
-- Kode lama (INVT.0001.INVT, TA.0001.INVT, ...) TIDAK diubah: tersimpan sebagai teks di
-- items.item_code, tanpa FK ke code_configs. Prefix lamanya hanya dinonaktifkan (status
-- 'inactive') supaya tidak muncul di dropdown barang baru; barang lama tetap bisa dicari,
-- dibuka, diedit, dan dipakai di transaksi. Counter prefix lama dibiarkan apa adanya.
--
-- Aman dijalankan ulang: INSERT IGNORE (UNIQUE entity_type+prefix) tidak menimpa counter
-- yang sudah berjalan.
INSERT IGNORE INTO code_configs (entity_type, prefix, master_code, digit_length, next_number, status) VALUES
    ('item_inventory_kantor', 'INV.HME',  '', 4, 1, 'active'),
    ('item_inventory_kantor', 'INV.EXGF', '', 4, 1, 'active'),
    ('item_inventory_kantor', 'INV.EXSS', '', 4, 1, 'active');

UPDATE code_configs
   SET status = 'inactive'
 WHERE entity_type = 'item_inventory_kantor'
   AND prefix NOT IN ('INV.HME', 'INV.EXGF', 'INV.EXSS');
