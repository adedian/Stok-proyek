-- ============================================================
-- Hak akses: cash.print_voucher (2026-09-18)
-- ============================================================
-- Fitur "Cetak Terpilih" di halaman Kas & Laporan Kas (voucher BUKTI KAS
-- KELUAR / MASUK, 1 dokumen per No Bukti). HANYA Super Admin & Accounting.
--
-- config/permissions.php sudah memuat default file [super_admin, accounting],
-- TAPI DB matrix (role_permissions) sudah "managed" -> default file diabaikan
-- untuk role selain Super Admin. Baris di bawah menautkannya ke Accounting.
--
-- INSERT IGNORE: idempotent, tidak menimpa hasil edit admin di UI Hak Akses.
-- Super Admin tidak perlu baris (selalu full-access via can()).
-- Role lain di-set allowed=0 secara eksplisit supaya muncul "tidak dicentang"
-- (bukan kosong) di halaman Hak Akses.
-- ============================================================

USE `db_stok_proyek`;

INSERT IGNORE INTO `role_permissions` (`role_slug`, `module`, `action`, `allowed`) VALUES
  ('accounting',      'cash', 'print_voucher', 1),
  ('purchase',        'cash', 'print_voucher', 0),
  ('pic_project',     'cash', 'print_voucher', 0),
  ('admin_project',   'cash', 'print_voucher', 0),
  ('project_manager', 'cash', 'print_voucher', 0);
