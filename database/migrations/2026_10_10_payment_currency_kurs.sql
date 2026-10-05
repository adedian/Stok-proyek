-- Kurs pada Pembayaran PO (2026-10-05).
--
-- Pembayaran kini menyimpan mata uang + kurs transaksi, TERPISAH dari nominal:
--   amount   = nilai transaksi dalam mata uang tsb (TIDAK dikonversi / dikali kurs)
--   currency = snapshot mata uang PO saat pembayaran dibuat (VARCHAR(3), kode ISO)
--   kurs     = nilai tukar saat bayar, informasi saja (DECIMAL(18,6), mis. 16500 / 12.75)
-- IDR selalu kurs = 1 (ditegakkan backend, bukan input user). Mata uang selain IDR
-- wajib kurs > 0 (divalidasi backend). Perhitungan total/sisa/status/termin TIDAK
-- berubah -- tetap memakai amount apa adanya.

ALTER TABLE payments
    ADD COLUMN currency VARCHAR(3)   NOT NULL DEFAULT 'IDR' AFTER amount,
    ADD COLUMN kurs     DECIMAL(18,6) NOT NULL DEFAULT 1    AFTER currency;

-- Pembayaran lama: ikut mata uang PO-nya (semua PO lama = IDR, jadi tidak ada yang
-- berubah). Kurs dibiarkan 1 (default) -- data lama tidak punya nilai kurs.
UPDATE payments p
  JOIN purchase_orders po ON po.id = p.purchase_order_id
   SET p.currency = po.currency;
