-- ============================================================
-- Hak akses: report.stock_price (2026-09-07)
-- ============================================================
-- Fitur baru "Tampilkan / Tanpa harga" di Laporan Stok Barang (Cetak & Export).
-- Hanya Super Admin (selalu, lewat kode) & Accounting yang boleh melihat kolom
-- harga + memilih toggle-nya. Role lain: output Cetak/Export selalu tanpa harga.
--
-- INSERT IGNORE: idempotent, tidak menimpa baris hasil edit admin di UI Hak Akses.
-- Super Admin tidak perlu baris (selalu full-access via can()).
-- ============================================================

USE `db_stok_proyek`;

INSERT IGNORE INTO `role_permissions` (`role_slug`, `module`, `action`, `allowed`) VALUES
  ('accounting',      'report', 'stock_price', 1),
  ('purchase',        'report', 'stock_price', 0),
  ('pic_project',     'report', 'stock_price', 0),
  ('admin_project',   'report', 'stock_price', 0),
  ('project_manager', 'report', 'stock_price', 0);
