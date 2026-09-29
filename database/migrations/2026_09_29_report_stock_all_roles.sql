-- Laporan Stok (Stok Barang) harus bisa dibuka SEMUA role yang sudah login,
-- bukan cuma role yang eksplisit terdaftar.
--
-- ROOT CAUSE: role_permissions ADALAH sumber kebenaran aktual sejak fitur
-- "Editable permissions" (2026-09-01) -- config/permissions.php cuma dipakai
-- sebagai katalog aksi & fallback SEBELUM tabel ini diisi. admin_project satu-
-- satunya role aktif yang baris report.view & report.stock_report-nya masih 0.
--
-- guardReportScope()/availableReports() di ReportController SUDAH BENAR:
-- role di luar [super_admin, purchase, accounting] otomatis dibatasi HANYA ke
-- Laporan Stok Barang (kartu Kas/PO/dll tidak ikut kebuka) begitu report.view
-- bernilai true -- jadi cukup ubah 2 baris ini, tidak perlu ubah kode.
--
-- finance & gudang TIDAK disertakan: kedua role itu sudah non-aktif (0 user
-- aktif memegangnya) dan bukan bagian dari role yang bisa diedit lewat Hak
-- Akses (permissionEditableRoleSlugs()), jadi tidak ada baris role_permissions
-- untuk mereka sama sekali -- tidak ada yang perlu/bisa diperbaiki di sana.

UPDATE role_permissions SET allowed = 1
 WHERE module = 'report' AND action = 'view' AND role_slug = 'admin_project';

UPDATE role_permissions SET allowed = 1
 WHERE module = 'report' AND action = 'stock_report' AND role_slug = 'admin_project';
