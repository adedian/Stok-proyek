-- ============================================================
-- Kolom Kategori (catatan teks bebas) di baris item Purchase Order
--
-- Catatan per baris item PO, DIKETIK MANUAL di form Tambah/Edit PO -- TIDAK
-- terhubung ke kategori di Master Data > Barang. Ditampilkan HANYA di hasil
-- cetak PO (purchase_order/print.php), TIDAK di halaman list/detail PO.
--
-- Backfill di bawah HANYA mengisi nilai awal yang masuk akal untuk PO yang
-- SUDAH ada (diambil dari kategori master Barang saat migrasi dijalankan) --
-- setelah itu murni teks bebas & bisa diedit per PO.
-- ============================================================

ALTER TABLE `purchase_order_items`
  ADD COLUMN `category` VARCHAR(100) DEFAULT NULL AFTER `item_name`;

-- Backfill baris PO existing dari kategori Barang saat ini (yang punya item_id).
UPDATE `purchase_order_items` poi
JOIN `items` i ON i.id = poi.item_id
LEFT JOIN `item_categories` c ON c.id = i.category_id
SET poi.category = c.category_name
WHERE poi.item_id IS NOT NULL AND (poi.category IS NULL OR poi.category = '');
