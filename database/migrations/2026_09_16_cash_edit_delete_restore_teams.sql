-- ============================================================
-- Kas: kembalikan edit & hapus untuk tim Purchase & tim Project (2026-09-16)
-- ============================================================
-- Revisi lanjutan dari 2026_09_14 (yang mengunci edit/hapus Kas ke Super Admin
-- & Accounting saja). Sesuai permintaan: tim Purchase dan tim Project
-- (PIC Project & Admin Project) BOLEH lagi mengedit & menghapus transaksi Kas
-- -- tetap ter-scope PIC/divisi masing-masing lewat CashController.
--
-- Project Manager tetap "lihat saja".
--
-- Pembatasan "Tutup Bulan": transaksi / edit / hapus di periode yang sudah
-- ditutup tetap DITOLAK untuk SEMUA role (assertPeriodOpen('cash', ...) di
-- CashController::store()/update()/delete()) -- tidak perlu perubahan di sini.
-- ============================================================

USE `db_stok_proyek`;

UPDATE `role_permissions`
   SET `allowed` = 1
 WHERE `module` = 'cash'
   AND `action` IN ('edit', 'delete')
   AND `role_slug` IN ('purchase', 'pic_project', 'admin_project');

INSERT IGNORE INTO `role_permissions` (`role_slug`, `module`, `action`, `allowed`) VALUES
  ('purchase',      'cash', 'edit',   1),
  ('purchase',      'cash', 'delete', 1),
  ('pic_project',   'cash', 'edit',   1),
  ('pic_project',   'cash', 'delete', 1),
  ('admin_project', 'cash', 'edit',   1),
  ('admin_project', 'cash', 'delete', 1);
