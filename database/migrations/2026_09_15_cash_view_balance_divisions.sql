-- ============================================================
-- Kas: saldo divisi untuk role Purchase & Project (2026-09-15)
-- ============================================================
-- Sebelumnya kartu saldo Kas (cash.view_balance) hanya untuk Super Admin &
-- Accounting. Sekarang Purchase / PIC Project / Admin Project / Project Manager
-- juga boleh melihat -- TAPI hanya saldo DIVISI mereka sendiri (di-scope di
-- CashController::index(): Purchase -> "Saldo Kas Purchase", role project ->
-- "Saldo Kas Project"), tanpa Total Saldo & tanpa divisi lain.
--
-- INSERT IGNORE: idempotent, tidak menimpa hasil edit admin di UI Hak Akses.
-- ============================================================

USE `db_stok_proyek`;

INSERT IGNORE INTO `role_permissions` (`role_slug`, `module`, `action`, `allowed`) VALUES
  ('purchase',        'cash', 'view_balance', 1),
  ('pic_project',     'cash', 'view_balance', 1),
  ('admin_project',   'cash', 'view_balance', 1),
  ('project_manager', 'cash', 'view_balance', 1);

-- Kalau barisnya sudah ada tapi ter-set 0 (mis. dari seed lama), aktifkan.
UPDATE `role_permissions`
   SET `allowed` = 1
 WHERE `module` = 'cash' AND `action` = 'view_balance'
   AND `role_slug` IN ('purchase', 'pic_project', 'admin_project', 'project_manager');
