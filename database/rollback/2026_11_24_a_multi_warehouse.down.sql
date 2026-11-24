-- ROLLBACK untuk database/migrations/2026_11_24_a_multi_warehouse.php
-- Jalankan MANUAL, lalu: DELETE FROM schema_migrations WHERE filename = '2026_11_24_a_multi_warehouse.php';
-- PERINGATAN: baris stock_out ber-jenis 'gudang' (mutasi) akan hilang jenisnya -- pastikan tidak ada
-- mutasi aktif (Dalam Perjalanan) sebelum rollback. Kode aplikasi versi baru butuh kolom-kolom ini;
-- rollback hanya untuk kembali ke kode LAMA. Master Gudang "Gudang Domas" yang dibuat migrasi dibiarkan.
DELETE FROM stock_out WHERE destination_type = 'gudang';
ALTER TABLE stock_out
  DROP FOREIGN KEY fk_so_source_wh, DROP FOREIGN KEY fk_so_dest_wh,
  DROP FOREIGN KEY fk_so_dest_inventory, DROP FOREIGN KEY fk_so_received_by;
ALTER TABLE stock_out
  DROP INDEX idx_so_source_wh, DROP INDEX idx_so_dest_wh, DROP INDEX idx_so_transfer_status;
ALTER TABLE stock_out
  DROP COLUMN source_warehouse_id, DROP COLUMN dest_warehouse_id, DROP COLUMN transfer_status,
  DROP COLUMN dest_inventory_id, DROP COLUMN transfer_received_at, DROP COLUMN transfer_received_by;
ALTER TABLE stock_out MODIFY COLUMN destination_type ENUM('project','client') NOT NULL DEFAULT 'project';
ALTER TABLE goods_receipts DROP FOREIGN KEY fk_gr_warehouse, DROP INDEX idx_gr_warehouse, DROP COLUMN warehouse_id;
ALTER TABLE inventory DROP FOREIGN KEY fk_inventory_warehouse, DROP INDEX idx_inventory_warehouse, DROP COLUMN warehouse_id;
DELETE FROM system_settings WHERE setting_key = 'stock.default_warehouse_id';
