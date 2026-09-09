-- ============================================================
-- Revisi hak akses: tim Purchase & Project Manager (2026-09-09)
-- ============================================================
-- Permintaan:
--   1. Role PURCHASE (mis. akun Andy & Anggita) BOLEH membuka menu
--      "Validasi Barang" dan memvalidasi barang datang.
--   2. Role PROJECT MANAGER (mis. akun Vicky) TIDAK lagi melihat menu
--      "Purchase Order" & "Invoice Keluar" -- PM tidak mengurus pembelian
--      maupun penagihan.
--   3. Role PURCHASE BOLEH membuka "Laporan Stok Barang" (kartu stok) TANPA
--      diberi akses penuh modul Stok & Opname. Output Cetak/Export tetap
--      SELALU tanpa harga (report.stock_price tetap Super Admin & Accounting).
--
-- role_permissions sudah "managed" -> default di config/permissions.php
-- diabaikan untuk role selain Super Admin, jadi perubahan harus lewat baris DB.
-- INSERT IGNORE: idempotent, tidak menimpa hasil edit admin di UI Hak Akses.
-- UPDATE menyusul: memastikan baris yang SUDAH ada ikut berubah nilainya.
-- Super Admin tidak perlu baris (selalu full-access via can()).
-- ============================================================

USE `db_stok_proyek`;

-- 1) PURCHASE -> Validasi Barang (view + validate) ------------------------
INSERT IGNORE INTO `role_permissions` (`role_slug`, `module`, `action`, `allowed`) VALUES
  ('purchase', 'validation', 'view',     1),
  ('purchase', 'validation', 'validate', 1);

UPDATE `role_permissions`
   SET `allowed` = 1
 WHERE `role_slug` = 'purchase'
   AND `module` = 'validation'
   AND `action` IN ('view', 'validate');

-- 2) PROJECT MANAGER -> cabut Purchase Order & Invoice Keluar ------------
INSERT IGNORE INTO `role_permissions` (`role_slug`, `module`, `action`, `allowed`) VALUES
  ('project_manager', 'purchase_order', 'view',   0),
  ('project_manager', 'sales_invoice',  'view',   0);

UPDATE `role_permissions`
   SET `allowed` = 0
 WHERE `role_slug` = 'project_manager'
   AND `module` IN ('purchase_order', 'sales_invoice');

-- 3) report.stock_report -- buka "Laporan Stok Barang" tanpa inventory.view
--    Purchase & role project = 1 (butuh / sudah dapat lewat jalur lain).
--    Accounting = 1 (sudah punya inventory.view, didaftarkan agar matrix
--    Hak Akses menampilkan kondisi sebenarnya). admin_project = 0
--    (tidak punya report.view sama sekali).
INSERT IGNORE INTO `role_permissions` (`role_slug`, `module`, `action`, `allowed`) VALUES
  ('purchase',        'report', 'stock_report', 1),
  ('accounting',      'report', 'stock_report', 1),
  ('pic_project',     'report', 'stock_report', 1),
  ('project_manager', 'report', 'stock_report', 1),
  ('admin_project',   'report', 'stock_report', 0);
