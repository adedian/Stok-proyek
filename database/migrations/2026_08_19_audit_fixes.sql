-- ADDITIVE ONLY -- tidak ada kolom/tabel lama yang diubah tipe/dihapus, tidak ada DROP.
-- Migration hasil audit menyeluruh (2026-08-19): kategori barang, master code Client,
-- lokasi pengiriman PO, sumber & kategori penerimaan barang (kantor/proyek, PO/pemakai),
-- barang aktual vs barang PO, dan kolom penanda kredit-stok untuk memperbaiki bug
-- "salah barang tapi status stok tetap Aman" (stok baru dikredit saat item DIVALIDASI,
-- bukan saat penerimaan disimpan -- lihat stock_posted_at).

-- 1) Kategori Barang wajib minimal (item_categories sudah ada & sudah terintegrasi
--    dengan items/PO -- ini cuma seed data, bukan perubahan struktur)
INSERT IGNORE INTO item_categories (category_name, created_by) VALUES
    ('Inventory Kantor', NULL),
    ('Stok Proyek', NULL),
    ('Stok Lampu', NULL);

-- 2) Lokasi Pengiriman pada PO -- reuse master Warehouse yang sudah ada
ALTER TABLE purchase_orders
    ADD COLUMN delivery_location_id INT UNSIGNED NULL AFTER project_id,
    ADD CONSTRAINT fk_po_delivery_location FOREIGN KEY (delivery_location_id) REFERENCES warehouses(id);

-- 3) Goods Receipt: sumber (PO/Pemakai) + kategori (Proyek/Kantor) + project_id langsung
--    (dipakai saat receipt_type = 'pemakai', tidak ada PO untuk diturunkan project-nya)
ALTER TABLE goods_receipts
    MODIFY COLUMN purchase_order_id INT UNSIGNED NULL,
    ADD COLUMN receipt_type ENUM('purchase_order','pemakai') NOT NULL DEFAULT 'purchase_order' AFTER purchase_order_id,
    ADD COLUMN stock_scope ENUM('proyek','kantor') NOT NULL DEFAULT 'proyek' AFTER receipt_type,
    ADD COLUMN project_id INT UNSIGNED NULL AFTER stock_scope,
    ADD COLUMN source_detail VARCHAR(150) NULL AFTER project_id,
    ADD CONSTRAINT fk_gr_project FOREIGN KEY (project_id) REFERENCES projects(id);

-- 4) Goods Receipt Items: barang aktual (bisa beda dari barang PO) + penanda kredit stok
ALTER TABLE goods_receipt_items
    MODIFY COLUMN purchase_order_item_id INT UNSIGNED NULL,
    ADD COLUMN actual_item_id INT UNSIGNED NULL AFTER purchase_order_item_id,
    ADD COLUMN actual_item_name VARCHAR(200) NULL AFTER actual_item_id,
    ADD COLUMN actual_unit VARCHAR(30) NULL AFTER actual_item_name,
    ADD COLUMN is_item_mismatch TINYINT(1) NOT NULL DEFAULT 0 AFTER actual_unit,
    ADD COLUMN stock_posted_at DATETIME NULL AFTER validated_at,
    ADD CONSTRAINT fk_gri_actual_item FOREIGN KEY (actual_item_id) REFERENCES items(id);

-- 5) Inventory: pisahkan bucket stok Kantor vs Proyek (project_id NULL = bucket Kantor)
ALTER TABLE inventory
    MODIFY COLUMN project_id INT UNSIGNED NULL,
    ADD COLUMN stock_scope ENUM('proyek','kantor') NOT NULL DEFAULT 'proyek' AFTER project_id;

-- 6) Master Client (entitas baru -- sebelumnya tidak ada sama sekali di sistem)
CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_code VARCHAR(30) NOT NULL UNIQUE,
    client_name VARCHAR(150) NOT NULL,
    address TEXT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    contact_person VARCHAR(100) NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
