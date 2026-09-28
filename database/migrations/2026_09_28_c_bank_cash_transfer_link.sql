-- Transfer Bank -> Kas: 1 aksi menghasilkan 2 baris tertaut --
-- 1 baris `bank_transactions` (mutasi='keluar', dari PIC pengirim) DAN
-- 1 baris `cash_transactions` (mutasi='masuk', ke PIC tujuan) dalam satu
-- transaksi DB (lihat CashController::transferStore()). Kolom penaut di
-- bawah supaya kedua sisi bisa saling dirujuk secara query-able (uraian teks
-- di masing-masing baris tetap dibuat juga, sebagai fallback dibaca manusia).
-- ON DELETE SET NULL -- menghapus salah satu sisi TIDAK menghapus sisi lain
-- (masing-masing tetap tunduk pada aturan hapus/validasi modulnya sendiri),
-- cuma memutus link.

ALTER TABLE bank_transactions
    ADD COLUMN related_cash_transaction_id INT UNSIGNED NULL AFTER pic,
    ADD CONSTRAINT fk_bank_trx_related_cash FOREIGN KEY (related_cash_transaction_id)
        REFERENCES cash_transactions (id) ON DELETE SET NULL;

ALTER TABLE cash_transactions
    ADD COLUMN related_bank_transaction_id INT UNSIGNED NULL AFTER pic,
    ADD CONSTRAINT fk_cash_trx_related_bank FOREIGN KEY (related_bank_transaction_id)
        REFERENCES bank_transactions (id) ON DELETE SET NULL;
