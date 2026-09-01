-- ============================================================
-- KAS: perbaikan ejaan kategori "Material Projek" -> "Material Proyek" (2026-09-06)
-- ============================================================
-- Ejaan baku bahasa Indonesia adalah "Proyek" (sesuai penamaan lain di aplikasi:
-- "Stok Proyek", dll). Kategori Kas ini terlanjur di-seed sebagai "Material Projek"
-- di 2026_08_28_revisi9_roles_cash_pic.sql. Migration ini menyeragamkannya pada
-- database yang sudah berjalan; seed & referensi di kode juga sudah disesuaikan.
--
-- UNIQUE(category_name): kalau (entah bagaimana) baris "Material Proyek" sudah ada,
-- UPDATE akan gagal karena duplikat -- kondisi itu tidak diharapkan terjadi.
-- ============================================================

USE `db_stok_proyek`;

UPDATE `cash_categories`
SET `category_name` = 'Material Proyek', `updated_at` = NOW()
WHERE `category_name` = 'Material Projek';
