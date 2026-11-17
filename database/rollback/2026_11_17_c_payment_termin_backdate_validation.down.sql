-- ROLLBACK untuk database/migrations/2026_11_17_c_payment_termin_backdate_validation.php
-- Jalankan MANUAL (mysql / phpMyAdmin), lalu hapus barisnya dari schema_migrations:
--   DELETE FROM schema_migrations WHERE filename = '2026_11_17_c_payment_termin_backdate_validation.php';
-- PERINGATAN: pembayaran berstatus 'overpaid' diubah ke 'paid' dulu (agar ENUM lama bisa dipulihkan).
-- Pembayaran jenis 'pelunasan' kehilangan penandanya (kembali dianggap termin biasa; nomor termin = 0).

ALTER TABLE payments DROP COLUMN payment_type;
UPDATE payments SET status = 'paid' WHERE status = 'overpaid';
ALTER TABLE payments MODIFY COLUMN status ENUM('pending','partial','paid') DEFAULT 'pending';
ALTER TABLE purchase_orders DROP COLUMN termin_counter;
DROP TABLE IF EXISTS system_setting_history;
DROP TABLE IF EXISTS goods_receipt_item_validations;
DELETE FROM system_settings WHERE setting_key = 'po_block_backdate';
