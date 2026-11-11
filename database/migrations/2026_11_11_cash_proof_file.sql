-- Bukti foto/dokumen transaksi Kas (opsional): satu file per transaksi (foto nota/kwitansi atau PDF).
-- Disimpan di public/uploads/bukti_kas/ dan dilayani lewat FileController (login + izin modul Kas),
-- bukan link langsung. NULL = tidak ada bukti (semua data lama). Transaksi lama tidak diubah.
ALTER TABLE cash_transactions
  ADD COLUMN proof_file VARCHAR(255) NULL AFTER rekening_id;
