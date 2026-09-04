-- ============================================================
-- Penyeragaman "Kategori Stok" ke 3 Jenis Stok master (2026-09-12)
-- ============================================================
-- Sebelumnya ada 2 sumbu kategori yang tidak nyambung:
--   * items.stock_type          -> 'stok_proyek' / 'stok_lampu' / 'inventory_kantor'
--       (atribut master Barang; dipakai Master Kode & filter Laporan)
--   * inventory/stock_opname/goods_receipts.stock_scope -> 'proyek' / 'kantor'
--       (bucket fisik: terikat project vs tidak)
--
-- Form Stok Opname & Penerimaan Barang cuma menampilkan 2 opsi (stock_scope),
-- padahal user mengharapkan 3 Jenis Stok yang sama dengan Master Data > Barang.
--
-- Migration ini menambah kolom `stock_type` (ENUM 3 nilai) ke tabel stok supaya
-- bisa jadi sumbu kategori tunggal di UI + filter. `stock_scope` DIPERTAHANKAN
-- (tidak di-drop, tidak diubah) -- tetap jadi penanda "terikat project / tidak"
-- dan kunci bucket stok TIDAK berubah, jadi angka historis & Kartu Stok aman.
--
-- Backfill:
--   inventory     -> ikut items.stock_type (cocokkan nama). Barang tanpa entri
--                    master jatuh ke fallback dari stock_scope lama.
--   stock_opname  -> dari stock_scope lama (proyek->stok_proyek, kantor->inventory_kantor)
--   goods_receipts-> idem (nilai per-item sebenarnya dihitung dari master saat
--                    validasi; kolom ini hanya default untuk barang non-master)
--
-- ADDITIVE ONLY -- tidak ada DROP, tidak ada kolom lama yang diubah tipe.
-- ============================================================

USE `db_stok_proyek`;

-- 1) inventory --------------------------------------------------------------
ALTER TABLE `inventory`
  ADD COLUMN `stock_type` ENUM('stok_proyek','stok_lampu','inventory_kantor')
    NOT NULL DEFAULT 'stok_proyek' AFTER `stock_scope`;

UPDATE `inventory` inv
LEFT JOIN `items` i
  ON i.`item_name` = inv.`item_name` AND i.`deleted_at` IS NULL
SET inv.`stock_type` = COALESCE(
  i.`stock_type`,
  CASE inv.`stock_scope` WHEN 'kantor' THEN 'inventory_kantor' ELSE 'stok_proyek' END
);

-- 2) stock_opname ---------------------------------------------------------
ALTER TABLE `stock_opname`
  ADD COLUMN `stock_type` ENUM('stok_proyek','stok_lampu','inventory_kantor')
    NOT NULL DEFAULT 'stok_proyek' AFTER `stock_scope`;

UPDATE `stock_opname`
SET `stock_type` = CASE `stock_scope` WHEN 'kantor' THEN 'inventory_kantor' ELSE 'stok_proyek' END;

-- 3) goods_receipts -----------------------------------------------------
ALTER TABLE `goods_receipts`
  ADD COLUMN `stock_type` ENUM('stok_proyek','stok_lampu','inventory_kantor')
    NOT NULL DEFAULT 'stok_proyek' AFTER `stock_scope`;

UPDATE `goods_receipts`
SET `stock_type` = CASE `stock_scope` WHEN 'kantor' THEN 'inventory_kantor' ELSE 'stok_proyek' END;
