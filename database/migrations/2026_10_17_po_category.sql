-- ============================================================
-- Kategori Purchase Order: BARANG / JASA.
--
--   BARANG : alur lama (PO -> Pembayaran -> Penerimaan -> Validasi -> Stok).
--   JASA   : PO -> Pembayaran saja. TIDAK boleh muncul di Penerimaan Barang,
--            tidak menghasilkan mutasi stok, tidak masuk "Barang Menunggu Datang".
--
-- Additive only. PO yang sudah ada otomatis 'BARANG' (default kolom) sehingga
-- perilaku & data lama tidak berubah. Baris item PO Jasa memakai tabel
-- purchase_order_items yang sama (item_id NULL, item_name = deskripsi jasa),
-- jadi tidak ada tabel/kolom lain yang diubah.
-- ============================================================
ALTER TABLE `purchase_orders`
  ADD COLUMN `po_category` ENUM('BARANG','JASA') NOT NULL DEFAULT 'BARANG' AFTER `po_number_suffix`,
  ADD INDEX `idx_po_category` (`po_category`);
