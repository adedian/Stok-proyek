-- Validasi Kas: Transfer ke Kas TIDAK perlu validasi (2026-10-05).
--
-- Aturan: validasi PM (Vicky) hanya untuk transaksi Kas biasa tim Project.
-- Kas Masuk hasil "Transfer ke Kas" (CashController::transferStore) langsung
-- final -- statusnya 'tidak_perlu', tidak masuk daftar/hitungan/badge Validasi
-- Kas, dan tidak terkunci setelah dibuat (kunci hanya untuk 'tervalidasi').
-- Penentu "Transfer ke Kas" = kolom yang SUDAH ADA: related_bank_transaction_id
-- (terisi hanya oleh transfer; transaksi Kas biasa selalu NULL).
--
-- Tidak ada kolom baru: hanya menambah 1 nilai enum, pola yang sama dengan
-- stock_out (2026_09_30_stock_out_validation_project_only.sql). Saldo & laporan
-- TIDAK disentuh -- tidak ada yang membaca validation_status untuk menghitung.

ALTER TABLE cash_transactions
    MODIFY COLUMN validation_status ENUM('menunggu','tervalidasi','ditolak','tidak_perlu') NOT NULL DEFAULT 'menunggu';

-- Kas Masuk transfer yang masih antre ('menunggu', belum diputuskan siapa pun)
-- -> 'tidak_perlu'. Yang SUDAH diputuskan user sungguhan (tervalidasi/ditolak)
-- dibiarkan sebagai catatan riwayat.
UPDATE cash_transactions
   SET validation_status = 'tidak_perlu',
       validated_by      = NULL,
       validated_at      = NULL,
       validation_note   = 'Transfer ke Kas, tidak perlu validasi'
 WHERE related_bank_transaction_id IS NOT NULL
   AND validation_status = 'menunggu';
