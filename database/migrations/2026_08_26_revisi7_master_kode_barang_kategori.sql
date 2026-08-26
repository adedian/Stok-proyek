-- ============================================================
-- REVISI 7 (2026-08-26): Master Kode Barang dibedakan per kategori
-- (Stok Proyek / Stok Lampu / Inventory Kantor)
--
-- Sebelumnya Master Kode > Barang cuma punya SATU konfigurasi prefix untuk
-- semua barang (code_configs.entity_type = 'item'). Sekarang dipecah jadi 3
-- baris config (item_stok_proyek/item_stok_lampu/item_inventory_kantor),
-- masing-masing prefix & sequence SENDIRI -- tapi kode fisiknya TETAP satu
-- kolom (items.item_code) supaya keunikan kode tetap GLOBAL lintas kategori
-- (dipakai di PO/Inventory/Validasi/Laporan, lihat app/models/CodeConfig.php).
--
-- Config LAMA (entity_type='item') di-RENAME jadi 'item_stok_proyek' --
-- prefix & next_number PERSIS SAMA, TIDAK di-reset -- supaya barang yang
-- sudah ada sequence-nya (kategori default/mayoritas dipakai selama ini)
-- lanjut tanpa lompat/duplikat. Stok Lampu & Inventory Kantor SENGAJA belum
-- dikonfigurasi (baru diisi kalau admin atur prefix-nya sendiri di Master
-- Kode > Barang, sama seperti perilaku "belum dikonfigurasi" yang sudah ada).
--
-- items.stock_type default 'stok_proyek' untuk SEMUA baris lama -- barang
-- lama TIDAK direkategorikan otomatis ke Lampu/Kantor (tidak ada cara aman
-- menebak itu dari data yang ada), admin bisa edit manual barang per barang
-- kalau perlu. Kode barang LAMA tidak diubah sama sekali.
-- ============================================================

ALTER TABLE `items`
  ADD COLUMN `stock_type` ENUM('stok_proyek','stok_lampu','inventory_kantor') NOT NULL DEFAULT 'stok_proyek' AFTER `category_id`;

UPDATE `code_configs` SET `entity_type` = 'item_stok_proyek' WHERE `entity_type` = 'item';
