-- ROLLBACK untuk database/migrations/2026_11_24_c_goods_receipt_sent_from_warehouse.php
-- Jalankan MANUAL, lalu: DELETE FROM schema_migrations WHERE filename = '2026_11_24_c_goods_receipt_sent_from_warehouse.php';
ALTER TABLE goods_receipts DROP FOREIGN KEY fk_gr_sent_from_warehouse, DROP COLUMN sent_from_warehouse_id;
