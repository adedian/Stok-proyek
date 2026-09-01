-- ============================================================
-- Penerimaan Barang: sumber "Dari Pembelian Kas" (2026-09-08)
-- ============================================================
-- Pembelian di luar PO sekarang lewat modul Kas (baris rincian ber-kategori
-- stok). Opsi lama "Dari Pembelian Offline" di form Penerimaan Barang diganti
-- jadi "Dari Pembelian Kas": user memilih 1 transaksi Kas, itemnya dari
-- cash_transaction_items, qty terima boleh sebagian (status sesuai/kurang/lebih).
--
-- Kas tetap kredit stok PENUH saat transaksi Kas disimpan. Validasi Barang
-- lalu mengoreksi stok ke qty FISIK yang diterima via delta:
--   goods_receipt_items.stock_delta_applied = (finalWanted - cash_qty)
--
-- Nilai enum lama 'offline_purchase' + kolom offline_purchase_id DIPERTAHANKAN
-- supaya data GR offline lama tetap valid & bisa diedit.
-- ============================================================

USE `db_stok_proyek`;

ALTER TABLE `goods_receipts`
  ADD COLUMN `cash_transaction_id` INT UNSIGNED NULL DEFAULT NULL AFTER `offline_purchase_id`,
  ADD KEY `fk_gr_cash_trx` (`cash_transaction_id`),
  ADD CONSTRAINT `fk_gr_cash_trx` FOREIGN KEY (`cash_transaction_id`) REFERENCES `cash_transactions` (`id`),
  MODIFY COLUMN `receipt_type` ENUM('purchase_order','pemakai','offline_purchase','cash') NOT NULL DEFAULT 'purchase_order';

ALTER TABLE `goods_receipt_items`
  ADD COLUMN `cash_transaction_item_id` INT UNSIGNED NULL DEFAULT NULL AFTER `offline_purchase_item_id`,
  ADD COLUMN `stock_delta_applied` DECIMAL(15,2) NULL DEFAULT NULL AFTER `stock_posted_at`,
  ADD KEY `fk_gri_cti` (`cash_transaction_item_id`),
  ADD CONSTRAINT `fk_gri_cti` FOREIGN KEY (`cash_transaction_item_id`) REFERENCES `cash_transaction_items` (`id`);
