-- ============================================================
-- Akhiran Nomor PO lama yang tertanam di po_number: isi kolom po_number_suffix.
--
-- PO lama ditulis langsung dengan akhiran di dalam nomornya, mis.
-- "138/PO.HME/IX/2026 - Rev1" atau "149/PO.HME/X/2026 - Rev 2", dengan
-- po_number_suffix masih NULL. Akibatnya form Edit menampilkan kolom
-- "Akhiran Nomor PO" kosong dan "- Rev1" dianggap bagian nomor otomatis,
-- sehingga tidak bisa diubah. Dengan kolom suffix terisi (teks persis setelah
-- spasi pertama), form Edit menampilkannya dan mengubahnya ikut mengubah nomor.
--
-- Nomor otomatis ("138/PO.HME/IX/2026") tidak pernah mengandung spasi, jadi
-- semua setelah spasi pertama = akhiran. po_number TIDAK diubah. Idempotent --
-- baris yang suffix-nya sudah terisi tidak disentuh.
-- ============================================================
UPDATE `purchase_orders`
SET `po_number_suffix` = TRIM(SUBSTRING(`po_number`, LOCATE(' ', `po_number`) + 1))
WHERE (`po_number_suffix` IS NULL OR `po_number_suffix` = '')
  AND LOCATE(' ', `po_number`) > 0
  AND CHAR_LENGTH(TRIM(SUBSTRING(`po_number`, LOCATE(' ', `po_number`) + 1))) BETWEEN 1 AND 30;
