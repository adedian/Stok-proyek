-- Invoice Keluar: field Tempo (hari) + Jatuh Tempo (Tanggal Invoice + Tempo).
--
-- Kolom baru murni ADDITIVE -- tidak ada kolom lama yang bisa dipakai ulang
-- (sales_invoices sebelumnya tidak punya konsep jatuh tempo sama sekali,
-- hanya invoice_date; jatuh tempo PER TERMIN sudah ada duluan di
-- sales_invoice_terms.due_date sejak migrasi Termin 2026-09-29 sebelumnya).
--
-- Invoice lama: TIDAK ADA dasar data untuk menghitung tempo/jatuh_tempo
-- (tidak pernah ada tanggal jatuh tempo tersimpan di invoice manapun sebelum
-- ini), jadi kedua kolom dibiarkan NULL untuk semua invoice existing --
-- BUKAN diisi paksa dengan asumsi/default.

ALTER TABLE sales_invoices
    ADD COLUMN tempo INT UNSIGNED NULL AFTER invoice_date,
    ADD COLUMN jatuh_tempo DATE NULL AFTER tempo;
