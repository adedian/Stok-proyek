-- ============================================================
-- Konsistensi Jenis Stok vs bucket (2026-09-13)
-- ============================================================
-- Setelah 2026_09_12_stock_type_unify.sql, sebagian baris `inventory` ter-backfill
-- jadi stock_type='stok_proyek' (ikut default master Barang) padahal stoknya ada
-- di bucket KANTOR (stock_scope='kantor', project_id NULL) -- mis. "Kabel AC" &
-- "Mouse" yang diterima lewat Penerimaan "Pemakai/Internal".
--
-- Akibatnya di halaman Stok Barang kolom "Kategori" tampil "Stok Proyek" tapi
-- kolom "Project" tampil "Kantor" -- terlihat bentrok.
--
-- Aturan: "Stok Proyek" HANYA untuk stok yang terikat project. Stok tanpa project
-- (Kantor) diklasifikasikan "Inventory Kantor". ("Stok Lampu" boleh di project
-- MAUPUN kantor, jadi TIDAK diutak-atik.)
--
-- ADDITIVE/CORRECTIVE -- hanya UPDATE nilai enum, tidak ada perubahan skema.
-- ============================================================

USE `db_stok_proyek`;

UPDATE `inventory`
SET `stock_type` = 'inventory_kantor'
WHERE `stock_scope` = 'kantor' AND `stock_type` = 'stok_proyek';

UPDATE `goods_receipts`
SET `stock_type` = 'inventory_kantor'
WHERE `stock_scope` = 'kantor' AND `stock_type` = 'stok_proyek';
