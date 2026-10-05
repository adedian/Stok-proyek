-- Mata Uang pada Purchase Order (2026-10-05).
--
-- Mata uang disimpan di HEADER PO (satu mata uang per PO; seluruh item, subtotal,
-- PPN, biaya tambahan & total ikut). Murni LABEL/identitas: TIDAK ada konversi,
-- kurs, atau perubahan nominal -- kolom nominal (price/subtotal/total_amount)
-- tetap angka asli. Kode ISO 3 huruf; daftar yang diizinkan dijaga di aplikasi
-- (poCurrencies() di app/helpers/functions.php) -- belum ada Master Mata Uang.
--
-- PO lama otomatis 'IDR' (default kolom), jadi data & tampilan lama tidak berubah
-- selain prefix "Rp" -> "IDR".

ALTER TABLE purchase_orders
    ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'IDR' AFTER po_date;
