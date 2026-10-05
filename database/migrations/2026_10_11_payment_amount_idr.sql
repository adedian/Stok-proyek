-- Konversi pembayaran PO non-IDR ke Rupiah berdasarkan kurs (2026-10-05).
--
-- Pembayaran kini punya 4 fakta terpisah:
--   currency   = mata uang PO (snapshot)                       [sudah ada]
--   amount     = nominal ASLI dalam mata uang PO               [sudah ada; dasar sisa/outstanding PO]
--   kurs       = kurs transaksi pembayaran ini                 [sudah ada]
--   amount_idr = amount x kurs, DIHITUNG ULANG backend          [BARU]
-- IDR: kurs 1 dan amount_idr = amount. Nilai asli PO TIDAK pernah diubah; sisa/status/termin
-- tetap dihitung dari `amount` (dalam mata uang PO), bukan dari amount_idr -- karena kurs
-- tiap pembayaran boleh berbeda.

ALTER TABLE payments
    ADD COLUMN amount_idr DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER kurs;

-- Data lama: amount x kurs (semua pembayaran lama IDR, kurs 1 -> amount_idr = amount).
UPDATE payments SET amount_idr = ROUND(amount * kurs, 2);