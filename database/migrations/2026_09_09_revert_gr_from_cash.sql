-- Revert "Penerimaan Barang: sumber Dari Pembelian Kas" (commits 53f295b + 60ccfa2).
--
-- Keputusan baru: transaksi Kas LANGSUNG menambah stok saat disimpan dan TIDAK
-- pernah lewat Penerimaan Barang. Penerimaan Barang kembali hanya mengenal
-- sumber: purchase_order, offline_purchase, pemakai.
--
-- Migration ini membatalkan 2026_09_08_gr_from_cash.sql. Aman dijalankan berulang
-- dan aman di environment yang belum pernah menjalankan migration 2026_09_08
-- (semua DROP memakai IF EXISTS).

ALTER TABLE goods_receipts       DROP FOREIGN KEY IF EXISTS fk_gr_cash_trx;
ALTER TABLE goods_receipt_items  DROP FOREIGN KEY IF EXISTS fk_gri_cti;

ALTER TABLE goods_receipts       DROP COLUMN IF EXISTS cash_transaction_id;
ALTER TABLE goods_receipt_items  DROP COLUMN IF EXISTS cash_transaction_item_id;
ALTER TABLE goods_receipt_items  DROP COLUMN IF EXISTS stock_delta_applied;

-- Kembalikan enum receipt_type ke 3 nilai semula (tidak ada baris ber-tipe 'cash').
ALTER TABLE goods_receipts
    MODIFY COLUMN receipt_type ENUM('purchase_order','pemakai','offline_purchase')
    NOT NULL DEFAULT 'purchase_order';
