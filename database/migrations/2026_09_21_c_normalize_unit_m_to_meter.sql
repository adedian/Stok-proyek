-- =========================================================================
-- 2026_09_21_c_normalize_unit_m_to_meter.sql
--
-- Normalisasi data lama: satuan disingkat "m" (dari sebelum Master Satuan
-- distandarkan ke "Meter") diseragamkan jadi "Meter" -- Master Satuan
-- (`units`) sendiri SUDAH benar (cuma ada 1 baris "Meter", tidak ada "m"),
-- ini murni membersihkan SNAPSHOT teks satuan lama yang masih tersimpan di
-- baris transaksi/kartu stok dari sebelum standardisasi itu.
--
-- Diaudit dulu -- tidak ada baris (item_name+project+jenis stok) yang punya
-- "m" DAN "Meter" sekaligus, jadi UPDATE ini aman (tidak perlu digabung/SUM),
-- murni ganti label.
-- =========================================================================

UPDATE `inventory` SET `unit` = 'Meter' WHERE `unit` = 'm';
UPDATE `purchase_order_items` SET `unit` = 'Meter' WHERE `unit` = 'm';
UPDATE `goods_receipt_items` SET `actual_unit` = 'Meter' WHERE `actual_unit` = 'm';
UPDATE `offline_purchase_items` SET `unit` = 'Meter' WHERE `unit` = 'm';
