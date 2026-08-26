-- ADDITIVE ONLY -- tidak ada kolom/tabel lama yang diubah tipe secara merusak,
-- tidak ada DROP, tidak ada data yang hilang.
--
-- ROOT CAUSE FIX (audit 2026-08-20): migration 2026_08_19_audit_fixes.sql menambahkan
-- pemisahan bucket stok Proyek vs Kantor ke `inventory` (project_id jadi nullable +
-- kolom stock_scope, project_id NULL = bucket Kantor) supaya Penerimaan Barang dari
-- "Pemakai/Internal" bisa dicatat sebagai stok Kantor yang tidak terikat project.
--
-- Tapi `stock_opname` TIDAK ikut diperbarui -- project_id di situ masih NOT NULL dan
-- listWithRelations()/findWithRelations() masih pakai INNER JOIN projects. Karena
-- InventoryController::opnameCreate()/ajaxItemsByProject() cuma bisa mengambil item
-- inventory berdasarkan project_id tertentu, item dengan stock_scope='kantor' (yang
-- project_id-nya NULL) TIDAK PERNAH bisa cocok dengan project manapun -- makanya item
-- itu tetap muncul normal di "Stok Barang" tapi selalu kosong saat dijadikan dasar
-- Stock Opname. Fix: samakan pola proyek/kantor di stock_opname dengan yang sudah
-- dipakai di inventory & goods_receipts.
ALTER TABLE stock_opname
    MODIFY COLUMN project_id INT UNSIGNED NULL,
    ADD COLUMN stock_scope ENUM('proyek','kantor') NOT NULL DEFAULT 'proyek' AFTER project_id;
