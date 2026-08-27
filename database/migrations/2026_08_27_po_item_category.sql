-- ============================================================
-- Kolom Kategori (snapshot) di baris item Purchase Order
--
-- Ditampilkan HANYA di hasil cetak PO (purchase_order/print.php), TIDAK di
-- halaman list/detail PO. Diisi otomatis (read-only di form) dari kategori
-- Barang yang dipilih -- disimpan sebagai teks snapshot supaya cetakan PO
-- lama tetap benar walau kategori master Barang berubah/terhapus nanti.
-- ============================================================

ALTER TABLE `purchase_order_items`
  ADD COLUMN `category` VARCHAR(100) DEFAULT NULL AFTER `item_name`;

-- Backfill baris PO existing dari kategori Barang saat ini (yang punya item_id).
UPDATE `purchase_order_items` poi
JOIN `items` i ON i.id = poi.item_id
LEFT JOIN `item_categories` c ON c.id = i.category_id
SET poi.category = c.category_name
WHERE poi.item_id IS NOT NULL AND (poi.category IS NULL OR poi.category = '');
