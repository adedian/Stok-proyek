-- Audit "Terakhir Diedit Oleh" untuk transaksi Kas & Bank. Diisi HANYA saat transaksi berhasil
-- diedit lewat form Edit (bukan saat dibuat / divalidasi), dari akun yang sedang login di server.
-- updated_at TIDAK dipakai untuk ini karena ikut berubah oleh proses lain (mis. validasi).
-- NULL = belum pernah diedit sejak dibuat (semua data lama). Transaksi lama tidak diubah.
ALTER TABLE cash_transactions
  ADD COLUMN last_edited_by INT UNSIGNED NULL AFTER created_by,
  ADD COLUMN last_edited_at DATETIME NULL AFTER last_edited_by,
  ADD CONSTRAINT fk_cash_last_edited_by FOREIGN KEY (last_edited_by) REFERENCES users (id);

ALTER TABLE bank_transactions
  ADD COLUMN last_edited_by INT UNSIGNED NULL AFTER created_by,
  ADD COLUMN last_edited_at DATETIME NULL AFTER last_edited_by,
  ADD CONSTRAINT fk_bank_last_edited_by FOREIGN KEY (last_edited_by) REFERENCES users (id);
