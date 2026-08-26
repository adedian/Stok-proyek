-- ============================================================
-- SEED DATA UJI: supplier & project
-- Dijalankan SETELAH database/schema.sql (butuh tabel users sudah ada
-- untuk kolom created_by, memakai id admin = 1 dari schema.sql).
-- ============================================================

USE `db_stok_proyek`;

INSERT INTO `suppliers` (`supplier_code`, `supplier_name`, `address`, `phone`, `email`, `contact_person`, `status`, `created_by`) VALUES
('SUP-0001', 'CV Sumber Material Jaya', 'Jl. Industri Raya No. 12, Bekasi', '021-8801234', 'sales@sumbermaterial.co.id', 'Budi Santoso', 'active', 1),
('SUP-0002', 'PT Baja Konstruksi Utama', 'Jl. Rungkut Industri II No. 5, Surabaya', '031-8971234', 'order@bajakonstruksi.co.id', 'Siti Rahma', 'active', 1);

INSERT INTO `projects` (`project_code`, `project_name`, `location`, `pm_id`, `start_date`, `end_date`, `status`, `created_by`) VALUES
('PRJ-0001', 'Pembangunan Gudang B - Cikarang', 'Cikarang, Jawa Barat', NULL, '2026-01-15', '2026-12-31', 'ongoing', 1),
('PRJ-0002', 'Renovasi Kantor Pusat', 'Jakarta Selatan', NULL, '2026-03-01', '2026-09-30', 'ongoing', 1);
