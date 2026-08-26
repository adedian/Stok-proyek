-- ============================================================
-- REVISI 7 (2026-08-26): Sumber Dana Pembayaran (Bank/Kas Kecil/Kas Project)
--
-- Payment sebelumnya cuma punya payment_method_id (Cek/Giro/Transfer Bank/
-- Tunai dari master payment_methods) TANPA konsep sumber dana yang lebih
-- tinggi. Ditambah `funding_source` supaya bisa dibedakan BK/KK/KKP --
-- payment_method_id (Jenis) TETAP dipakai, tapi sekarang HANYA relevan/wajib
-- saat funding_source = 'bank' (lihat PaymentController::validateInput()).
--
-- Default 'bank' untuk baris LAMA -- semua pembayaran sebelum revisi ini
-- memang selalu diisi payment_method_id (Cek/Giro/Transfer Bank/Tunai), jadi
-- 'bank' adalah nilai yang paling konsisten dengan histori tanpa mengubah
-- data. Nomor pembayaran YANG SUDAH TERBIT (format lama PAY/YYYY/MM/NNNN)
-- TIDAK diubah/direnumber -- cuma pembayaran BARU yang pakai format baru
-- "001/BK.HME/VIII/2026" dkk (lihat Payment::generatePaymentNumber()).
-- ============================================================

ALTER TABLE `payments`
  ADD COLUMN `funding_source` ENUM('bank','kas_kecil','kas_project') NOT NULL DEFAULT 'bank' AFTER `payment_method_id`;
