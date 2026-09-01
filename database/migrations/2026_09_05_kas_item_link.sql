-- ============================================================
-- KAS: baris kategori stok WAJIB tertaut ke master Barang (2026-09-05)
-- ============================================================
-- Lanjutan 2026_09_03 / 2026_09_04. Permintaan user: baris rincian Kas yang
-- kategorinya "pembelian barang" (affects_stock: Material Proyek / Inventory
-- Kantor / Inventory Teknik) sekarang WAJIB memilih Barang dari master
-- (seperti form Tambah Barang di PO/Pembelian Offline), lengkap tombol "+"
-- quick-add. Stok yang dikreditkan dicatat atas NAMA BARANG MASTER supaya
-- otomatis nyambung ke Stok Barang & Laporan Stok Barang (join items.item_name).
-- Uraian & Satuan baris tetap bebas (uraian = catatan, satuan = dropdown).
--
-- Kolom item_id sempat dibuang di 2026_09_03; di sini ditambahkan kembali.
-- Baris non-stok (Biaya Operasional) tetap item_id NULL.
-- ============================================================

USE `db_stok_proyek`;

ALTER TABLE `cash_transaction_items`
  ADD COLUMN `item_id` INT UNSIGNED NULL DEFAULT NULL AFTER `cash_category_id`,
  ADD KEY `fk_cti_item` (`item_id`),
  ADD CONSTRAINT `fk_cti_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`);
