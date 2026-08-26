-- Tambahan: No. & Tanggal Quotation (referensi penawaran dari supplier) di PO,
-- supaya format cetak PO bisa menampilkan blok "Quote Number / Date" seperti
-- dokumen PO resmi perusahaan. Opsional (nullable) -- PO lama tetap valid.
ALTER TABLE purchase_orders
    ADD COLUMN quote_number VARCHAR(50) NULL AFTER signature_id,
    ADD COLUMN quote_date DATE NULL AFTER quote_number;
