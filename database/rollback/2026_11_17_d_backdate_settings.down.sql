-- ROLLBACK untuk database/migrations/2026_11_17_d_backdate_settings.php
-- Jalankan MANUAL, lalu: DELETE FROM schema_migrations WHERE filename = '2026_11_17_d_backdate_settings.php';
-- Kembalikan toggle lama "Back Date PO" dari baris purchase_order, lalu hapus tabel baru.
-- (Kode aplikasi versi baru memakai backdate_settings -- rollback ini hanya untuk kembali ke kode LAMA.)
INSERT INTO system_settings (setting_key, setting_value, setting_group)
SELECT 'po_block_backdate', COALESCE((SELECT is_active FROM backdate_settings WHERE menu_code = 'purchase_order'), 0), 'purchase_order'
 WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'po_block_backdate');
DROP TABLE IF EXISTS backdate_settings;
DELETE FROM system_setting_history WHERE setting_key LIKE 'backdate:%';
