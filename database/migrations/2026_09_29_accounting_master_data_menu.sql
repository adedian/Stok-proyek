-- Lanjutan 2026_09_29_accounting_master_data_full_access.sql -- ditemukan saat
-- testing: role_permissions.accounting.master_data.view masih 0 (menu "Master
-- Data" itu sendiri belum kebuka untuk Accounting), padahal izin ke tiap
-- sub-modul (supplier/project/dst) sudah diaktifkan di migrasi sebelumnya.
-- Tanpa ini, Accounting akan 403 di /master_data walau bisa akses /supplier
-- dst langsung by URL -- kondisi tidak konsisten yang diminta dihindari.

UPDATE role_permissions SET allowed = 1
 WHERE role_slug = 'accounting' AND module = 'master_data' AND action = 'view';
