-- ============================================================
-- SEEDER: akun Finance, Gudang, Project Manager
-- Dijalankan SETELAH database/schema.sql (butuh tabel roles & users sudah ada).
-- role_id di-lookup lewat subquery ke roles.role_slug supaya tidak
-- tergantung urutan/nomor id insert di schema.sql.
-- Password login (GANTI setelah login pertama):
--   finance / financehme
--   gudang  / gudanghme
--   pm      / pmhme
-- ============================================================

USE `db_stok_proyek`;

INSERT INTO `users` (`role_id`, `full_name`, `username`, `email`, `password`, `status`, `created_by`) VALUES
((SELECT id FROM roles WHERE role_slug = 'finance'), 'Finance', 'finance', 'finance@stokproyek.local', '$2y$10$GD6QV.yx7mYHkn6n4qaPPepfOTflFqV6KQuzMkjQVI35.qbbVNJU2', 'active', 1),
((SELECT id FROM roles WHERE role_slug = 'gudang'), 'Gudang', 'gudang', 'gudang@stokproyek.local', '$2y$10$Vfo66t5JsOczqx.2aNZBP.l1U5DFdq.Z.ZF/awHWX45yXEo3HWT/W', 'active', 1),
((SELECT id FROM roles WHERE role_slug = 'project_manager'), 'Project Manager', 'pm', 'pm@stokproyek.local', '$2y$10$BjGljxfbbE0iGcKhCNbSXOX.UcJjEZ4KQd5C3k2cei1WPfwQG/FU2', 'active', 1);
