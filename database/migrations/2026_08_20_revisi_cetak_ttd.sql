-- Revisi: Cetak (checkbox), Pembuat PO manual, Master Tanda Tangan,
-- Satuan dropdown di Penerimaan Barang, rename status "Salah Barang" -> "Barang Lain".
-- Aman: tidak ada DROP TABLE, tidak ada data dihapus, FK dipertahankan.

-- 1) Purchase Order: Pembuat PO (nama orang yang MENYUSUN PO secara bisnis) --
-- field baru, terpisah dari created_by (akun sistem yang input). Nullable supaya
-- PO lama (belum punya nilai ini) tidak error; form baru mewajibkan diisi.
ALTER TABLE purchase_orders
    ADD COLUMN pembuat_po VARCHAR(150) NULL AFTER created_by;

-- 2) Master Tanda Tangan -- dipakai di cetak PO & cetak Penerimaan Barang by User.
CREATE TABLE IF NOT EXISTS signatures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    position VARCHAR(100) NOT NULL,
    signature_image VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Rename status validasi 'salah_barang' -> 'barang_lain' (istilah baru, business
-- logic/logic "tidak sesuai" TIDAK berubah). Dua langkah aman: tambah value baru dulu
-- ke enum, migrasikan data, baru buang value lama -- supaya tidak ada baris ke-truncate.
ALTER TABLE goods_receipt_items
    MODIFY COLUMN comparison_status ENUM('sesuai','kurang','lebih','salah_barang','barang_lain') NOT NULL DEFAULT 'sesuai';

UPDATE goods_receipt_items SET comparison_status = 'barang_lain' WHERE comparison_status = 'salah_barang';

ALTER TABLE goods_receipt_items
    MODIFY COLUMN comparison_status ENUM('sesuai','kurang','lebih','barang_lain') NOT NULL DEFAULT 'sesuai';
